<?php
/**
 * Debug Setup-Script für RapMarket.de
 * Mit erweiterten Error-Informationen
 */

// Error Reporting für Setup aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Debug-Ausgabe
function debugLog($message) {
    echo "<div class='alert alert-info'><small>[DEBUG] " . htmlspecialchars($message) . "</small></div>";
}

$setupStep = $_GET['step'] ?? 'welcome';
$setupComplete = false;
$debugInfo = [];

// Debug-Informationen sammeln
$debugInfo[] = "PHP Version: " . phpversion();
$debugInfo[] = "Current Directory: " . getcwd();
$debugInfo[] = "Script File: " . __FILE__;
$debugInfo[] = "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set');

// Prüfe ob bereits installiert
if (file_exists('config.php') && $setupStep === 'welcome') {
    try {
        require_once 'config.php';
        require_once 'includes/database.php';
        
        // Define writeLog if not exists
        if (!function_exists('writeLog')) {
            function writeLog($level, $message, $context = []) {
                error_log("[$level] $message");
            }
        }
        
        $db = Database::getInstance();
        if ($db->tableExists('users')) {
            $setupComplete = true;
        }
    } catch (Exception $e) {
        $debugInfo[] = "Setup check error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapMarket.de Setup (Debug)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%);
            min-height: 100vh;
            color: #ecf0f1;
        }
        .setup-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .setup-card {
            background: #2c3e50;
            border: 1px solid #34495e;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: #3498db;
            border-color: #3498db;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #34495e;
            color: #ecf0f1;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #3498db;
            color: #ecf0f1;
        }
        .alert-info { background: #3498db; color: white; }
        .alert-success { background: #27ae60; color: white; }
        .alert-danger { background: #e74c3c; color: white; }
        .alert-warning { background: #f39c12; color: white; }
        .debug-section { background: #34495e; border-radius: 8px; padding: 15px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="text-center mb-4">
            <h1><i class="fas fa-microphone me-2"></i>RapMarket.de</h1>
            <p class="lead">Setup & Debug</p>
        </div>

        <!-- Debug Information -->
        <div class="debug-section mb-4">
            <h6><i class="fas fa-bug me-2"></i>Debug Information</h6>
            <?php foreach ($debugInfo as $info): ?>
                <small class="d-block text-muted"><?= htmlspecialchars($info) ?></small>
            <?php endforeach; ?>
        </div>

        <?php if ($setupComplete): ?>
            <div class="setup-card p-4">
                <div class="text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h3>Installation bereits abgeschlossen!</h3>
                    <p>RapMarket.de ist bereits konfiguriert und einsatzbereit.</p>
                    <a href="index.html" class="btn btn-primary btn-lg">
                        <i class="fas fa-home me-2"></i>Zur Website
                    </a>
                </div>
            </div>
        <?php else: ?>

            <div class="setup-card p-4">
                <?php if ($setupStep === 'database'): ?>
                    <?php
                    // Database Configuration Step mit Debug
                    $error = '';
                    $success = '';

                    if ($_POST) {
                        debugLog("POST-Daten erhalten");
                        
                        $dbHost = $_POST['db_host'] ?? '';
                        $dbName = $_POST['db_name'] ?? '';
                        $dbUser = $_POST['db_user'] ?? '';
                        $dbPass = $_POST['db_pass'] ?? '';
                        $appUrl = $_POST['app_url'] ?? '';
                        
                        debugLog("Daten: Host=$dbHost, DB=$dbName, User=$dbUser, URL=$appUrl");
                        
                        if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($appUrl)) {
                            $error = 'Bitte fülle alle Pflichtfelder aus!';
                        } else {
                            try {
                                debugLog("Teste Datenbankverbindung...");
                                
                                // Test database connection
                                $dsn = "mysql:host={$dbHost};charset=utf8mb4";
                                $testConnection = new PDO($dsn, $dbUser, $dbPass, [
                                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                                ]);
                                
                                debugLog("Datenbankverbindung erfolgreich");
                                
                                // Create config.php
                                $configContent = "<?php\n";
                                $configContent .= "// RapMarket.de Configuration\n";
                                $configContent .= "// Auto-generated by setup on " . date('Y-m-d H:i:s') . "\n\n";
                                $configContent .= "// Datenbank Konfiguration\n";
                                $configContent .= "define('DB_HOST', '{$dbHost}');\n";
                                $configContent .= "define('DB_NAME', '{$dbName}');\n";
                                $configContent .= "define('DB_USER', '{$dbUser}');\n";
                                $configContent .= "define('DB_PASS', '{$dbPass}');\n";
                                $configContent .= "define('DB_CHARSET', 'utf8mb4');\n\n";
                                $configContent .= "// Anwendungs-Konfiguration\n";
                                $configContent .= "define('APP_NAME', 'RapMarket.de');\n";
                                $configContent .= "define('APP_URL', '{$appUrl}');\n";
                                $configContent .= "define('APP_ENV', 'production');\n\n";
                                $configContent .= "// Sicherheit\n";
                                $configContent .= "define('JWT_SECRET', '" . bin2hex(random_bytes(32)) . "');\n";
                                $configContent .= "define('PASSWORD_SALT', '" . bin2hex(random_bytes(16)) . "');\n";
                                $configContent .= "define('SESSION_NAME', 'rapmarket_session');\n\n";
                                $configContent .= "// API Konfiguration\n";
                                $configContent .= "define('API_RATE_LIMIT', 100);\n";
                                $configContent .= "define('MAX_LOGIN_ATTEMPTS', 5);\n\n";
                                $configContent .= "// Punkte System\n";
                                $configContent .= "define('STARTING_POINTS', 1000);\n";
                                $configContent .= "define('DAILY_BONUS_POINTS', 50);\n";
                                $configContent .= "define('MIN_BET_AMOUNT', 10);\n";
                                $configContent .= "define('MAX_BET_AMOUNT', 1000);\n\n";
                                $configContent .= "// Debug und Logging\n";
                                $configContent .= "define('ENABLE_DEBUG', true);\n";
                                $configContent .= "define('LOG_LEVEL', 'DEBUG');\n";
                                $configContent .= "define('LOG_FILE', 'logs/app.log');\n\n";
                                $configContent .= "// Zeitzone\n";
                                $configContent .= "date_default_timezone_set('Europe/Berlin');\n\n";
                                $configContent .= "// Error Reporting für Debug\n";
                                $configContent .= "error_reporting(E_ALL);\n";
                                $configContent .= "ini_set('display_errors', 1);\n";
                                $configContent .= "?>";
                                
                                debugLog("Erstelle config.php...");
                                
                                if (file_put_contents('config.php', $configContent) === false) {
                                    throw new Exception('Konnte config.php nicht erstellen. Prüfe die Schreibrechte!');
                                }
                                
                                debugLog("config.php erstellt");
                                
                                // Test the configuration
                                require_once 'config.php';
                                
                                // Define writeLog function
                                if (!function_exists('writeLog')) {
                                    function writeLog($level, $message, $context = []) {
                                        error_log("[$level] $message");
                                    }
                                }
                                
                                debugLog("Lade Database-Klasse...");
                                require_once 'includes/database.php';
                                
                                debugLog("Erstelle Datenbankinstanz...");
                                $db = Database::getInstance();
                                
                                debugLog("Datenbank-Setup abgeschlossen");
                                
                                $success = 'Datenbank-Konfiguration erfolgreich! Datenbank und Tabellen wurden automatisch erstellt.';
                                
                            } catch (Exception $e) {
                                $error = 'Fehler: ' . $e->getMessage();
                                debugLog("ERROR: " . $e->getMessage());
                                debugLog("Stack trace: " . $e->getTraceAsString());
                                
                                // Delete config.php if created but failed
                                if (file_exists('config.php')) {
                                    unlink('config.php');
                                }
                            }
                        }
                    }
                    ?>

                    <h3><i class="fas fa-database me-2"></i>Datenbank-Konfiguration (Debug)</h3>

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
                            <a href="setup.php?step=admin" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-right me-2"></i>Weiter (Normaler Setup)
                            </a>
                        </div>
                    <?php else: ?>

                        <form method="POST" class="mt-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_host" class="form-label">Datenbank Host *</label>
                                        <input type="text" class="form-control" id="db_host" name="db_host" 
                                               value="<?= $_POST['db_host'] ?? 'localhost' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_name" class="form-label">Datenbank Name *</label>
                                        <input type="text" class="form-control" id="db_name" name="db_name" 
                                               value="<?= $_POST['db_name'] ?? 'rapmarket' ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_user" class="form-label">Datenbank Benutzername *</label>
                                        <input type="text" class="form-control" id="db_user" name="db_user" 
                                               value="<?= $_POST['db_user'] ?? '' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_pass" class="form-label">Datenbank Passwort</label>
                                        <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                               value="<?= $_POST['db_pass'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="app_url" class="form-label">Website URL *</label>
                                <input type="url" class="form-control" id="app_url" name="app_url" 
                                       value="<?= $_POST['app_url'] ?? 'https://rapmarket.tabootwin.com' ?>" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check me-2"></i>Datenbank konfigurieren (Debug)
                            </button>
                        </form>

                    <?php endif; ?>

                <?php else: ?>
                    <!-- Welcome Step -->
                    <div class="text-center">
                        <h3>RapMarket.de Debug Setup</h3>
                        <p>Dies ist die Debug-Version des Setups mit erweiterten Error-Informationen.</p>
                        <a href="?step=database" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>Datenbank konfigurieren (Debug)
                        </a>
                        <hr>
                        <a href="setup.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Zum normalen Setup
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>