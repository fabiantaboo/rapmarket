<?php
// Setup Complete Step
session_start();

if (!isset($_SESSION['setup_admin_created'])) {
    header('Location: ?step=admin');
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'admin';

// Clean up setup session data
unset($_SESSION['setup_db_configured']);
unset($_SESSION['setup_admin_created']);
unset($_SESSION['admin_username']);

// Get some stats for display
try {
    require_once '../config.php';
    require_once '../includes/database.php';
    
    $db = Database::getInstance();
    $tableCount = $db->query("SHOW TABLES")->rowCount();
    $sampleEvents = $db->query("SELECT COUNT(*) as count FROM events")->fetch()['count'];
    $dbVersion = $db->getVersion();
    $dbName = $db->getDatabaseName();
    
} catch (Exception $e) {
    $tableCount = 'Unbekannt';
    $sampleEvents = 'Unbekannt';
    $dbVersion = 'Unbekannt';
    $dbName = 'Unbekannt';
}
?>

<div class="text-center">
    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
    <h3>🎉 Setup erfolgreich abgeschlossen!</h3>
    <p class="lead">RapMarket.de ist jetzt einsatzbereit!</p>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card bg-dark border-success">
            <div class="card-header bg-success">
                <i class="fas fa-database me-2"></i>Datenbank
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li><i class="fas fa-check text-success me-2"></i>Datenbank: <strong><?= htmlspecialchars($dbName) ?></strong></li>
                    <li><i class="fas fa-check text-success me-2"></i>Version: <?= htmlspecialchars($dbVersion) ?></li>
                    <li><i class="fas fa-check text-success me-2"></i>Tabellen: <?= $tableCount ?> erstellt</li>
                    <li><i class="fas fa-check text-success me-2"></i>Sample Events: <?= $sampleEvents ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-dark border-info">
            <div class="card-header bg-info">
                <i class="fas fa-user-shield me-2"></i>Administrator
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li><i class="fas fa-check text-success me-2"></i>Username: <strong><?= htmlspecialchars($adminUsername) ?></strong></li>
                    <li><i class="fas fa-check text-success me-2"></i>Startpunkte: 10.000</li>
                    <li><i class="fas fa-check text-success me-2"></i>Admin-Rechte: Aktiv</li>
                    <li><i class="fas fa-check text-success me-2"></i>Account: Verifiziert</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-success mt-4">
    <i class="fas fa-lightbulb me-2"></i>
    <strong>Was du jetzt tun kannst:</strong><br>
    • Logge dich mit deinem Admin-Account ein<br>
    • Erstelle neue Events für die Community<br>
    • Teste das Wett-System mit den Sample-Events<br>
    • Lade Freunde ein und baue deine Community auf
</div>

<div class="alert alert-warning">
    <i class="fas fa-shield-alt me-2"></i>
    <strong>Sicherheitshinweis:</strong><br>
    • Lösche das <code>setup/</code> Verzeichnis für zusätzliche Sicherheit<br>
    • Die <code>config.php</code> enthält sensible Daten und ist durch .gitignore geschützt<br>
    • Erstelle regelmäßige Backups deiner Datenbank
</div>

<div class="text-center mt-4">
    <a href="../index.html" class="btn btn-primary btn-lg me-3">
        <i class="fas fa-home me-2"></i>Zur Website
    </a>
    <a href="../" class="btn btn-outline-info btn-lg">
        <i class="fas fa-sign-in-alt me-2"></i>Jetzt einloggen
    </a>
</div>

<div class="mt-5 pt-4 border-top">
    <h6><i class="fas fa-info-circle me-2"></i>Nützliche Informationen:</h6>
    <div class="row">
        <div class="col-md-4">
            <small>
                <strong>Konfiguration:</strong><br>
                <code>config.php</code> - Hauptkonfiguration<br>
                <code>database.sql</code> - SQL Schema
            </small>
        </div>
        <div class="col-md-4">
            <small>
                <strong>API Endpoints:</strong><br>
                <code>/api/auth.php</code> - Authentifizierung<br>
                <code>/api/events.php</code> - Events & Wetten<br>
                <code>/api/leaderboard.php</code> - Rangliste
            </small>
        </div>
        <div class="col-md-4">
            <small>
                <strong>Log-Dateien:</strong><br>
                <code>/logs/app.log</code> - Anwendungs-Logs<br>
                PHP Error Log - Server-Logs
            </small>
        </div>
    </div>
</div>

<script>
// Auto-cleanup setup files after 30 seconds (optional)
setTimeout(function() {
    if (confirm('Möchtest du das Setup-Verzeichnis jetzt löschen? (Empfohlen für Sicherheit)')) {
        fetch('../api/cleanup_setup.php', {method: 'POST'})
            .then(() => {
                alert('Setup-Verzeichnis wurde gelöscht.');
                window.location.href = '../index.html';
            })
            .catch(() => {
                alert('Setup-Verzeichnis konnte nicht automatisch gelöscht werden. Bitte lösche es manuell.');
            });
    }
}, 30000);
</script>