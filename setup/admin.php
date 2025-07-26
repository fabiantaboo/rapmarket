<?php
// Admin Account Creation Step
session_start();

if (!isset($_SESSION['setup_db_configured'])) {
    header('Location: ?step=database');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Bitte fülle alle Felder aus!';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = 'Username muss zwischen 3 und 20 Zeichen lang sein!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse!';
    } elseif (strlen($password) < 6) {
        $error = 'Passwort muss mindestens 6 Zeichen lang sein!';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwörter stimmen nicht überein!';
    } else {
        try {
            require_once '../config.php';
            require_once '../includes/database.php';
            require_once '../includes/functions.php';
            
            $db = Database::getInstance();
            
            // Check if admin already exists
            $existingAdmin = $db->fetchOne("SELECT id FROM users WHERE is_admin = 1 LIMIT 1");
            
            if ($existingAdmin) {
                $error = 'Ein Administrator-Account existiert bereits!';
            } else {
                // Create admin user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $adminId = $db->insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'points' => 10000, // Admin bekommt mehr Startpunkte
                    'is_admin' => 1,
                    'is_verified' => 1,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'login_count' => 0
                ]);
                
                if ($adminId) {
                    // Log the creation
                    $db->insert('user_logs', [
                        'user_id' => $adminId,
                        'action' => 'admin_created',
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                        'details' => 'Admin account created during setup',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Add initial points transaction
                    $db->insert('point_transactions', [
                        'user_id' => $adminId,
                        'amount' => 10000,
                        'type' => 'credit',
                        'reason' => 'Admin-Account Startpunkte',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $_SESSION['setup_admin_created'] = true;
                    $_SESSION['admin_username'] = $username;
                    $success = 'Administrator-Account erfolgreich erstellt!';
                } else {
                    $error = 'Fehler beim Erstellen des Admin-Accounts!';
                }
            }
        } catch (Exception $e) {
            $error = 'Fehler: ' . $e->getMessage();
        }
    }
}
?>

<h3><i class="fas fa-user-shield me-2"></i>Administrator-Account</h3>
<p>Erstelle deinen Administrator-Account für die Verwaltung von RapMarket.de:</p>

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
        <a href="?step=complete" class="btn btn-primary btn-lg">
            <i class="fas fa-arrow-right me-2"></i>Setup abschließen
        </a>
    </div>
<?php else: ?>

<form method="POST" class="mt-4">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="fas fa-user me-1"></i>Administrator Username *
                </label>
                <input type="text" class="form-control" id="username" name="username" 
                       value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>" 
                       pattern="[a-zA-Z0-9_]{3,20}" required>
                <small class="form-text text-muted">3-20 Zeichen, nur Buchstaben, Zahlen und Unterstriche</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-1"></i>E-Mail-Adresse *
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                <small class="form-text text-muted">Für wichtige Benachrichtigungen</small>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-1"></i>Passwort *
                </label>
                <input type="password" class="form-control" id="password" name="password" 
                       minlength="6" required>
                <small class="form-text text-muted">Mindestens 6 Zeichen</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-lock me-1"></i>Passwort bestätigen *
                </label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       minlength="6" required>
            </div>
        </div>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-crown me-2"></i>
        <strong>Administrator-Vorteile:</strong><br>
        • 10.000 Startpunkte (statt 1.000)<br>
        • Vollzugriff auf alle Funktionen<br>
        • Kann Events erstellen und verwalten<br>
        • Zugriff auf Admin-Panel (in Entwicklung)
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="?step=database" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Zurück
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Admin-Account erstellen
        </button>
    </div>
</form>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwörter stimmen nicht überein');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php endif; ?>