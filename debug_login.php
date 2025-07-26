<?php
/**
 * Login Debug Tool
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$db = Database::getInstance();

echo "<h2>Login Debug</h2>";

// Zeige alle User
echo "<h3>Alle User in der Datenbank:</h3>";
$users = $db->fetchAll("SELECT id, username, email, is_active, created_at FROM users ORDER BY id DESC LIMIT 10");

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Active</th><th>Created</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['is_active']}</td>";
    echo "<td>{$user['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test Login für letzten User
if (!empty($users)) {
    $lastUser = $users[0];
    echo "<h3>Test Login für User: {$lastUser['username']}</h3>";
    
    // Prüfe Password Hash
    $fullUser = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $lastUser['id']]);
    
    echo "<p><strong>User gefunden:</strong> " . ($fullUser ? 'Ja' : 'Nein') . "</p>";
    echo "<p><strong>Password Hash:</strong> " . substr($fullUser['password'], 0, 30) . "...</p>";
    
    // Test verschiedene Passwörter
    $testPasswords = ['test123', 'testpass', 'password', 'admin'];
    
    foreach ($testPasswords as $testPass) {
        $isValid = password_verify($testPass, $fullUser['password']);
        echo "<p><strong>Passwort '{$testPass}':</strong> " . ($isValid ? 'GÜLTIG ✓' : 'Ungültig ✗') . "</p>";
    }
    
    // Test DB Query wie in auth_v2.php
    echo "<h3>Test DB Query für Login:</h3>";
    $testUser = $db->fetchOne(
        "SELECT * FROM users WHERE (username = :login OR email = :login) AND is_active = 1",
        ['login' => $lastUser['username']]
    );
    
    echo "<p><strong>User gefunden mit Query:</strong> " . ($testUser ? 'Ja' : 'Nein') . "</p>";
    if ($testUser) {
        echo "<p><strong>User ID:</strong> {$testUser['id']}</p>";
        echo "<p><strong>Username:</strong> {$testUser['username']}</p>";
        echo "<p><strong>Email:</strong> {$testUser['email']}</p>";
        echo "<p><strong>Active:</strong> {$testUser['is_active']}</p>";
    }
}

// Test mit POST Request Simulation
echo "<h3>Test Login API Simulation:</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    echo "<p><strong>Test Login für:</strong> {$username}</p>";
    
    // Hole User
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE (username = :login OR email = :login) AND is_active = 1",
        ['login' => $username]
    );
    
    if (!$user) {
        echo "<p style='color: red;'>❌ User nicht gefunden!</p>";
    } else {
        echo "<p style='color: green;'>✅ User gefunden: {$user['username']}</p>";
        
        if (!password_verify($password, $user['password'])) {
            echo "<p style='color: red;'>❌ Passwort falsch!</p>";
            
            // Debug: Zeige mehr Details
            echo "<p><strong>Eingegebenes Passwort:</strong> '{$password}'</p>";
            echo "<p><strong>Hash in DB:</strong> " . substr($user['password'], 0, 60) . "...</p>";
            
            // Test: Erstelle neuen Hash
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            echo "<p><strong>Neuer Hash für Passwort:</strong> " . substr($newHash, 0, 60) . "...</p>";
            echo "<p><strong>Verify mit neuem Hash:</strong> " . (password_verify($password, $newHash) ? 'OK' : 'FEHLER') . "</p>";
            
        } else {
            echo "<p style='color: green;'>✅ Passwort korrekt!</p>";
            echo "<p style='color: green;'>🎉 Login wäre erfolgreich!</p>";
        }
    }
}
?>

<form method="POST">
    <h3>Test Login Form:</h3>
    <p>Username: <input type="text" name="username" value="<?= !empty($users) ? $users[0]['username'] : '' ?>" /></p>
    <p>Passwort: <input type="text" name="password" value="test123" /></p>
    <p><input type="submit" name="test_login" value="Test Login" /></p>
</form>

<style>
body { font-family: Arial; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
</style>