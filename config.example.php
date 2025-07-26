<?php
/**
 * RapMarket.de Configuration Example
 * 
 * Kopiere diese Datei zu config.php und fülle die Werte aus
 */

// Datenbank Konfiguration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rapmarket');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');

// Anwendungs-Konfiguration
define('APP_NAME', 'RapMarket.de');
define('APP_URL', 'https://rapmarket.de');
define('APP_ENV', 'production'); // 'development' oder 'production'

// Sicherheit
define('JWT_SECRET', 'your-very-secure-jwt-secret-key-here');
define('PASSWORD_SALT', 'your-secure-password-salt-here');
define('SESSION_NAME', 'rapmarket_session');

// E-Mail Konfiguration (optional)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'your-email-password');
define('MAIL_FROM', 'noreply@rapmarket.de');
define('MAIL_FROM_NAME', 'RapMarket.de');

// API Konfiguration
define('API_RATE_LIMIT', 100); // Requests pro Stunde pro IP
define('MAX_LOGIN_ATTEMPTS', 5); // Pro IP pro Stunde

// Punkte System
define('STARTING_POINTS', 1000);
define('DAILY_BONUS_POINTS', 50);
define('MIN_BET_AMOUNT', 10);
define('MAX_BET_AMOUNT', 1000);

// Upload Konfiguration
define('UPLOAD_MAX_SIZE', 2097152); // 2MB in Bytes
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', 'uploads/');

// Debug und Logging
define('ENABLE_DEBUG', false);
define('LOG_LEVEL', 'ERROR'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', 'logs/app.log');

// Cache Konfiguration
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // Sekunden

// Social Media Integration (optional)
define('TWITTER_API_KEY', '');
define('TWITTER_API_SECRET', '');
define('INSTAGRAM_API_KEY', '');

// Externe APIs
define('SPOTIFY_CLIENT_ID', '');
define('SPOTIFY_CLIENT_SECRET', '');
define('YOUTUBE_API_KEY', '');

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Error Reporting basierend auf Environment
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>