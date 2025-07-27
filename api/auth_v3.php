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
    if (!file_exists('../config.php')) {
        throw new Exception('Config file not found');
    }
    require_once '../config.php';
    
    if (!file_exists('../includes/database.php')) {
        throw new Exception('Database class not found');
    }
    require_once '../includes/database.php';
    
    if (!file_exists('../includes/functions.php')) {
        throw new Exception('Functions file not found');
    }
    require_once '../includes/functions.php';
    
    // Logger laden
    if (!file_exists('../includes/logger.php')) {
        throw new Exception('Logger class not found');
    }
    require_once '../includes/logger.php';
    
    // Teste ob wichtige Funktionen verfügbar sind
    if (!function_exists('sendErrorResponse')) {
        throw new Exception('sendErrorResponse function not available');
    }
    
    if (!function_exists('sendSuccessResponse')) {
        throw new Exception('sendSuccessResponse function not available');
    }
    
    Logger::info('Auth API v3 initialized');
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    $error = ['error' => 'Server configuration error: ' . $e->getMessage()];
    echo json_encode($error);
    
    // Log auch in Datei falls Logger verfügbar
    if (class_exists('Logger')) {
        Logger::logException($e, ['api' => 'auth_v3', 'stage' => 'initialization']);
    }
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
    Logger::logApiRequest('auth_v3.php', $_SERVER['REQUEST_METHOD'], $input);
    
    switch ($action) {
        case 'register':
            Logger::info('Registration attempt', ['username' => $input['username'] ?? 'unknown']);
            handleRegisterV3($input, $db);
            break;
            
        case 'login':
            Logger::info('Login attempt', ['username' => $input['username'] ?? 'unknown']);
            handleLoginV3($input, $db);
            break;
            
        case 'logout':
            Logger::info('Logout attempt');
            handleLogoutV3();
            break;
            
        case 'check_session':
            Logger::debug('Session check');
            handleCheckSessionV3($db);
            break;
            
        case 'get_user_stats':
            Logger::debug('Get user stats');
            handleGetUserStatsV3($db);
            break;
            
        default:
            Logger::warning('Invalid action', ['action' => $action]);
            sendErrorResponse('Ungültige Aktion');
    }
} catch (Exception $e) {
    Logger::logException($e, ['api' => 'auth_v3', 'action' => $action, 'input' => $input]);
    sendErrorResponse($e->getMessage());
}

function handleRegisterV3($input, $db) {
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    Logger::debug('Registration validation', [
        'username_length' => strlen($username),
        'email_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        'password_length' => strlen($password)
    ]);
    
    // Validierung
    if (empty($username) || empty($email) || empty($password)) {
        Logger::warning('Registration failed: Empty fields', [
            'username_empty' => empty($username),
            'email_empty' => empty($email),
            'password_empty' => empty($password)
        ]);
        sendErrorResponse('Alle Felder sind erforderlich');
    }
    
    if ($password !== $confirmPassword) {
        Logger::warning('Registration failed: Password mismatch');
        sendErrorResponse('Passwörter stimmen nicht überein');
    }
    
    if (strlen($username) < 3 || strlen($username) > 20) {
        Logger::warning('Registration failed: Username length', ['length' => strlen($username)]);
        sendErrorResponse('Username muss zwischen 3 und 20 Zeichen lang sein');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Logger::warning('Registration failed: Invalid email', ['email' => $email]);
        sendErrorResponse('Ungültige E-Mail-Adresse');
    }
    
    if (strlen($password) < 6) {
        Logger::warning('Registration failed: Password too short', ['length' => strlen($password)]);
        sendErrorResponse('Passwort muss mindestens 6 Zeichen lang sein');
    }
    
    // Prüfe ob Username oder Email bereits existiert
    $existingUser = $db->fetchOne(
        "SELECT id FROM users WHERE username = :username OR email = :email",
        ['username' => $username, 'email' => $email]
    );
    
    if ($existingUser) {
        Logger::warning('Registration failed: User already exists', [
            'existing_user_id' => $existingUser['id'],
            'attempted_username' => $username,
            'attempted_email' => $email
        ]);
        sendErrorResponse('Username oder E-Mail bereits vergeben');
    }
    
    // Erstelle User
    Logger::debug('Creating new user', ['username' => $username, 'email' => $email]);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $userId = $db->insert('users', [
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
        'points' => 1000,
        'created_at' => date('Y-m-d H:i:s'),
        'is_active' => 1,
        'is_verified' => 0,
        'is_admin' => 0,
        'login_count' => 0
    ]);
    
    if (!$userId) {
        Logger::error('Registration failed: Database insert failed', ['username' => $username]);
        sendErrorResponse('Registrierung fehlgeschlagen');
    }
    
    Logger::info('User created successfully', ['user_id' => $userId, 'username' => $username]);
    
    // Hole erstellten User
    $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
    
    if (!$user) {
        Logger::error('Registration failed: Could not fetch created user', ['user_id' => $userId]);
        sendErrorResponse('User konnte nicht erstellt werden');
    }
    
    // Auto-Login nach Registrierung
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();
    
    Logger::logUserAction('register', $user['id'], ['auto_login' => true]);
    
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
    
    Logger::debug('Login validation', [
        'username_length' => strlen($username),
        'password_length' => strlen($password)
    ]);
    
    if (empty($username) || empty($password)) {
        Logger::warning('Login failed: Empty credentials', [
            'username_empty' => empty($username),
            'password_empty' => empty($password)
        ]);
        sendErrorResponse('Username und Passwort sind erforderlich');
    }
    
    // Hole User - verwende Named Parameters
    Logger::debug('Searching for user', ['login' => $username]);
    
    try {
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE (username = :username OR email = :email) AND is_active = 1",
            ['username' => $username, 'email' => $username]
        );
        
        Logger::debug('Database query executed', [
            'user_found' => $user ? true : false,
            'user_id' => $user ? $user['id'] : null
        ]);
        
    } catch (Exception $e) {
        Logger::error('Database error during user search', [
            'error' => $e->getMessage(),
            'login' => $username,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Datenbankfehler bei User-Suche');
    }
    
    if (!$user) {
        Logger::warning('Login failed: User not found', ['login' => $username]);
        sendErrorResponse('User nicht gefunden');
    }
    
    Logger::debug('User found, verifying password', ['user_id' => $user['id'], 'username' => $user['username']]);
    
    // Password prüfen
    if (!password_verify($password, $user['password'])) {
        Logger::warning('Login failed: Invalid password', [
            'user_id' => $user['id'],
            'username' => $user['username']
        ]);
        sendErrorResponse('Falsches Passwort');
    }
    
    Logger::info('Password verified, updating login stats', ['user_id' => $user['id']]);
    
    // Update last_login
    $updateResult = $db->update('users', 
        [
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => $user['login_count'] + 1
        ], 
        'id = :id', 
        ['id' => $user['id']]
    );
    
    Logger::debug('Login stats updated', ['update_result' => $updateResult]);
    
    // Setze Session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();
    
    Logger::logUserAction('login', $user['id'], [
        'login_count' => $user['login_count'] + 1,
        'session_id' => session_id()
    ]);
    
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
    $userId = $_SESSION['user_id'] ?? null;
    
    Logger::logUserAction('logout', $userId, ['session_id' => session_id()]);
    
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    
    Logger::info('User logged out successfully', ['user_id' => $userId]);
    
    sendSuccessResponse([], 'Logout erfolgreich');
}

function handleCheckSessionV3($db) {
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

function handleGetUserStatsV3($db) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Nicht angemeldet');
    }
    
    $userId = $_SESSION['user_id'];
    
    try {
        // Hole Benutzer-Statistiken
        $user = $db->fetchOne(
            "SELECT id, username, points, created_at FROM users WHERE id = :id",
            ['id' => $userId]
        );
        
        if (!$user) {
            sendErrorResponse('Benutzer nicht gefunden');
        }
        
        // Hole Wett-Statistiken - prüfe erst ob bets Tabelle existiert
        $betStats = null;
        $tableExists = $db->fetchOne("SHOW TABLES LIKE 'bets'");
        
        if ($tableExists) {
            $betStats = $db->fetchOne("
                SELECT 
                    COUNT(*) as total_bets,
                    SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as losses,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as pending,
                    CASE 
                        WHEN COUNT(*) > 0 THEN 
                            ROUND((SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1)
                        ELSE 0 
                    END as win_rate
                FROM bets 
                WHERE user_id = :user_id
            ", ['user_id' => $userId]);
        }
        
        // Hole Rang in der Rangliste
        $rankQuery = $db->fetchOne("
            SELECT COUNT(*) + 1 as rank 
            FROM users 
            WHERE points > :points AND is_active = 1
        ", ['points' => $user['points']]);
        
        $stats = [
            'total_bets' => $betStats ? ($betStats['total_bets'] ?? 0) : 0,
            'wins' => $betStats ? ($betStats['wins'] ?? 0) : 0,
            'losses' => $betStats ? ($betStats['losses'] ?? 0) : 0,
            'pending' => $betStats ? ($betStats['pending'] ?? 0) : 0,
            'win_rate' => $betStats ? ($betStats['win_rate'] ?? 0) : 0,
            'rank' => $rankQuery['rank'] ?? '-'
        ];
        
        Logger::info('User stats loaded', ['user_id' => $userId, 'stats' => $stats]);
        
        sendSuccessResponse(['stats' => $stats]);
        
    } catch (Exception $e) {
        Logger::error('Error loading user stats', ['user_id' => $userId, 'error' => $e->getMessage()]);
        sendErrorResponse('Fehler beim Laden der Statistiken');
    }
}

ob_end_clean();
?>