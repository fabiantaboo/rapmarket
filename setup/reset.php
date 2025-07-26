<?php
// Reset Setup Step
$error = '';
$success = '';

if ($_POST && isset($_POST['confirm_reset'])) {
    try {
        // Delete config.php if exists
        if (file_exists('../config.php')) {
            if (!unlink('../config.php')) {
                throw new Exception('Konnte config.php nicht löschen');
            }
        }
        
        // Clear any setup session data
        session_start();
        session_destroy();
        
        $success = 'Setup wurde zurückgesetzt! Du kannst jetzt neu beginnen.';
        
    } catch (Exception $e) {
        $error = 'Fehler beim Zurücksetzen: ' . $e->getMessage();
    }
}
?>

<div class="text-center">
    <i class="fas fa-redo fa-4x text-warning mb-4"></i>
    <h3>Setup zurücksetzen</h3>
    <p class="lead">Dies wird alle Setup-Konfigurationen löschen und das Setup neu starten.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
    </div>
    <div class="text-center mt-4">
        <a href="?step=welcome" class="btn btn-primary btn-lg">
            <i class="fas fa-play me-2"></i>Setup neu starten
        </a>
    </div>
<?php else: ?>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Achtung:</strong> Dies wird folgende Aktionen ausführen:<br>
    • Die <code>config.php</code> Datei löschen<br>
    • Alle Setup-Session-Daten löschen<br>
    • Das Setup auf den Ausgangszustand zurücksetzen<br><br>
    <strong>Die Datenbank und deren Inhalte bleiben unberührt!</strong>
</div>

<form method="POST" class="mt-4">
    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="confirm_reset" name="confirm_reset" required>
            <label class="form-check-label" for="confirm_reset">
                <strong>Ja, ich möchte das Setup zurücksetzen und alle Konfigurationsdateien löschen.</strong>
            </label>
        </div>
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="?step=welcome" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Abbrechen
        </a>
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-redo me-2"></i>Setup zurücksetzen
        </button>
    </div>
</form>

<?php endif; ?>

<div class="mt-4 p-3 border border-info rounded">
    <h6><i class="fas fa-info-circle me-2"></i>Hinweise zum Reset:</h6>
    <ul class="mb-0">
        <li>Die Datenbank wird <strong>NICHT</strong> gelöscht</li>
        <li>Bestehende User-Accounts bleiben erhalten</li>
        <li>Events und Wetten gehen nicht verloren</li>
        <li>Du musst nur die Datenbank-Verbindung neu konfigurieren</li>
    </ul>
</div>