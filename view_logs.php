<?php
/**
 * Log Viewer für RapMarket.de
 */
require_once 'includes/logger.php';

$logFiles = Logger::getLogFiles();
$selectedLog = $_GET['log'] ?? 'app';
$lines = $_GET['lines'] ?? 100;
$refresh = $_GET['refresh'] ?? false;

// Auto-refresh
if ($refresh) {
    header("Refresh: 5");
}

$currentLogFile = $logFiles[$selectedLog] ?? $logFiles['app'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapMarket.de - Log Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-content {
            background: #1e1e1e;
            color: #f8f9fa;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 5px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .log-line {
            margin: 2px 0;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .log-error { background-color: rgba(220, 53, 69, 0.2); }
        .log-warning { background-color: rgba(255, 193, 7, 0.2); }
        .log-info { background-color: rgba(13, 202, 240, 0.1); }
        .log-debug { background-color: rgba(108, 117, 125, 0.1); }
        .log-api { background-color: rgba(25, 135, 84, 0.2); }
        
        .controls {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-microphone me-2"></i>RapMarket.de - Log Viewer</h1>
                
                <!-- Controls -->
                <div class="controls">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="form-label">Log Datei:</label>
                            <select class="form-select" onchange="changeLog(this.value)">
                                <option value="app" <?= $selectedLog === 'app' ? 'selected' : '' ?>>Application Log</option>
                                <option value="error" <?= $selectedLog === 'error' ? 'selected' : '' ?>>Error Log</option>
                                <option value="api" <?= $selectedLog === 'api' ? 'selected' : '' ?>>API Log</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Zeilen:</label>
                            <select class="form-select" onchange="changeLines(this.value)">
                                <option value="50" <?= $lines == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $lines == 100 ? 'selected' : '' ?>>100</option>
                                <option value="200" <?= $lines == 200 ? 'selected' : '' ?>>200</option>
                                <option value="500" <?= $lines == 500 ? 'selected' : '' ?>>500</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label><br>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="autoRefresh" <?= $refresh ? 'checked' : '' ?> onchange="toggleRefresh(this.checked)">
                                <label class="form-check-label" for="autoRefresh">
                                    Auto-Refresh (5s)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label><br>
                            <button class="btn btn-primary me-2" onclick="location.reload()">
                                <i class="fas fa-refresh me-1"></i>Aktualisieren
                            </button>
                            <button class="btn btn-warning me-2" onclick="clearLogs()">
                                <i class="fas fa-trash me-1"></i>Logs löschen
                            </button>
                            <a href="debug_frontend.html" class="btn btn-info">
                                <i class="fas fa-bug me-1"></i>Debug Tool
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Stats -->
                <?php
                $logStats = getLogStats($currentLogFile);
                ?>
                <div class="stats">
                    <div class="stat-card">
                        <h6>Datei Größe</h6>
                        <div class="h4"><?= formatBytes(filesize($currentLogFile)) ?></div>
                    </div>
                    <div class="stat-card">
                        <h6>Letzte Änderung</h6>
                        <div class="h4"><?= date('H:i:s', filemtime($currentLogFile)) ?></div>
                    </div>
                    <div class="stat-card">
                        <h6>Error Logs</h6>
                        <div class="h4 text-danger"><?= $logStats['errors'] ?></div>
                    </div>
                    <div class="stat-card">
                        <h6>API Requests</h6>
                        <div class="h4 text-success"><?= $logStats['api_requests'] ?></div>
                    </div>
                </div>
                
                <!-- Log Content -->
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <?php
                            switch($selectedLog) {
                                case 'error': echo '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Error Log'; break;
                                case 'api': echo '<i class="fas fa-code text-success me-2"></i>API Log'; break;
                                default: echo '<i class="fas fa-file-alt text-primary me-2"></i>Application Log'; break;
                            }
                            ?>
                            <small class="text-muted">(<?= $currentLogFile ?>)</small>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="log-content" id="logContent">
                            <?php
                            if (file_exists($currentLogFile)) {
                                $logLines = file($currentLogFile);
                                $logLines = array_slice($logLines, -$lines);
                                
                                foreach ($logLines as $line) {
                                    echo formatLogLine($line);
                                }
                            } else {
                                echo "<div class='text-muted'>Log-Datei nicht gefunden.</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeLog(log) {
            const url = new URL(window.location);
            url.searchParams.set('log', log);
            window.location = url;
        }
        
        function changeLines(lines) {
            const url = new URL(window.location);
            url.searchParams.set('lines', lines);
            window.location = url;
        }
        
        function toggleRefresh(enabled) {
            const url = new URL(window.location);
            if (enabled) {
                url.searchParams.set('refresh', '1');
            } else {
                url.searchParams.delete('refresh');
            }
            window.location = url;
        }
        
        function clearLogs() {
            if (confirm('Alle Logs löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                fetch('?action=clear_logs', {method: 'POST'})
                    .then(() => location.reload());
            }
        }
        
        // Auto-scroll to bottom
        const logContent = document.getElementById('logContent');
        logContent.scrollTop = logContent.scrollHeight;
    </script>
</body>
</html>

<?php
// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'clear_logs') {
    Logger::clearLogs();
    echo json_encode(['success' => true]);
    exit;
}

function formatLogLine($line) {
    $line = htmlspecialchars($line);
    $cssClass = 'log-line';
    
    if (strpos($line, 'ERROR') !== false) {
        $cssClass .= ' log-error';
    } elseif (strpos($line, 'WARNING') !== false) {
        $cssClass .= ' log-warning';
    } elseif (strpos($line, 'INFO') !== false) {
        $cssClass .= ' log-info';
    } elseif (strpos($line, 'DEBUG') !== false) {
        $cssClass .= ' log-debug';
    } elseif (strpos($line, 'API') !== false) {
        $cssClass .= ' log-api';
    }
    
    return "<div class=\"{$cssClass}\">{$line}</div>";
}

function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return round($size, 2) . ' ' . $units[$unit];
}

function getLogStats($logFile) {
    $stats = ['errors' => 0, 'api_requests' => 0];
    
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $stats['errors'] = substr_count($content, 'ERROR');
        $stats['api_requests'] = substr_count($content, 'API Request');
    }
    
    return $stats;
}
?>