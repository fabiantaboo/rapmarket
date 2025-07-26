<?php
/**
 * Admin-Setup Tool
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/logger.php';

$db = Database::getInstance();

echo "<h2>Admin Setup Tool</h2>";

// Zeige alle User
$users = $db->fetchAll("SELECT id, username, email, is_admin, created_at FROM users ORDER BY id DESC");

echo "<h3>Alle User:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th style='padding: 8px;'>ID</th>";
echo "<th style='padding: 8px;'>Username</th>";
echo "<th style='padding: 8px;'>Email</th>";
echo "<th style='padding: 8px;'>Admin Status</th>";
echo "<th style='padding: 8px;'>Aktion</th>";
echo "</tr>";

foreach ($users as $user) {
    $bgColor = $user['is_admin'] ? '#e8f5e8' : '#ffffff';
    echo "<tr style='background: {$bgColor};'>";
    echo "<td style='padding: 8px;'>{$user['id']}</td>";
    echo "<td style='padding: 8px;'><strong>{$user['username']}</strong></td>";
    echo "<td style='padding: 8px;'>{$user['email']}</td>";
    echo "<td style='padding: 8px;'>" . ($user['is_admin'] ? '✅ Admin' : '❌ Normaler User') . "</td>";
    echo "<td style='padding: 8px;'>";
    if (!$user['is_admin']) {
        echo "<a href='?make_admin={$user['id']}' onclick='return confirm(\"User {$user['username']} zum Admin machen?\")' style='color: green; text-decoration: none;'>👑 Admin machen</a>";
    } else {
        echo "<a href='?remove_admin={$user['id']}' onclick='return confirm(\"Admin-Rechte von {$user['username']} entfernen?\")' style='color: red; text-decoration: none;'>❌ Admin entfernen</a>";
    }
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

// Handle actions
if (isset($_GET['make_admin'])) {
    $userId = (int)$_GET['make_admin'];
    
    try {
        $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
        
        if ($user) {
            $db->update('users', ['is_admin' => 1], 'id = :id', ['id' => $userId]);
            
            Logger::logUserAction('admin_granted', $userId, ['granted_by' => 'make_admin_tool']);
            
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "✅ <strong>{$user['username']}</strong> wurde erfolgreich zum Admin gemacht!";
            echo "</div>";
            
            echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "❌ User nicht gefunden!";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "❌ Fehler: " . $e->getMessage();
        echo "</div>";
        
        Logger::logException($e, ['context' => 'make_admin', 'user_id' => $userId]);
    }
}

if (isset($_GET['remove_admin'])) {
    $userId = (int)$_GET['remove_admin'];
    
    try {
        $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
        
        if ($user) {
            $db->update('users', ['is_admin' => 0], 'id = :id', ['id' => $userId]);
            
            Logger::logUserAction('admin_removed', $userId, ['removed_by' => 'make_admin_tool']);
            
            echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "⚠️ Admin-Rechte von <strong>{$user['username']}</strong> wurden entfernt!";
            echo "</div>";
            
            echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "❌ User nicht gefunden!";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "❌ Fehler: " . $e->getMessage();
        echo "</div>";
        
        Logger::logException($e, ['context' => 'remove_admin', 'user_id' => $userId]);
    }
}

// Quick Admin Setup für 'fabianadmin'
$fabianAdmin = $db->fetchOne("SELECT * FROM users WHERE username = 'fabianadmin'");
if ($fabianAdmin && !$fabianAdmin['is_admin']) {
    echo "<div style='background: #cce5ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;'>";
    echo "<h3>🚀 Quick Setup</h3>";
    echo "<p>Der User <strong>fabianadmin</strong> wurde gefunden!</p>";
    echo "<p><a href='?make_admin={$fabianAdmin['id']}' class='btn btn-primary' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>👑 fabianadmin zum Admin machen</a></p>";
    echo "</div>";
}

echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;'>";
echo "<h3>ℹ️ Hinweise:</h3>";
echo "<ul>";
echo "<li><strong>Admin-Zugang:</strong> Nur Admins können auf <code>admin.php</code> zugreifen</li>";
echo "<li><strong>Event-Management:</strong> Admins können Events erstellen, bearbeiten und löschen</li>";
echo "<li><strong>Sicherheit:</strong> Admin-Aktionen werden geloggt</li>";
echo "<li><strong>Empfehlung:</strong> Verwende einen dedizierten Admin-Account</li>";
echo "</ul>";
echo "</div>";
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
h2, h3 {
    color: #333;
}
</style>

<p style="margin-top: 30px;">
    <a href="admin.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🏠 Zum Admin Backend</a>
    <a href="index.html" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">🏠 Zur Hauptseite</a>
    <a href="view_logs.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">📋 Logs anzeigen</a>
</p>