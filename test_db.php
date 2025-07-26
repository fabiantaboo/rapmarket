<?php
/**
 * Database Connection Test Tool
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

try {
    echo "<h3>1. Config laden...</h3>";
    require_once 'config.php';
    echo "✅ Config geladen<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
    echo "DB_PASS: " . (strlen(DB_PASS) > 0 ? str_repeat('*', strlen(DB_PASS)) : 'LEER') . "<br><br>";

    echo "<h3>2. Direkte PDO-Verbindung testen...</h3>";
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "✅ Direkte PDO-Verbindung erfolgreich<br><br>";

    echo "<h3>3. Test einfache Abfrage...</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✅ Simple Query erfolgreich - User Count: " . $result['total'] . "<br><br>";

    echo "<h3>4. Test parametrisierte Abfrage...</h3>";
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = :username");
    $stmt->execute(['username' => 'fabianadmin']);
    $user = $stmt->fetch();
    echo "✅ Parametrisierte Query erfolgreich<br>";
    if ($user) {
        echo "User gefunden: ID=" . $user['id'] . ", Username=" . $user['username'] . "<br>";
    } else {
        echo "User 'fabianadmin' nicht gefunden<br>";
    }
    echo "<br>";

    echo "<h3>5. Test Database-Klasse...</h3>";
    require_once 'includes/database.php';
    $db = Database::getInstance();
    echo "✅ Database-Klasse instanziiert<br>";

    $users = $db->fetchAll("SELECT id, username, email FROM users LIMIT 5");
    echo "✅ fetchAll erfolgreich - " . count($users) . " User gefunden<br>";
    
    foreach ($users as $user) {
        echo "- ID: {$user['id']}, Username: {$user['username']}<br>";
    }
    echo "<br>";

    echo "<h3>6. Test spezifische User-Suche...</h3>";
    $testUser = $db->fetchOne("SELECT * FROM users WHERE username = :username", ['username' => 'fabianadmin']);
    if ($testUser) {
        echo "✅ User 'fabianadmin' gefunden:<br>";
        echo "- ID: {$testUser['id']}<br>";
        echo "- Username: {$testUser['username']}<br>";
        echo "- Email: {$testUser['email']}<br>";
        echo "- Active: {$testUser['is_active']}<br>";
        echo "- Created: {$testUser['created_at']}<br>";
    } else {
        echo "❌ User 'fabianadmin' nicht gefunden<br>";
        
        // Suche alle User die mit 'fabian' beginnen
        echo "<br>Suche nach Usern die mit 'fabian' beginnen:<br>";
        $similarUsers = $db->fetchAll("SELECT username FROM users WHERE username LIKE 'fabian%'");
        foreach ($similarUsers as $similarUser) {
            echo "- {$similarUser['username']}<br>";
        }
    }

    echo "<br><h3>7. Test OR-Abfrage (wie im Auth)...</h3>";
    $authQuery = "SELECT * FROM users WHERE (username = :username OR email = :email) AND is_active = 1";
    $authUser = $db->fetchOne($authQuery, ['username' => 'fabianadmin', 'email' => 'fabianadmin']);
    
    if ($authUser) {
        echo "✅ OR-Query erfolgreich - User gefunden<br>";
    } else {
        echo "❌ OR-Query - User nicht gefunden<br>";
        
        // Debug: Teste beide Bedingungen separat
        $userByUsername = $db->fetchOne("SELECT * FROM users WHERE username = :username", ['username' => 'fabianadmin']);
        $userByEmail = $db->fetchOne("SELECT * FROM users WHERE email = :email", ['email' => 'fabianadmin']);
        
        echo "Debug - Username match: " . ($userByUsername ? "✅" : "❌") . "<br>";
        echo "Debug - Email match: " . ($userByEmail ? "✅" : "❌") . "<br>";
    }

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Fehler:</h3>";
    echo "<p><strong>Message:</strong> {$e->getMessage()}</p>";
    echo "<p><strong>File:</strong> {$e->getFile()}:{$e->getLine()}</p>";
    echo "<p><strong>Trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}
h2, h3 {
    color: #333;
}
pre {
    background: #f4f4f4;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
}
</style>

<p><a href="check_users.php">👥 Check Users</a> | <a href="view_logs.php">📋 View Logs</a> | <a href="index.html">🏠 Zurück zur App</a></p>