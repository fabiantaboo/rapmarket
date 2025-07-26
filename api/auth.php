<?php
/**
 * API Endpoint für Authentifizierung
 */

require_once '../includes/init.php';

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
}

// Rate Limiting
checkApiRateLimit();

// Hole JSON-Daten
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'register':
            handleRegister($input);
            break;
            
        case 'login':
            handleLogin($input);
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        case 'check_session':
            handleCheckSession();
            break;
            
        case 'get_user_data':
            handleGetUserData();
            break;
            
        default:
            sendErrorResponse('Ungültige Aktion');
    }
} catch (Exception $e) {
    writeLog('ERROR', 'Auth API Error: ' . $e->getMessage(), ['action' => $action, 'input' => $input]);
    sendErrorResponse($e->getMessage());
}

function handleRegister($input) {
    global $auth;
    
    $username = sanitizeInput($input['username'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validierung
    if (empty($username) || empty($email) || empty($password)) {
        sendErrorResponse('Alle Felder sind erforderlich');
    }
    
    if ($password !== $confirmPassword) {
        sendErrorResponse('Passwörter stimmen nicht überein');
    }
    
    if (!isValidUsername($username)) {
        sendErrorResponse('Username muss 3-20 Zeichen lang sein und darf nur Buchstaben, Zahlen und Unterstriche enthalten');
    }
    
    if (!isValidEmail($email)) {
        sendErrorResponse('Ungültige E-Mail-Adresse');
    }
    
    if (strlen($password) < 6) {
        sendErrorResponse('Passwort muss mindestens 6 Zeichen lang sein');
    }
    
    // Registrierung durchführen
    $userId = $auth->register($username, $email, $password);
    
    // Auto-Login nach Registrierung
    $user = $auth->login($username, $password);
    
    sendSuccessResponse([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'points' => $user['points'],
            'created_at' => $user['created_at']
        ]
    ], 'Registrierung erfolgreich! Willkommen bei RapMarket.de!');
}

function handleLogin($input) {
    global $auth;
    
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $rememberMe = !empty($input['remember_me']);
    
    if (empty($username) || empty($password)) {
        sendErrorResponse('Username und Passwort sind erforderlich');
    }
    
    $user = $auth->login($username, $password);
    
    // Remember Me Cookie setzen
    if ($rememberMe) {
        $token = generateSecureToken();
        // TODO: Implement remember me token in database
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true); // 30 Tage
    }
    
    sendSuccessResponse([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'points' => $user['points'],
            'last_login' => $user['last_login']
        ]
    ], 'Login erfolgreich!');
}

function handleLogout() {
    global $auth;
    
    $auth->logout();
    
    // Remember Me Cookie löschen
    setcookie('remember_token', '', time() - 3600, '/');
    
    sendSuccessResponse([], 'Logout erfolgreich');
}

function handleCheckSession() {
    global $auth;
    
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        if ($user) {
            sendSuccessResponse([
                'logged_in' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'points' => $user['points']
                ]
            ]);
        }
    }
    
    sendSuccessResponse(['logged_in' => false]);
}

function handleGetUserData() {
    global $auth, $db;
    
    $auth->requireLogin();
    $user = $auth->getCurrentUser();
    
    // Hole erweiterte User-Statistiken
    $stats = $db->fetchOne("
        SELECT 
            COUNT(b.id) as total_bets,
            SUM(CASE WHEN b.status = 'won' THEN 1 ELSE 0 END) as won_bets,
            SUM(CASE WHEN b.status = 'lost' THEN 1 ELSE 0 END) as lost_bets,
            SUM(CASE WHEN b.status = 'won' THEN b.actual_winnings ELSE 0 END) as total_winnings,
            SUM(b.amount) as total_wagered
        FROM bets b 
        WHERE b.user_id = :user_id
    ", ['user_id' => $user['id']]);
    
    // Hole Rang des Users
    $rank = $db->fetchOne("
        SELECT rank FROM (
            SELECT id, ROW_NUMBER() OVER (ORDER BY points DESC) as rank 
            FROM users WHERE is_active = 1
        ) ranked WHERE id = :user_id
    ", ['user_id' => $user['id']]);
    
    // Hole letzte Transaktionen
    $transactions = $db->fetchAll("
        SELECT amount, type, reason, created_at 
        FROM point_transactions 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 10
    ", ['user_id' => $user['id']]);
    
    sendSuccessResponse([
        'user' => $user,
        'stats' => array_merge($stats, ['rank' => $rank['rank'] ?? 0]),
        'recent_transactions' => $transactions
    ]);
}
?>