<?php
/**
 * Quick User Check Tool
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/logger.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>User Database Check</h2>";
    
    // Hole alle User
    $users = $db->fetchAll("SELECT id, username, email, is_active, created_at, login_count FROM users ORDER BY id DESC LIMIT 20");
    
    echo "<h3>Alle User (neueste 20):</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>ID</th>";
    echo "<th style='padding: 8px;'>Username</th>";
    echo "<th style='padding: 8px;'>Email</th>";
    echo "<th style='padding: 8px;'>Active</th>";
    echo "<th style='padding: 8px;'>Created</th>";
    echo "<th style='padding: 8px;'>Login Count</th>";
    echo "</tr>";
    
    foreach ($users as $user) {
        $bgColor = $user['is_active'] ? '#e8f5e8' : '#f8d7da';
        echo "<tr style='background: {$bgColor};'>";
        echo "<td style='padding: 8px;'>{$user['id']}</td>";
        echo "<td style='padding: 8px;'><strong>{$user['username']}</strong></td>";
        echo "<td style='padding: 8px;'>{$user['email']}</td>";
        echo "<td style='padding: 8px;'>" . ($user['is_active'] ? '✅ Aktiv' : '❌ Inaktiv') . "</td>";
        echo "<td style='padding: 8px;'>{$user['created_at']}</td>";
        echo "<td style='padding: 8px;'>{$user['login_count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test specific user search
    echo "<h3>Test User Search:</h3>";
    
    $testUsernames = ['fabianadmin', 'admin', 'test'];
    
    foreach ($testUsernames as $testUser) {
        echo "<h4>Suche nach '{$testUser}':</h4>";
        
        try {
            // Test exact username match
            $userByUsername = $db->fetchOne(
                "SELECT id, username, email, is_active FROM users WHERE username = :username",
                ['username' => $testUser]
            );
            
            // Test email match  
            $userByEmail = $db->fetchOne(
                "SELECT id, username, email, is_active FROM users WHERE email = :email",
                ['email' => $testUser]
            );
            
            // Test combined search like in auth
            $userCombined = $db->fetchOne(
                "SELECT id, username, email, is_active FROM users WHERE (username = :login OR email = :login) AND is_active = 1",
                ['login' => $testUser]
            );
            
            echo "<ul>";
            echo "<li><strong>Username-Suche:</strong> " . ($userByUsername ? "✅ Gefunden (ID: {$userByUsername['id']})" : "❌ Nicht gefunden") . "</li>";
            echo "<li><strong>Email-Suche:</strong> " . ($userByEmail ? "✅ Gefunden (ID: {$userByEmail['id']})" : "❌ Nicht gefunden") . "</li>";
            echo "<li><strong>Kombinierte Suche (wie in Auth):</strong> " . ($userCombined ? "✅ Gefunden (ID: {$userCombined['id']})" : "❌ Nicht gefunden") . "</li>";
            echo "</ul>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Fehler bei Suche: {$e->getMessage()}</p>";
        }
    }
    
    // Database connection test
    echo "<h3>Database Connection Test:</h3>";
    $testQuery = $db->fetchOne("SELECT COUNT(*) as total FROM users");
    echo "<p>✅ Verbindung OK - Gesamt User: {$testQuery['total']}</p>";
    
    // Check if fabianadmin exists with different variations
    echo "<h3>Detailsuche 'fabianadmin':</h3>";
    $variations = ['fabianadmin', 'FABIANADMIN', 'FabianAdmin'];
    
    foreach ($variations as $variation) {
        $found = $db->fetchOne("SELECT * FROM users WHERE username = :username", ['username' => $variation]);
        echo "<p><strong>{$variation}:</strong> " . ($found ? "✅ Gefunden" : "❌ Nicht gefunden") . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Fehler:</h3>";
    echo "<p>{$e->getMessage()}</p>";
    echo "<p>File: {$e->getFile()}:{$e->getLine()}</p>";
    
    Logger::logException($e, ['context' => 'check_users.php']);
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}
table {
    margin: 15px 0;
}
th, td {
    border: 1px solid #ddd;
}
h2, h3, h4 {
    color: #333;
}
</style>

<p><a href="view_logs.php">🔍 View Logs</a> | <a href="debug_frontend.html">🐛 Debug Frontend</a> | <a href="index.html">🏠 Zurück zur App</a></p>