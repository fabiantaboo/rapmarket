<?php
/**
 * API Endpoint für Events
 */

// Output buffering starten
ob_start();

// Error handling für saubere JSON-Antworten
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
    
    Logger::info('Events API initialized');
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: ' . $e->getMessage()]);
    if (class_exists('Logger')) {
        Logger::logException($e, ['api' => 'events', 'stage' => 'initialization']);
    }
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;

try {
    Logger::logApiRequest('events.php', $method, $input);
    
    switch ($method) {
        case 'GET':
            handleGetEvents($db);
            break;
            
        case 'POST':
            $action = $input['action'] ?? '';
            switch ($action) {
                case 'place_bet':
                    handlePlaceBet($input, $db);
                    break;
                case 'get_user_bets':
                    handleGetUserBets($db);
                    break;
                default:
                    sendErrorResponse('Ungültige Aktion');
            }
            break;
            
        default:
            sendErrorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    Logger::logException($e, ['api' => 'events', 'method' => $method, 'input' => $input]);
    sendErrorResponse($e->getMessage());
}

function handleGetEvents($db) {
    $status = $_GET['status'] ?? 'active';
    $category = $_GET['category'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    
    Logger::debug('Fetching events', [
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
            $event['is_upcoming'] = strtotime($event['start_date']) > time();
            $event['is_live'] = strtotime($event['start_date']) <= time() && strtotime($event['end_date']) > time() && $event['status'] === 'active';
        }
        
        Logger::info('Events fetched successfully', ['count' => count($events)]);
        
        sendSuccessResponse([
            'events' => $events,
            'total' => count($events)
        ]);
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'get_events']);
        sendErrorResponse('Fehler beim Laden der Events');
    }
}

function handlePlaceBet($input, $db) {
    session_start();
    
    // Auth check
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Nicht authentifiziert', 401);
    }
    
    $userId = $_SESSION['user_id'];
    $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
    
    $eventId = (int)($input['event_id'] ?? 0);
    $optionId = (int)($input['option_id'] ?? 0);
    $amount = (int)($input['amount'] ?? 0);
    
    // Validierung
    if (!$eventId || !$optionId || !$amount) {
        sendErrorResponse('Ungültige Parameter');
    }
    
    if (!isValidBetAmount($amount, $user['points'])) {
        sendErrorResponse('Ungültiger Wetteinsatz. Min: ' . MIN_BET_AMOUNT . ', Max: ' . MAX_BET_AMOUNT);
    }
    
    // Prüfe Event
    $event = $db->fetchOne("
        SELECT * FROM events 
        WHERE id = :id AND status = 'active'
    ", ['id' => $eventId]);
    
    if (!$event) {
        sendErrorResponse('Event nicht gefunden oder nicht mehr aktiv');
    }
    
    // Prüfe Option
    $option = $db->fetchOne("
        SELECT * FROM event_options 
        WHERE id = :id AND event_id = :event_id
    ", ['id' => $optionId, 'event_id' => $eventId]);
    
    if (!$option) {
        sendErrorResponse('Wettoption nicht gefunden');
    }
    
    // Prüfe ob User genug Punkte hat
    if ($user['points'] < $amount) {
        sendErrorResponse('Nicht genügend Punkte verfügbar');
    }
    
    // Prüfe Event-Limits
    if ($amount < $event['min_bet'] || $amount > $event['max_bet']) {
        sendErrorResponse("Wetteinsatz muss zwischen {$event['min_bet']} und {$event['max_bet']} Punkten liegen");
    }
    
    // Prüfe ob User bereits auf dieses Event gesetzt hat
    $existingBet = $db->fetchOne("
        SELECT id FROM bets 
        WHERE user_id = :user_id AND event_id = :event_id AND status = 'active'
    ", ['user_id' => $user['id'], 'event_id' => $eventId]);
    
    if ($existingBet) {
        sendErrorResponse('Sie haben bereits auf dieses Event gesetzt');
    }
    
    $potentialWinnings = calculateWinnings($amount, $option['odds']);
    
    // Transaktion starten
    $db->beginTransaction();
    
    try {
        // Punkte abziehen
        $auth->deductPoints($user['id'], $amount, "Wette auf Event: {$event['title']}");
        
        // Wette erstellen
        $betId = $db->insert('bets', [
            'user_id' => $user['id'],
            'event_id' => $eventId,
            'option_id' => $optionId,
            'amount' => $amount,
            'odds' => $option['odds'],
            'potential_winnings' => $potentialWinnings,
            'status' => 'active',
            'placed_at' => date('Y-m-d H:i:s')
        ]);
        
        $db->commit();
        
        // Neue Punktezahl holen
        $newUser = $auth->getCurrentUser();
        
        writeLog('INFO', 'Bet placed', [
            'user_id' => $user['id'],
            'event_id' => $eventId,
            'option_id' => $optionId,
            'amount' => $amount,
            'bet_id' => $betId
        ]);
        
        sendSuccessResponse([
            'bet_id' => $betId,
            'new_points' => $newUser['points'],
            'potential_winnings' => $potentialWinnings
        ], 'Wette erfolgreich platziert!');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleGetUserBets() {
    global $auth, $db;
    
    $auth->requireLogin();
    $user = $auth->getCurrentUser();
    
    $status = $_GET['status'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    
    $whereClause = ["b.user_id = :user_id"];
    $params = ['user_id' => $user['id']];
    
    if (!empty($status)) {
        $whereClause[] = "b.status = :status";
        $params['status'] = $status;
    }
    
    $whereSQL = implode(' AND ', $whereClause);
    
    $bets = $db->fetchAll("
        SELECT 
            b.*,
            e.title as event_title,
            e.status as event_status,
            eo.option_text
        FROM bets b
        JOIN events e ON b.event_id = e.id
        JOIN event_options eo ON b.option_id = eo.id
        WHERE {$whereSQL}
        ORDER BY b.placed_at DESC
        LIMIT :limit OFFSET :offset
    ", array_merge($params, ['limit' => $limit, 'offset' => $offset]));
    
    // Formatiere Daten
    foreach ($bets as &$bet) {
        $bet['formatted_placed_at'] = formatDate($bet['placed_at']);
        $bet['time_ago'] = timeAgo($bet['placed_at']);
        
        if ($bet['resolved_at']) {
            $bet['formatted_resolved_at'] = formatDate($bet['resolved_at']);
        }
    }
    
    sendSuccessResponse(['bets' => $bets]);
}

// Clean output buffer
ob_end_clean();
?>