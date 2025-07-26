<?php
/**
 * Fix-Script für fehlende Tabellen
 */

// Error reporting aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>RapMarket - Fehlende Tabellen erstellen</h3>";

try {
    require_once '../config.php';
    require_once '../includes/database.php';
    
    // Define writeLog function if not exists
    if (!function_exists('writeLog')) {
        function writeLog($level, $message, $context = []) {
            error_log("[$level] $message");
        }
    }
    
    $db = Database::getInstance();
    
    echo "<p>✅ Datenbankverbindung erfolgreich</p>";
    
    // Prüfe welche Tabellen fehlen
    $requiredTables = [
        'rate_limits' => "
            CREATE TABLE rate_limits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ip VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_ip_action (ip, action),
                INDEX idx_ip (ip),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;
        ",
        
        'user_logs' => "
            CREATE TABLE user_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NULL,
                action VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                details TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_ip (ip_address),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;
        ",
        
        'point_transactions' => "
            CREATE TABLE point_transactions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                amount INT NOT NULL,
                type ENUM('credit', 'debit') NOT NULL,
                reason VARCHAR(255) NOT NULL,
                reference_id INT NULL,
                reference_type VARCHAR(50) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_type (type),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;
        "
    ];
    
    $createdTables = [];
    $skippedTables = [];
    
    foreach ($requiredTables as $tableName => $sql) {
        if ($db->tableExists($tableName)) {
            echo "<p>✅ Tabelle '$tableName' existiert bereits</p>";
            $skippedTables[] = $tableName;
        } else {
            echo "<p>🔧 Erstelle Tabelle '$tableName'...</p>";
            try {
                $db->query($sql);
                echo "<p>✅ Tabelle '$tableName' erfolgreich erstellt</p>";
                $createdTables[] = $tableName;
            } catch (Exception $e) {
                echo "<p>❌ Fehler beim Erstellen von '$tableName': " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h4>Zusammenfassung:</h4>";
    echo "<p><strong>Erstellt:</strong> " . (count($createdTables) > 0 ? implode(', ', $createdTables) : 'Keine') . "</p>";
    echo "<p><strong>Bereits vorhanden:</strong> " . (count($skippedTables) > 0 ? implode(', ', $skippedTables) : 'Keine') . "</p>";
    
    if (count($createdTables) > 0) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h5>✅ Erfolgreich!</h5>";
        echo "<p>Die fehlenden Tabellen wurden erstellt. Du kannst jetzt das Login testen:</p>";
        echo "<p><a href='../index.html' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Zur Website</a></p>";
        echo "</div>";
    }
    
    // Test der neuen Tabellen
    echo "<h4>Test der Tabellen:</h4>";
    foreach (['rate_limits', 'user_logs', 'point_transactions'] as $table) {
        if ($db->tableExists($table)) {
            echo "<p>✅ $table - funktionsfähig</p>";
        } else {
            echo "<p>❌ $table - fehlt noch</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ FEHLER: " . $e->getMessage() . "</p>";
    echo "<p>Datei: " . $e->getFile() . " Zeile: " . $e->getLine() . "</p>";
    echo "<p>Stack Trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h3 { color: #007cba; }
h4 { color: #333; margin-top: 30px; }
p { margin: 5px 0; }
hr { margin: 30px 0; }
</style>