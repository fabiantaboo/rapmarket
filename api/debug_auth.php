<?php
/**
 * Debug API für Auth-Probleme
 */

// Error reporting aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>RapMarket Auth Debug</h3>";

try {
    echo "<p>1. Config laden...</p>";
    require_once '../config.php';
    echo "<p>✅ Config geladen</p>";
    
    echo "<p>2. Database-Klasse laden...</p>";
    require_once '../includes/database.php';
    echo "<p>✅ Database-Klasse geladen</p>";
    
    echo "<p>3. Functions laden...</p>";
    require_once '../includes/functions.php';
    echo "<p>✅ Functions geladen</p>";
    
    echo "<p>4. Auth-Klasse laden...</p>";
    require_once '../includes/auth.php';
    echo "<p>✅ Auth-Klasse geladen</p>";
    
    echo "<p>5. Datenbankverbindung testen...</p>";
    $db = Database::getInstance();
    echo "<p>✅ Datenbankverbindung erfolgreich</p>";
    
    echo "<p>6. Tabellen prüfen...</p>";
    $tables = ['users', 'rate_limits', 'user_logs'];
    foreach ($tables as $table) {
        if ($db->tableExists($table)) {
            echo "<p>✅ Tabelle '$table' existiert</p>";
        } else {
            echo "<p>❌ Tabelle '$table' fehlt!</p>";
        }
    }
    
    echo "<p>7. User-Count prüfen...</p>";
    $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    echo "<p>✅ Users in DB: " . $userCount['count'] . "</p>";
    
    echo "<p>8. Auth-Klasse instanziieren...</p>";
    $auth = new Auth();
    echo "<p>✅ Auth-Klasse initialisiert</p>";
    
    echo "<p>9. Test-Login versuchen...</p>";
    
    // Prüfe ob Admin existiert
    $admin = $db->fetchOne("SELECT * FROM users WHERE is_admin = 1 LIMIT 1");
    if ($admin) {
        echo "<p>✅ Admin-User gefunden: " . $admin['username'] . "</p>";
        echo "<p>Admin-Daten: <pre>" . print_r($admin, true) . "</pre></p>";
    } else {
        echo "<p>❌ Kein Admin-User gefunden!</p>";
    }
    
    echo "<p>10. Simuliere Login-Request...</p>";
    
    if ($_POST) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        echo "<p>Login-Versuch für: $username</p>";
        
        try {
            $user = $auth->login($username, $password);
            echo "<p>✅ Login erfolgreich!</p>";
            echo "<p>User-Daten: <pre>" . print_r($user, true) . "</pre></p>";
        } catch (Exception $e) {
            echo "<p>❌ Login-Fehler: " . $e->getMessage() . "</p>";
            echo "<p>Stack Trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ FEHLER: " . $e->getMessage() . "</p>";
    echo "<p>Datei: " . $e->getFile() . " Zeile: " . $e->getLine() . "</p>";
    echo "<p>Stack Trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
}

?>

<form method="POST" style="margin-top: 30px; padding: 20px; border: 1px solid #ccc;">
    <h4>Test-Login</h4>
    <p>
        <label>Username:</label><br>
        <input type="text" name="username" value="admin" style="width: 200px; padding: 5px;">
    </p>
    <p>
        <label>Password:</label><br>
        <input type="password" name="password" value="" style="width: 200px; padding: 5px;">
    </p>
    <p>
        <button type="submit" style="padding: 10px 20px; background: #007cba; color: white; border: none;">
            Test Login
        </button>
    </p>
</form>

<hr>
<h4>Server-Info</h4>
<p>PHP Version: <?= phpversion() ?></p>
<p>MySQL verfügbar: <?= extension_loaded('pdo_mysql') ? 'Ja' : 'Nein' ?></p>
<p>Current Directory: <?= getcwd() ?></p>
<p>Database Name: <?= defined('DB_NAME') ? DB_NAME : 'Nicht definiert' ?></p>
<p>Database Host: <?= defined('DB_HOST') ? DB_HOST : 'Nicht definiert' ?></p>