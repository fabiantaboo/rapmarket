<?php
/**
 * Datenbank-Verbindung für RapMarket.de
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Erste Verbindung ohne Datenbank um sie zu erstellen
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $tempConnection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Datenbank erstellen falls sie nicht existiert
            $tempConnection->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tempConnection = null;
            
            // Verbindung zur erstellten Datenbank
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ]);
            
            // Prüfe ob Tabellen existieren und erstelle sie bei Bedarf
            $this->checkAndCreateTables();
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new PDOException("Statement preparation failed");
            }
            $result = $stmt->execute($params);
            if (!$result) {
                throw new PDOException("Statement execution failed");
            }
            return $stmt;
        } catch (PDOException $e) {
            $errorMsg = "Database query failed: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params);
            error_log($errorMsg);
            
            // Include logger if available
            if (class_exists('Logger')) {
                Logger::error('Database query error', [
                    'sql' => $sql,
                    'params' => $params,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            }
            
            throw new Exception("Datenbankabfrage fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $stmt = $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $field) {
            $setClause[] = "{$field} = :{$field}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    private function checkAndCreateTables() {
        // Prüfe kritische Tabellen
        $requiredTables = ['users', 'events', 'event_options', 'bets', 'rate_limits', 'user_logs', 'point_transactions'];
        $missingTables = [];
        
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            // Mindestens eine Tabelle fehlt, erstelle alle
            $this->createTables();
        }
    }
    
    private function createTables() {
        $sql = file_get_contents(__DIR__ . '/../database.sql');
        
        if ($sql === false) {
            throw new Exception("Konnte database.sql nicht lesen");
        }
        
        // Entferne CREATE DATABASE und USE Befehle
        $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
        $sql = preg_replace('/USE.*?;/i', '', $sql);
        
        // Teile SQL in einzelne Statements
        $statements = $this->splitSqlStatements($sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                try {
                    $this->connection->exec($statement);
                } catch (PDOException $e) {
                    // Ignoriere bereits existierende Tabellen
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        error_log("SQL Error: " . $e->getMessage() . " in statement: " . substr($statement, 0, 100));
                    }
                }
            }
        }
        
        if (function_exists('writeLog')) {
            writeLog('INFO', 'Database tables created automatically');
        } else {
            error_log('Database tables created automatically');
        }
    }
    
    private function splitSqlStatements($sql) {
        $statements = [];
        $current = '';
        $inDelimiter = false;
        $delimiter = ';';
        
        $lines = explode("\n", $sql);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments
            if (empty($line) || substr($line, 0, 2) === '--' || substr($line, 0, 1) === '#') {
                continue;
            }
            
            // Handle DELIMITER changes
            if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
                $delimiter = trim($matches[1]);
                $inDelimiter = true;
                continue;
            }
            
            $current .= $line . "\n";
            
            // Check for statement end
            if (substr(rtrim($line), -strlen($delimiter)) === $delimiter) {
                if ($delimiter === '$$' && $inDelimiter) {
                    // End of stored procedure/function/trigger
                    $statements[] = substr($current, 0, -strlen($delimiter));
                    $current = '';
                    $delimiter = ';';
                    $inDelimiter = false;
                } elseif ($delimiter === ';') {
                    $statements[] = substr($current, 0, -2); // Remove ;\n
                    $current = '';
                }
            }
        }
        
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        
        return $statements;
    }
    
    public function tableExists($tableName) {
        $result = $this->connection->query("SHOW TABLES LIKE '{$tableName}'")->rowCount();
        return $result > 0;
    }
    
    public function getVersion() {
        return $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
    
    public function getDatabaseName() {
        return DB_NAME;
    }
}
?>