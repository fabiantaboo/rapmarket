<?php
/**
 * Debug-Version der Auth API mit detaillierter Fehlerausgabe
 */

// Output buffering starten
ob_start();

// Error handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Detaillierte Debug-Funktion
function debugResponse($message, $data = [], $success = true) {
    $response = [
        'debug' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

try {
    // Schritt 1: Config laden
    if (!file_exists('../config.php')) {
        debugResponse('Config-Datei nicht gefunden', [], false);
    }
    
    require_once '../config.php';
    
    // Schritt 2: Includes laden
    require_once '../includes/database.php';
    require_once '../includes/functions.php';
    require_once '../includes/auth.php';
    
    // Schritt 3: Datenbank testen
    $db = Database::getInstance();
    
    // Schritt 4: Tabellen prüfen
    $tables = ['users', 'rate_limits', 'user_logs'];
    $tableStatus = [];
    foreach ($tables as $table) {
        $tableStatus[$table] = $db->tableExists($table);
    }
    
    // Schritt 5: Request verarbeiten
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debugResponse('GET Request - zeige Debug-Info', [
            'config_loaded' => defined('DB_NAME'),
            'db_name' => DB_NAME ?? 'undefined',
            'tables' => $tableStatus,
            'user_count' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0
        ]);
    }
    
    // JSON Input parsen
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        debugResponse('Ungültiger JSON Input', ['raw_input' => file_get_contents('php://input')], false);
    }
    
    $action = $input['action'] ?? '';
    
    // Auth-Klasse instanziieren
    $auth = new Auth();
    
    switch ($action) {
        case 'register':
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            
            debugResponse('Register-Versuch', [
                'username' => $username,
                'email' => $email,
                'password_length' => strlen($password),
                'tables' => $tableStatus
            ]);
            
            // Validierung
            if (empty($username) || empty($email) || empty($password)) {
                debugResponse('Validierungsfehler: Leere Felder', [
                    'username_empty' => empty($username),
                    'email_empty' => empty($email),
                    'password_empty' => empty($password)
                ], false);
            }
            
            try {
                // Schritt für Schritt Registrierung
                
                // 1. Prüfe ob User existiert
                $existingUser = $db->fetchOne(
                    "SELECT id FROM users WHERE username = :username OR email = :email",
                    ['username' => $username, 'email' => $email]
                );
                
                if ($existingUser) {
                    debugResponse('User existiert bereits', ['existing_user_id' => $existingUser['id']], false);
                }
                
                // 2. Erstelle User ohne Auth-Klasse (um Rate Limiting zu umgehen)
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $userId = $db->insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'points' => 1000,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => 1,
                    'is_verified' => 0,
                    'is_admin' => 0
                ]);
                
                if ($userId) {
                    debugResponse('Registrierung erfolgreich', [
                        'user_id' => $userId,
                        'username' => $username,
                        'points' => 1000
                    ]);
                } else {
                    debugResponse('Registrierung fehlgeschlagen - Insert failed', [], false);
                }
                
            } catch (Exception $e) {
                debugResponse('Registrierung Exception', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ], false);
            }
            break;
            
        case 'login':
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            
            try {
                // Direkte DB-Abfrage ohne Auth-Klasse
                $user = $db->fetchOne(
                    "SELECT * FROM users WHERE (username = :login OR email = :login) AND is_active = 1",
                    ['login' => $username]
                );
                
                if (!$user) {
                    debugResponse('User nicht gefunden', ['username' => $username], false);
                }
                
                if (!password_verify($password, $user['password'])) {
                    debugResponse('Passwort falsch', [], false);
                }
                
                // Login erfolgreich
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                debugResponse('Login erfolgreich', [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'points' => $user['points']
                    ]
                ]);
                
            } catch (Exception $e) {
                debugResponse('Login Exception', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], false);
            }
            break;
            
        case 'check_session':
            session_start();
            if (isset($_SESSION['user_id'])) {
                $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $_SESSION['user_id']]);
                debugResponse('Session aktiv', ['user' => $user]);
            } else {
                debugResponse('Keine Session', ['logged_in' => false]);
            }
            break;
            
        default:
            debugResponse('Unbekannte Aktion', ['action' => $action], false);
    }
    
} catch (Exception $e) {
    ob_clean();
    debugResponse('Kritischer Fehler', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ], false);
}

ob_end_clean();
?>