<?php
/**
 * Setup-Script für RapMarket.de
 * Führt die erste Installation durch
 */

// Error Reporting für Setup
error_reporting(E_ALL);
ini_set('display_errors', 1);

$setupStep = $_GET['step'] ?? 'welcome';
$setupComplete = false;

// Prüfe ob bereits installiert
if (file_exists('config.php') && $setupStep === 'welcome') {
    try {
        require_once 'config.php';
        require_once 'includes/database.php';
        $db = Database::getInstance();
        if ($db->tableExists('users')) {
            $setupComplete = true;
        }
    } catch (Exception $e) {
        // Installation nicht vollständig
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapMarket.de Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%);
            min-height: 100vh;
            color: #ecf0f1;
        }
        .setup-container {
            max-width: 800px;
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
        .btn-primary:hover {
            background: #2980b9;
            border-color: #2980b9;
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
        .alert-info {
            background: #3498db;
            border-color: #2980b9;
            color: white;
        }
        .alert-success {
            background: #27ae60;
            border-color: #229954;
            color: white;
        }
        .alert-danger {
            background: #e74c3c;
            border-color: #c0392b;
            color: white;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #34495e;
            color: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #3498db;
        }
        .step.completed {
            background: #27ae60;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="text-center mb-4">
            <h1><i class="fas fa-microphone me-2"></i>RapMarket.de</h1>
            <p class="lead">Installation & Setup</p>
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
                    <a href="?step=reset" class="btn btn-outline-danger btn-lg ms-3">
                        <i class="fas fa-redo me-2"></i>Setup neu starten
                    </a>
                </div>
            </div>
        <?php else: ?>

        <div class="step-indicator">
            <div class="step <?= $setupStep === 'welcome' ? 'active' : ($setupStep !== 'welcome' ? 'completed' : '') ?>">1</div>
            <div class="step <?= $setupStep === 'database' ? 'active' : (in_array($setupStep, ['admin', 'complete']) ? 'completed' : '') ?>">2</div>
            <div class="step <?= $setupStep === 'admin' ? 'active' : ($setupStep === 'complete' ? 'completed' : '') ?>">3</div>
            <div class="step <?= $setupStep === 'complete' ? 'active' : '' ?>">4</div>
        </div>

        <div class="setup-card p-4">
            <?php
            switch ($setupStep) {
                case 'welcome':
                    include 'setup/welcome.php';
                    break;
                case 'database':
                    include 'setup/database.php';
                    break;
                case 'admin':
                    include 'setup/admin.php';
                    break;
                case 'complete':
                    include 'setup/complete.php';
                    break;
                case 'reset':
                    include 'setup/reset.php';
                    break;
                default:
                    include 'setup/welcome.php';
            }
            ?>
        </div>

        <?php endif; ?>

        <div class="text-center mt-4">
            <small class="text-muted">
                RapMarket.de Setup &copy; 2024 | 
                <a href="https://github.com/fabiantaboo/rapmarket" target="_blank" class="text-muted">
                    <i class="fab fa-github"></i> GitHub
                </a>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>