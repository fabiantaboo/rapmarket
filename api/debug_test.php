<?php
/**
 * Minimaler Test-Endpoint für API-Debugging
 */

// Output buffering
ob_start();

try {
    // Basic JSON response test
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['error' => 'Invalid JSON input']);
            exit;
        }
        
        $action = $input['action'] ?? 'unknown';
        
        switch ($action) {
            case 'ping':
                echo json_encode(['success' => true, 'message' => 'pong', 'timestamp' => time()]);
                break;
                
            case 'test_db':
                require_once '../config.php';
                require_once '../includes/database.php';
                
                $db = Database::getInstance();
                $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users");
                
                echo json_encode([
                    'success' => true, 
                    'users' => $userCount['count'],
                    'db_name' => DB_NAME
                ]);
                break;
                
            case 'test_login':
                require_once '../config.php';
                require_once '../includes/database.php';
                require_once '../includes/functions.php';
                require_once '../includes/auth.php';
                
                $username = $input['username'] ?? '';
                $password = $input['password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    echo json_encode(['error' => 'Username and password required']);
                    exit;
                }
                
                // Direkte DB-Abfrage ohne Auth-Klasse
                $db = Database::getInstance();
                $user = $db->fetchOne(
                    "SELECT * FROM users WHERE username = :username AND is_active = 1",
                    ['username' => $username]
                );
                
                if ($user && password_verify($password, $user['password'])) {
                    echo json_encode([
                        'success' => true,
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'points' => $user['points']
                        ]
                    ]);
                } else {
                    echo json_encode(['error' => 'Invalid credentials']);
                }
                break;
                
            default:
                echo json_encode(['error' => 'Unknown action: ' . $action]);
        }
    } else {
        echo json_encode(['error' => 'Only POST requests allowed']);
    }
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

ob_end_flush();
?>