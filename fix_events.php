<?php
/**
 * Fix bestehende Events - Setze start_date und end_date
 */
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/logger.php';

$db = Database::getInstance();

echo "<h2>Event-Fix Tool</h2>";

try {
    // Hole alle Events ohne start_date
    $events = $db->fetchAll("SELECT * FROM events WHERE start_date IS NULL OR start_date = '0000-00-00 00:00:00'");
    
    echo "<h3>Events die repariert werden müssen: " . count($events) . "</h3>";
    
    if (empty($events)) {
        echo "<p style='color: green;'>✅ Alle Events haben bereits gültige Daten!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Titel</th>";
        echo "<th style='padding: 8px;'>Status</th>";
        echo "<th style='padding: 8px;'>Aktion</th>";
        echo "</tr>";
        
        foreach ($events as $event) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$event['id']}</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($event['title']) . "</td>";
            echo "<td style='padding: 8px;'>{$event['status']}</td>";
            echo "<td style='padding: 8px;'>";
            echo "<a href='?fix_event={$event['id']}' style='color: green;'>🔧 Reparieren</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Handle fix action
    if (isset($_GET['fix_event'])) {
        $eventId = (int)$_GET['fix_event'];
        
        // Setze Standard-Daten für das Event
        $startDate = date('Y-m-d H:i:s', strtotime('+1 day')); // Morgen
        $endDate = date('Y-m-d H:i:s', strtotime('+1 week')); // In einer Woche
        
        $result = $db->update('events', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ], 'id = :id', ['id' => $eventId]);
        
        if ($result) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "✅ Event {$eventId} wurde erfolgreich repariert!";
            echo "<br>Start-Datum: {$startDate}";
            echo "<br>End-Datum: {$endDate}";
            echo "</div>";
            
            Logger::logUserAction('fix_event', null, [
                'event_id' => $eventId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "❌ Fehler beim Reparieren von Event {$eventId}";
            echo "</div>";
        }
    }
    
    // Batch fix all events
    if (isset($_GET['fix_all'])) {
        $fixedCount = 0;
        
        foreach ($events as $event) {
            $startDate = date('Y-m-d H:i:s', strtotime('+' . $fixedCount . ' days'));
            $endDate = date('Y-m-d H:i:s', strtotime($startDate . ' +1 week'));
            
            $result = $db->update('events', [
                'start_date' => $startDate,
                'end_date' => $endDate
            ], 'id = :id', ['id' => $event['id']]);
            
            if ($result) {
                $fixedCount++;
            }
        }
        
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "✅ {$fixedCount} Events wurden erfolgreich repariert!";
        echo "</div>";
        
        Logger::logUserAction('fix_all_events', null, ['fixed_count' => $fixedCount]);
        
        echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
    }
    
    if (!empty($events)) {
        echo "<div style='margin: 20px 0;'>";
        echo "<a href='?fix_all=1' onclick='return confirm(\"Alle Events reparieren?\")' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Alle Events reparieren</a>";
        echo "</div>";
    }
    
    // Zeige alle Events mit ihren Daten
    echo "<h3>Alle Events Übersicht:</h3>";
    $allEvents = $db->fetchAll("SELECT * FROM events ORDER BY id DESC");
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>ID</th>";
    echo "<th style='padding: 8px;'>Titel</th>";
    echo "<th style='padding: 8px;'>Start-Datum</th>";
    echo "<th style='padding: 8px;'>End-Datum</th>";
    echo "<th style='padding: 8px;'>Status</th>";
    echo "</tr>";
    
    foreach ($allEvents as $event) {
        $bgColor = ($event['start_date'] && $event['start_date'] !== '0000-00-00 00:00:00') ? '#e8f5e8' : '#f8d7da';
        echo "<tr style='background: {$bgColor};'>";
        echo "<td style='padding: 8px;'>{$event['id']}</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($event['title']) . "</td>";
        echo "<td style='padding: 8px;'>" . ($event['start_date'] ? date('d.m.Y H:i', strtotime($event['start_date'])) : '❌ Kein Datum') . "</td>";
        echo "<td style='padding: 8px;'>" . ($event['end_date'] ? date('d.m.Y H:i', strtotime($event['end_date'])) : '❌ Kein Datum') . "</td>";
        echo "<td style='padding: 8px;'>{$event['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "❌ Fehler: " . $e->getMessage();
    echo "</div>";
    
    Logger::logException($e, ['context' => 'fix_events']);
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
h2, h3 {
    color: #333;
}
</style>

<p style="margin-top: 30px;">
    <a href="admin.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🏠 Zum Admin Backend</a>
    <a href="index.html" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">🏠 Zur Hauptseite</a>
</p>