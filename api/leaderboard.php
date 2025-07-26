<?php
/**
 * API Endpoint für Leaderboard
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
    require_once '../includes/init.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

// Nur GET-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
}

// Rate Limiting
checkApiRateLimit();

try {
    $type = $_GET['type'] ?? 'points';
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    
    switch ($type) {
        case 'points':
            handlePointsLeaderboard($limit, $offset);
            break;
            
        case 'wins':
            handleWinsLeaderboard($limit, $offset);
            break;
            
        case 'winnings':
            handleWinningsLeaderboard($limit, $offset);
            break;
            
        case 'monthly':
            handleMonthlyLeaderboard($limit, $offset);
            break;
            
        default:
            sendErrorResponse('Ungültiger Leaderboard-Typ');
    }
} catch (Exception $e) {
    writeLog('ERROR', 'Leaderboard API Error: ' . $e->getMessage(), ['type' => $type ?? '']);
    sendErrorResponse($e->getMessage());
}

function handlePointsLeaderboard($limit, $offset) {
    global $db;
    
    $leaderboard = $db->fetchAll("
        SELECT 
            ROW_NUMBER() OVER (ORDER BY points DESC) as rank,
            id,
            username,
            points,
            (SELECT COUNT(*) FROM bets WHERE user_id = users.id AND status = 'won') as wins,
            (SELECT COUNT(*) FROM bets WHERE user_id = users.id) as total_bets,
            created_at
        FROM users 
        WHERE is_active = 1 
        ORDER BY points DESC 
        LIMIT :limit OFFSET :offset
    ", ['limit' => $limit, 'offset' => $offset]);
    
    // Formatiere Daten
    foreach ($leaderboard as &$user) {
        $user['formatted_points'] = formatPoints($user['points']);
        $user['win_rate'] = $user['total_bets'] > 0 ? round(($user['wins'] / $user['total_bets']) * 100, 1) : 0;
        $user['member_since'] = formatDate($user['created_at'], 'M Y');
    }
    
    sendSuccessResponse(['leaderboard' => $leaderboard, 'type' => 'points']);
}

function handleWinsLeaderboard($limit, $offset) {
    global $db;
    
    $leaderboard = $db->fetchAll("
        SELECT 
            ROW_NUMBER() OVER (ORDER BY wins DESC, points DESC) as rank,
            u.id,
            u.username,
            u.points,
            COUNT(b.id) as total_bets,
            SUM(CASE WHEN b.status = 'won' THEN 1 ELSE 0 END) as wins,
            u.created_at
        FROM users u
        LEFT JOIN bets b ON u.id = b.user_id
        WHERE u.is_active = 1
        GROUP BY u.id, u.username, u.points, u.created_at
        HAVING wins > 0
        ORDER BY wins DESC, u.points DESC
        LIMIT :limit OFFSET :offset
    ", ['limit' => $limit, 'offset' => $offset]);
    
    // Formatiere Daten
    foreach ($leaderboard as &$user) {
        $user['formatted_points'] = formatPoints($user['points']);
        $user['win_rate'] = $user['total_bets'] > 0 ? round(($user['wins'] / $user['total_bets']) * 100, 1) : 0;
        $user['member_since'] = formatDate($user['created_at'], 'M Y');
    }
    
    sendSuccessResponse(['leaderboard' => $leaderboard, 'type' => 'wins']);
}

function handleWinningsLeaderboard($limit, $offset) {
    global $db;
    
    $leaderboard = $db->fetchAll("
        SELECT 
            ROW_NUMBER() OVER (ORDER BY total_winnings DESC, points DESC) as rank,
            u.id,
            u.username,
            u.points,
            COUNT(b.id) as total_bets,
            SUM(CASE WHEN b.status = 'won' THEN 1 ELSE 0 END) as wins,
            SUM(CASE WHEN b.status = 'won' THEN b.actual_winnings ELSE 0 END) as total_winnings,
            u.created_at
        FROM users u
        LEFT JOIN bets b ON u.id = b.user_id
        WHERE u.is_active = 1
        GROUP BY u.id, u.username, u.points, u.created_at
        HAVING total_winnings > 0
        ORDER BY total_winnings DESC, u.points DESC
        LIMIT :limit OFFSET :offset
    ", ['limit' => $limit, 'offset' => $offset]);
    
    // Formatiere Daten
    foreach ($leaderboard as &$user) {
        $user['formatted_points'] = formatPoints($user['points']);
        $user['formatted_winnings'] = formatPoints($user['total_winnings']);
        $user['win_rate'] = $user['total_bets'] > 0 ? round(($user['wins'] / $user['total_bets']) * 100, 1) : 0;
        $user['member_since'] = formatDate($user['created_at'], 'M Y');
    }
    
    sendSuccessResponse(['leaderboard' => $leaderboard, 'type' => 'winnings']);
}

function handleMonthlyLeaderboard($limit, $offset) {
    global $db;
    
    $startOfMonth = date('Y-m-01 00:00:00');
    
    $leaderboard = $db->fetchAll("
        SELECT 
            ROW_NUMBER() OVER (ORDER BY monthly_winnings DESC, points DESC) as rank,
            u.id,
            u.username,
            u.points,
            COUNT(b.id) as monthly_bets,
            SUM(CASE WHEN b.status = 'won' THEN 1 ELSE 0 END) as monthly_wins,
            SUM(CASE WHEN b.status = 'won' THEN b.actual_winnings ELSE 0 END) as monthly_winnings,
            SUM(b.amount) as monthly_wagered
        FROM users u
        LEFT JOIN bets b ON u.id = b.user_id AND b.placed_at >= :start_of_month
        WHERE u.is_active = 1
        GROUP BY u.id, u.username, u.points
        HAVING monthly_bets > 0
        ORDER BY monthly_winnings DESC, u.points DESC
        LIMIT :limit OFFSET :offset
    ", ['start_of_month' => $startOfMonth, 'limit' => $limit, 'offset' => $offset]);
    
    // Formatiere Daten
    foreach ($leaderboard as &$user) {
        $user['formatted_points'] = formatPoints($user['points']);
        $user['formatted_winnings'] = formatPoints($user['monthly_winnings']);
        $user['formatted_wagered'] = formatPoints($user['monthly_wagered']);
        $user['win_rate'] = $user['monthly_bets'] > 0 ? round(($user['monthly_wins'] / $user['monthly_bets']) * 100, 1) : 0;
        $user['profit'] = $user['monthly_winnings'] - $user['monthly_wagered'];
        $user['formatted_profit'] = formatPoints($user['profit']);
    }
    
    sendSuccessResponse([
        'leaderboard' => $leaderboard, 
        'type' => 'monthly',
        'period' => formatDate($startOfMonth, 'F Y')
    ]);
}

// Clean output buffer
ob_end_clean();
?>