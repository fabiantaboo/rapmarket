<?php
/**
 * Authentifizierung und Session-Management für RapMarket.de
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    public function register($username, $email, $password) {
        // Validierung
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("Alle Felder sind erforderlich");
        }
        
        if (strlen($username) < 3 || strlen($username) > 20) {
            throw new Exception("Username muss zwischen 3 und 20 Zeichen lang sein");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Ungültige E-Mail-Adresse");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("Passwort muss mindestens 6 Zeichen lang sein");
        }
        
        // Prüfe ob Username oder Email bereits existiert
        $existingUser = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = :username OR email = :email",
            ['username' => $username, 'email' => $email]
        );
        
        if ($existingUser) {
            throw new Exception("Username oder E-Mail bereits vergeben");
        }
        
        // Rate Limiting - max 5 Registrierungen pro IP pro Stunde
        $this->checkRateLimit('register');
        
        // Erstelle User
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $userId = $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'points' => STARTING_POINTS,
            'created_at' => date('Y-m-d H:i:s'),
            'last_login' => null,
            'is_active' => 1
        ]);
        
        // Log die Registrierung
        $this->logAction('register', $userId);
        
        return $userId;
    }
    
    public function login($username, $password) {
        // Rate Limiting
        $this->checkRateLimit('login');
        
        // Hole User
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE (username = :login OR email = :login) AND is_active = 1",
            ['login' => $username]
        );
        
        if (!$user || !password_verify($password, $user['password'])) {
            $this->logAction('failed_login', null, $username);
            throw new Exception("Ungültige Anmeldedaten");
        }
        
        // Update last_login
        $this->db->update('users', 
            ['last_login' => date('Y-m-d H:i:s')], 
            'id = :id', 
            ['id' => $user['id']]
        );
        
        // Setze Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
        
        // Log den Login
        $this->logAction('login', $user['id']);
        
        return $user;
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logAction('logout', $_SESSION['user_id']);
        }
        
        session_destroy();
        setcookie(SESSION_NAME, '', time() - 3600, '/');
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $user = $this->db->fetchOne(
            "SELECT id, username, email, points, created_at, last_login FROM users WHERE id = :id AND is_active = 1",
            ['id' => $_SESSION['user_id']]
        );
        
        return $user;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Login erforderlich']);
            exit;
        }
    }
    
    public function updatePoints($userId, $points) {
        return $this->db->update('users', 
            ['points' => $points], 
            'id = :id', 
            ['id' => $userId]
        );
    }
    
    public function addPoints($userId, $amount, $reason = '') {
        $this->db->beginTransaction();
        
        try {
            // Update user points
            $this->db->query(
                "UPDATE users SET points = points + :amount WHERE id = :id",
                ['amount' => $amount, 'id' => $userId]
            );
            
            // Log transaction
            $this->db->insert('point_transactions', [
                'user_id' => $userId,
                'amount' => $amount,
                'type' => 'credit',
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function deductPoints($userId, $amount, $reason = '') {
        // Prüfe ob genug Punkte vorhanden
        $user = $this->db->fetchOne("SELECT points FROM users WHERE id = :id", ['id' => $userId]);
        
        if (!$user || $user['points'] < $amount) {
            throw new Exception("Nicht genügend Punkte verfügbar");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Update user points
            $this->db->query(
                "UPDATE users SET points = points - :amount WHERE id = :id",
                ['amount' => $amount, 'id' => $userId]
            );
            
            // Log transaction
            $this->db->insert('point_transactions', [
                'user_id' => $userId,
                'amount' => -$amount,
                'type' => 'debit',
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function checkRateLimit($action) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $timeLimit = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $attempts = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM rate_limits WHERE ip = :ip AND action = :action AND created_at > :time_limit",
            ['ip' => $ip, 'action' => $action, 'time_limit' => $timeLimit]
        );
        
        $maxAttempts = ($action === 'login') ? MAX_LOGIN_ATTEMPTS : 5;
        
        if ($attempts['count'] >= $maxAttempts) {
            throw new Exception("Zu viele Versuche. Bitte warten Sie eine Stunde.");
        }
        
        // Log den Versuch
        $this->db->insert('rate_limits', [
            'ip' => $ip,
            'action' => $action,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function logAction($action, $userId = null, $details = '') {
        $this->db->insert('user_logs', [
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>