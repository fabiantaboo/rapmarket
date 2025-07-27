<?php
/**
 * Events API v2 - Saubere Version für RapMarket.de
 */

ob_start();

set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

try {
    require_once '../config.php';
    require_once '../includes/database.php';
    require_once '../includes/functions.php';
    require_once '../includes/logger.php';
    
    Logger::info('Events API v2 initialized');
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: ' . $e->getMessage()]);
    if (class_exists('Logger')) {
        Logger::logException($e, ['api' => 'events_v2', 'stage' => 'initialization']);
    }
    exit;
}

$db = Database::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetEvents($db);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostEvent($db);
    } else {
        sendErrorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    Logger::logException($e, ['api' => 'events_v2']);
    sendErrorResponse($e->getMessage());
}

function handleGetEvents($db) {
    $status = $_GET['status'] ?? 'active';
    $category = $_GET['category'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    
    Logger::debug('Fetching events v2', [
        'status' => $status,
        'category' => $category,
        'limit' => $limit
    ]);
    
    try {
        $sql = "
            SELECT e.*,
                   DATE_FORMAT(e.start_date, '%d.%m.%Y %H:%i') as formatted_event_date,
                   DATE_FORMAT(e.created_at, '%d.%m.%Y') as formatted_created_date,
                   u.username as creator_name
            FROM events e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($status !== 'all') {
            $sql .= " AND e.status = :status";
            $params['status'] = $status;
        }
        
        if ($category) {
            $sql .= " AND e.category = :category";
            $params['category'] = $category;
        }
        
        $sql .= " ORDER BY e.start_date ASC, e.created_at DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT :limit";
            $params['limit'] = $limit;
        }
        
        $events = $db->fetchAll($sql, $params);
        
        // Hole Event-Optionen für jedes Event
        foreach ($events as &$event) {
            $event['options'] = $db->fetchAll(
                "SELECT * FROM event_options WHERE event_id = :event_id ORDER BY odds ASC",
                ['event_id' => $event['id']]
            );
            
            // Hole Wett-Statistiken
            $betStats = $db->fetchOne(
                "SELECT COUNT(*) as bet_count, SUM(amount) as total_amount FROM bets WHERE event_id = :event_id",
                ['event_id' => $event['id']]
            );
            
            $event['bet_count'] = (int)($betStats['bet_count'] ?? 0);
            $event['total_bets'] = (int)($betStats['total_amount'] ?? 0);
            
            // Event-Status für Frontend
            $now = time();
            $startTime = strtotime($event['start_date']);
            $endTime = strtotime($event['end_date']);
            
            $event['is_upcoming'] = $startTime > $now;
            $event['is_live'] = $startTime <= $now && $endTime > $now && $event['status'] === 'active';
            $event['is_ended'] = $endTime <= $now;
        }
        
        Logger::info('Events v2 fetched successfully', ['count' => count($events)]);
        
        sendSuccessResponse([
            'events' => $events,
            'total' => count($events)
        ]);
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'get_events_v2']);
        sendErrorResponse('Fehler beim Laden der Events');
    }
}

function handlePostEvent($db) {
    session_start();
    
    // Auth check
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Nicht authentifiziert', 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }
    
    $action = $input['action'] ?? '';
    $userId = $_SESSION['user_id'];
    
    Logger::logApiRequest('events_v2.php', 'POST', $input);
    
    switch ($action) {
        case 'place_bet':
            handlePlaceBet($input, $db, $userId);
            break;
            
        case 'get_user_bets':
            handleGetUserBets($db, $userId);
            break;
            
        default:
            sendErrorResponse('Ungültige Aktion');
    }
}

function handlePlaceBet($input, $db, $userId) {
    try {
        $eventId = (int)($input['event_id'] ?? 0);
        $optionId = (int)($input['option_id'] ?? 0);
        $amount = (int)($input['amount'] ?? 0);
        
        Logger::info('Placing bet v2', [
            'user_id' => $userId,
            'event_id' => $eventId,
            'option_id' => $optionId,
            'amount' => $amount
        ]);
        
        // Validierung
        if (!$eventId || !$optionId || $amount < 10) {
            sendErrorResponse('Ungültige Eingabedaten');
        }
        
        // Hole Event und prüfe Status
        $event = $db->fetchOne("SELECT * FROM events WHERE id = :id", ['id' => $eventId]);
        if (!$event) {
            sendErrorResponse('Event nicht gefunden');
        }
        
        if ($event['status'] !== 'active') {
            sendErrorResponse('Event ist nicht aktiv');
        }
        
        if (strtotime($event['end_date']) <= time()) {
            sendErrorResponse('Event ist bereits beendet');
        }
        
        if ($amount < $event['min_bet'] || $amount > $event['max_bet']) {
            sendErrorResponse("Einsatz muss zwischen {$event['min_bet']} und {$event['max_bet']} Punkten liegen");
        }
        
        // Hole Option
        $option = $db->fetchOne(
            "SELECT * FROM event_options WHERE id = :id AND event_id = :event_id",
            ['id' => $optionId, 'event_id' => $eventId]
        );
        
        if (!$option) {
            sendErrorResponse('Wett-Option nicht gefunden');
        }
        
        // Prüfe User-Punkte
        $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
        if (!$user || $user['points'] < $amount) {
            sendErrorResponse('Nicht genügend Punkte');
        }
        
        // Prüfe ob User bereits auf dieses Event gesetzt hat
        $existingBet = $db->fetchOne(
            "SELECT * FROM bets WHERE user_id = :user_id AND event_id = :event_id",
            ['user_id' => $userId, 'event_id' => $eventId]
        );
        
        if ($existingBet) {
            sendErrorResponse('Du hast bereits auf dieses Event gesetzt');
        }
        
        // Transaction starten
        $db->beginTransaction();
        
        try {
            // Wette erstellen
            $betId = $db->insert('bets', [
                'user_id' => $userId,
                'event_id' => $eventId,
                'option_id' => $optionId,
                'amount' => $amount,
                'odds' => $option['odds'],
                'potential_win' => $amount * $option['odds'],
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Punkte abziehen
            $newPoints = $user['points'] - $amount;
            $db->update('users', 
                ['points' => $newPoints], 
                'id = :id', 
                ['id' => $userId]
            );
            
            // Punkt-Transaktion loggen (falls Tabelle existiert)
            try {
                $db->insert('point_transactions', [
                    'user_id' => $userId,
                    'type' => 'bet_placed',
                    'amount' => -$amount,
                    'balance_after' => $newPoints,
                    'description' => "Wette auf Event: {$event['title']}",
                    'reference_id' => $betId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                // Ignore if point_transactions table doesn't exist
                Logger::warning('Point transactions table not available', ['error' => $e->getMessage()]);
            }
            
            $db->commit();
            
            Logger::logUserAction('place_bet', $userId, [
                'bet_id' => $betId,
                'event_id' => $eventId,
                'amount' => $amount,
                'odds' => $option['odds'],
                'potential_win' => $amount * $option['odds']
            ]);
            
            sendSuccessResponse([
                'bet_id' => $betId,
                'new_points' => $newPoints,
                'potential_win' => $amount * $option['odds']
            ], 'Wette erfolgreich platziert!');
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'place_bet_v2', 'user_id' => $userId]);
        sendErrorResponse('Fehler beim Platzieren der Wette: ' . $e->getMessage());
    }
}

function handleGetUserBets($db, $userId) {
    try {
        $bets = $db->fetchAll("
            SELECT b.*,
                   e.title as event_title,
                   e.start_date as event_date,
                   e.status as event_status,
                   eo.option_text,
                   DATE_FORMAT(b.created_at, '%d.%m.%Y %H:%i') as formatted_date
            FROM bets b
            JOIN events e ON b.event_id = e.id
            JOIN event_options eo ON b.option_id = eo.id
            WHERE b.user_id = :user_id
            ORDER BY b.created_at DESC
        ", ['user_id' => $userId]);
        
        Logger::debug('User bets v2 fetched', ['user_id' => $userId, 'count' => count($bets)]);
        
        sendSuccessResponse(['bets' => $bets]);
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'get_user_bets_v2', 'user_id' => $userId]);
        sendErrorResponse('Fehler beim Laden der Wetten');
    }
}

ob_end_clean();
?>