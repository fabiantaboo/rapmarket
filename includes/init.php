<?php
/**
 * Initialisierung für RapMarket.de
 * Diese Datei wird in jeder PHP-Datei eingebunden
 */

// Error Reporting und Debugging
if (defined('APP_ENV') && APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Für APIs: Keine HTML-Ausgabe von Fehlern
if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Autoloader für Klassen
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Lade Konfiguration
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die('Konfigurationsdatei config.php nicht gefunden. Bitte kopiere config.example.php zu config.php und fülle die Werte aus.');
}

// Lade core includes
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// CORS für API
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . APP_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    exit(0);
}

// Content-Type für API
if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    header('Content-Type: application/json; charset=utf-8');
}

// Initialisiere globale Objekte
try {
    $db = Database::getInstance();
    $auth = new Auth();
} catch (Exception $e) {
    if (APP_ENV === 'development') {
        die('Initialisierungsfehler: ' . $e->getMessage());
    } else {
        die('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
    }
}

// Bereinige alte Sessions und Rate Limits
if (rand(1, 100) === 1) { // 1% Chance bei jedem Request
    cleanupOldData();
}

function cleanupOldData() {
    global $db;
    
    try {
        // Lösche alte Rate Limits (älter als 24 Stunden)
        $db->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        // Lösche alte Logs (älter als 30 Tage)
        $db->query("DELETE FROM user_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Lösche alte Sessions (älter als 7 Tage)
        $db->query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
    } catch (Exception $e) {
        error_log("Cleanup failed: " . $e->getMessage());
    }
}
?>