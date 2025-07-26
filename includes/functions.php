<?php
/**
 * Hilfsfunktionen für RapMarket.de
 */

/**
 * Sichere Ausgabe von Daten (XSS-Schutz)
 */
function escape($data) {
    if (is_array($data)) {
        return array_map('escape', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * JSON Response senden
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Fehler-Response senden
 */
function sendErrorResponse($message, $statusCode = 400) {
    sendJsonResponse(['error' => $message], $statusCode);
}

/**
 * Erfolgs-Response senden
 */
function sendSuccessResponse($data = [], $message = 'Erfolgreich') {
    sendJsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

/**
 * Validiere E-Mail-Adresse
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validiere Username
 */
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

/**
 * Generiere sicheres Token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Formatiere Datum für deutsche Ausgabe
 */
function formatDate($date, $format = 'd.m.Y H:i') {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    return $date->format($format);
}

/**
 * Berechne Zeit seit Datum
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'gerade eben';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "vor {$minutes} " . ($minutes == 1 ? 'Minute' : 'Minuten');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "vor {$hours} " . ($hours == 1 ? 'Stunde' : 'Stunden');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return "vor {$days} " . ($days == 1 ? 'Tag' : 'Tagen');
    } else {
        return formatDate($datetime, 'd.m.Y');
    }
}

/**
 * Formatiere Punkte mit Tausender-Trennzeichen
 */
function formatPoints($points) {
    return number_format($points, 0, ',', '.');
}

/**
 * Validiere Bet-Amount
 */
function isValidBetAmount($amount, $userPoints) {
    return is_numeric($amount) && 
           $amount >= MIN_BET_AMOUNT && 
           $amount <= MAX_BET_AMOUNT && 
           $amount <= $userPoints;
}

/**
 * Log-Nachricht schreiben
 */
function writeLog($level, $message, $context = []) {
    if (!ENABLE_DEBUG && $level === 'DEBUG') {
        return;
    }
    
    $logLevels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    $currentLogLevel = $logLevels[LOG_LEVEL] ?? 3;
    
    if ($logLevels[$level] < $currentLogLevel) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextString = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] {$level}: {$message}{$contextString}" . PHP_EOL;
    
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Rate Limiting prüfen
 */
function checkApiRateLimit($identifier = null) {
    if ($identifier === null) {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    $cacheKey = "rate_limit_{$identifier}";
    $requests = apcu_fetch($cacheKey) ?: 0;
    
    if ($requests >= API_RATE_LIMIT) {
        sendErrorResponse('Rate limit exceeded', 429);
    }
    
    apcu_store($cacheKey, $requests + 1, 3600); // 1 Stunde
}

/**
 * Validiere CSRF Token
 */
function validateCSRF() {
    global $auth;
    
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!$auth->validateCSRFToken($token)) {
        sendErrorResponse('Ungültiges CSRF Token', 403);
    }
}

/**
 * Bereinige User Input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    return trim(strip_tags($input));
}

/**
 * Prüfe ob Request über AJAX kam
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Redirect mit Sicherheit
 */
function safeRedirect($url, $statusCode = 302) {
    // Nur interne URLs erlauben
    if (!filter_var($url, FILTER_VALIDATE_URL) || 
        parse_url($url, PHP_URL_HOST) !== parse_url(APP_URL, PHP_URL_HOST)) {
        $url = APP_URL;
    }
    
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * Berechne Gewinn basierend auf Quote
 */
function calculateWinnings($betAmount, $odds) {
    return floor($betAmount * $odds);
}

/**
 * Prüfe ob Event noch aktiv ist
 */
function isEventActive($eventEndTime) {
    return strtotime($eventEndTime) > time();
}

/**
 * Generiere sichere Datei-Namen
 */
function generateSafeFilename($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
    $filename = substr($filename, 0, 50); // Länge begrenzen
    
    return $filename . '_' . time() . '.' . $extension;
}

/**
 * Validiere Upload
 */
function validateUpload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload-Fehler aufgetreten');
    }
    
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new Exception('Datei zu groß (max. ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB)');
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, UPLOAD_ALLOWED_TYPES)) {
        throw new Exception('Dateityp nicht erlaubt');
    }
    
    // MIME-Type prüfen
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    if ($allowedMimes[$extension] !== $mimeType) {
        throw new Exception('Ungültiger Dateityp');
    }
    
    return true;
}

/**
 * Debug-Ausgabe (nur in Development)
 */
function debug($data) {
    if (APP_ENV === 'development') {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
    }
}
?>