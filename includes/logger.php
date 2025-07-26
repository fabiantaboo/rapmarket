<?php
/**
 * Detailliertes Logging System für RapMarket.de
 */

class Logger {
    private static $logFile;
    private static $errorLogFile;
    private static $apiLogFile;
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        $baseDir = dirname(__DIR__);
        $logDir = $baseDir . '/logs';
        
        // Erstelle logs Verzeichnis falls nicht vorhanden
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/app.log';
        self::$errorLogFile = $logDir . '/error.log';
        self::$apiLogFile = $logDir . '/api.log';
        
        // PHP Error Log auf unsere Datei setzen
        ini_set('log_errors', 1);
        ini_set('error_log', self::$errorLogFile);
        
        self::$initialized = true;
    }
    
    public static function info($message, $context = []) {
        self::writeLog('INFO', $message, $context, self::$logFile);
    }
    
    public static function error($message, $context = []) {
        self::writeLog('ERROR', $message, $context, self::$errorLogFile);
    }
    
    public static function warning($message, $context = []) {
        self::writeLog('WARNING', $message, $context, self::$logFile);
    }
    
    public static function debug($message, $context = []) {
        self::writeLog('DEBUG', $message, $context, self::$logFile);
    }
    
    public static function api($action, $message, $context = []) {
        $fullMessage = "[{$action}] {$message}";
        self::writeLog('API', $fullMessage, $context, self::$apiLogFile);
    }
    
    private static function writeLog($level, $message, $context, $file) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 100),
            'request_uri' => $requestUri,
            'context' => $context
        ];
        
        // Formatiere Log Entry
        $formattedEntry = sprintf(
            "[%s] %s: %s | IP: %s | URI: %s",
            $timestamp,
            $level,
            $message,
            $ip,
            $requestUri
        );
        
        if (!empty($context)) {
            $formattedEntry .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $formattedEntry .= "\n";
        
        // Schreibe in Datei
        file_put_contents($file, $formattedEntry, FILE_APPEND | LOCK_EX);
        
        // Zusätzlich in PHP error_log für kritische Errors
        if ($level === 'ERROR') {
            error_log($message);
        }
    }
    
    public static function logException(Exception $e, $context = []) {
        $message = sprintf(
            "Exception: %s in %s:%d",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        
        $context['exception'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
        
        self::error($message, $context);
    }
    
    public static function logApiRequest($endpoint, $method, $data = [], $response = null) {
        $context = [
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => $data,
            'response' => $response
        ];
        
        self::api($method, "Request to {$endpoint}", $context);
    }
    
    public static function logUserAction($action, $userId = null, $details = []) {
        $context = [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'session_id' => session_id()
        ];
        
        self::info("User action: {$action}", $context);
    }
    
    public static function getLogFiles() {
        self::init();
        return [
            'app' => self::$logFile,
            'error' => self::$errorLogFile,
            'api' => self::$apiLogFile
        ];
    }
    
    public static function clearLogs() {
        self::init();
        $files = [self::$logFile, self::$errorLogFile, self::$apiLogFile];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                file_put_contents($file, '');
            }
        }
    }
}

// Auto-Initialize
Logger::init();
?>