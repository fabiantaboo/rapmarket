<?php
/**
 * Auth API v3 - Vereinfacht und mit besserer Fehlerbehandlung
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
            handleRegisterV3($input, $db);
            break;
            
        case 'login':
            handleLoginV3($input, $db);
            break;
            
        case 'logout':
            handleLogoutV3();
            break;
            
        case 'check_session':
            handleCheckSessionV3($db);
            break;
            
        default:
            sendErrorResponse('Ungültige Aktion');
    }
} catch (Exception $e) {
    error_log("Auth API Error: " . $e->getMessage());
    sendErrorResponse($e->getMessage());
}

function handleRegisterV3($input, $db) {
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
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
        "SELECT id FROM users WHERE username = ? OR email = ?",
        [$username, $email]
    );
    
    if ($existingUser) {
        sendErrorResponse('Username oder E-Mail bereits vergeben');
    }
    
    // Erstelle User
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $result = $db->execute(
        "INSERT INTO users (username, email, password, points, created_at, is_active, is_verified, is_admin, login_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$username, $email, $hashedPassword, 1000, date('Y-m-d H:i:s'), 1, 0, 0, 0]
    );
    
    if (!$result) {
        sendErrorResponse('Registrierung fehlgeschlagen');
    }
    
    // Hole erstellten User
    $user = $db->fetchOne("SELECT * FROM users WHERE username = ? AND email = ?", [$username, $email]);
    
    if (!$user) {
        sendErrorResponse('User konnte nicht erstellt werden');
    }
    
    // Auto-Login nach Registrierung
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

function handleLoginV3($input, $db) {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendErrorResponse('Username und Passwort sind erforderlich');
    }
    
    // Hole User - vereinfachte Abfrage
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE username = ? AND is_active = 1",
        [$username]
    );
    
    // Falls nicht per Username gefunden, versuche Email
    if (!$user) {
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND is_active = 1",
            [$username]
        );
    }
    
    if (!$user) {
        sendErrorResponse('User nicht gefunden');
    }
    
    // Password prüfen
    if (!password_verify($password, $user['password'])) {
        sendErrorResponse('Falsches Passwort');
    }
    
    // Update last_login
    $db->execute(
        "UPDATE users SET last_login = ?, login_count = login_count + 1 WHERE id = ?",
        [date('Y-m-d H:i:s'), $user['id']]
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
            'last_login' => date('Y-m-d H:i:s')
        ]
    ], 'Login erfolgreich!');
}

function handleLogoutV3() {
    session_start();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    
    sendSuccessResponse([], 'Logout erfolgreich');
}

function handleCheckSessionV3($db) {
    session_start();
    
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $user = $db->fetchOne(
            "SELECT id, username, email, points FROM users WHERE id = ? AND is_active = 1",
            [$_SESSION['user_id']]
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

ob_end_clean();
?>