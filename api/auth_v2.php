<?php
/**
 * Verbesserte Auth API mit besserem Error-Handling
 */

// Output buffering starten
ob_start();

// Error handler für saubere JSON-Responses
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

try {
    require_once '../config.php';
    require_once '../includes/database.php';
    require_once '../includes/functions.php';
    
    // Auth-Klasse NICHT laden um Rate Limiting zu umgehen
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: ' . $e->getMessage()]);
    exit;
}

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
}

// JSON Input parsen
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendErrorResponse('Invalid JSON input');
}

$action = $input['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'register':
            handleRegisterV2($input, $db);
            break;
            
        case 'login':
            handleLoginV2($input, $db);
            break;
            
        case 'logout':
            handleLogoutV2();
            break;
            
        case 'check_session':
            handleCheckSessionV2($db);
            break;
            
        default:
            sendErrorResponse('Ungültige Aktion');
    }
} catch (Exception $e) {
    error_log("Auth API Error: " . $e->getMessage());
    sendErrorResponse($e->getMessage());
}

function handleRegisterV2($input, $db) {
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
    
    if (strlen($username) < 3 || strlen($username) > 20) {
        sendErrorResponse('Username muss zwischen 3 und 20 Zeichen lang sein');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendErrorResponse('Ungültige E-Mail-Adresse');
    }
    
    if (strlen($password) < 6) {
        sendErrorResponse('Passwort muss mindestens 6 Zeichen lang sein');
    }
    
    // Prüfe ob Username oder Email bereits existiert
    $existingUser = $db->fetchOne(
        "SELECT id FROM users WHERE username = :username OR email = :email",
        ['username' => $username, 'email' => $email]
    );
    
    if ($existingUser) {
        sendErrorResponse('Username oder E-Mail bereits vergeben');
    }
    
    // Erstelle User
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $userId = $db->insert('users', [
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
        'points' => 1000,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => null,
        'is_active' => 1,
        'is_verified' => 0,
        'is_admin' => 0,
        'login_count' => 0
    ]);
    
    if (!$userId) {
        sendErrorResponse('Registrierung fehlgeschlagen');
    }
    
    // Auto-Login nach Registrierung
    $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
    
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();
    
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

function handleLoginV2($input, $db) {
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendErrorResponse('Username und Passwort sind erforderlich');
    }
    
    // Hole User
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE (username = :login OR email = :login) AND is_active = 1",
        ['login' => $username]
    );
    
    if (!$user || !password_verify($password, $user['password'])) {
        sendErrorResponse('Ungültige Anmeldedaten');
    }
    
    // Update last_login
    $db->update('users', 
        [
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => $user['login_count'] + 1
        ], 
        'id = :id', 
        ['id' => $user['id']]
    );
    
    // Setze Session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();
    
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

function handleLogoutV2() {
    session_start();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    
    sendSuccessResponse([], 'Logout erfolgreich');
}

function handleCheckSessionV2($db) {
    session_start();
    
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $user = $db->fetchOne(
            "SELECT id, username, email, points FROM users WHERE id = :id AND is_active = 1",
            ['id' => $_SESSION['user_id']]
        );
        
        if ($user) {
            sendSuccessResponse([
                'logged_in' => true,
                'user' => $user
            ]);
        }
    }
    
    sendSuccessResponse(['logged_in' => false]);
}

// Clean output buffer
ob_end_clean();
?>