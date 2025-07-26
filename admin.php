<?php
/**
 * Admin Backend für RapMarket.de
 */
session_start();

require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/logger.php';

// Prüfe Admin-Berechtigung
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $_SESSION['user_id']]);

if (!$user || !$user['is_admin']) {
    header('Location: index.html');
    exit;
}

Logger::logUserAction('admin_access', $user['id'], ['page' => 'admin_dashboard']);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_event':
                $result = createEvent($_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'toggle_event':
                $result = toggleEventStatus($_POST['event_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'delete_event':
                $result = deleteEvent($_POST['event_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Hole Events für Übersicht
$events = $db->fetchAll("
    SELECT e.*, 
           COUNT(b.id) as bet_count,
           SUM(b.amount) as total_bets
    FROM events e 
    LEFT JOIN bets b ON e.id = b.event_id 
    GROUP BY e.id 
    ORDER BY e.start_date ASC, e.created_at DESC
");

function createEvent($data) {
    global $db;
    
    try {
        $title = trim($data['title']);
        $description = trim($data['description']);
        $eventDate = $data['event_date'];
        $category = $data['category'];
        $minBet = (int)$data['min_bet'];
        $maxBet = (int)$data['max_bet'];
        $options = json_decode($data['options'], true);
        
        if (empty($title) || empty($description) || empty($eventDate)) {
            return ['success' => false, 'message' => 'Alle Pflichtfelder müssen ausgefüllt werden'];
        }
        
        if (!$options || count($options) < 2) {
            return ['success' => false, 'message' => 'Mindestens 2 Wett-Optionen erforderlich'];
        }
        
        // Event erstellen
        $eventId = $db->insert('events', [
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'start_date' => $eventDate,
            'end_date' => date('Y-m-d H:i:s', strtotime($eventDate . ' +1 week')), // Event läuft eine Woche
            'min_bet' => $minBet,
            'max_bet' => $maxBet,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id']
        ]);
        
        if (!$eventId) {
            return ['success' => false, 'message' => 'Event konnte nicht erstellt werden'];
        }
        
        // Event-Optionen erstellen
        foreach ($options as $option) {
            $db->insert('event_options', [
                'event_id' => $eventId,
                'option_text' => trim($option['text']),
                'odds' => (float)$option['odds'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        Logger::logUserAction('create_event', $_SESSION['user_id'], [
            'event_id' => $eventId,
            'title' => $title,
            'options_count' => count($options)
        ]);
        
        return ['success' => true, 'message' => 'Event erfolgreich erstellt!'];
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'create_event']);
        return ['success' => false, 'message' => 'Fehler beim Erstellen: ' . $e->getMessage()];
    }
}

function toggleEventStatus($eventId) {
    global $db;
    
    try {
        $event = $db->fetchOne("SELECT * FROM events WHERE id = :id", ['id' => $eventId]);
        if (!$event) {
            return ['success' => false, 'message' => 'Event nicht gefunden'];
        }
        
        $newStatus = $event['status'] === 'active' ? 'inactive' : 'active';
        
        $db->update('events', 
            ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 
            'id = :id', 
            ['id' => $eventId]
        );
        
        Logger::logUserAction('toggle_event', $_SESSION['user_id'], [
            'event_id' => $eventId,
            'old_status' => $event['status'],
            'new_status' => $newStatus
        ]);
        
        return ['success' => true, 'message' => "Event {$newStatus} gesetzt"];
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'toggle_event']);
        return ['success' => false, 'message' => 'Fehler beim Aktualisieren'];
    }
}

function deleteEvent($eventId) {
    global $db;
    
    try {
        // Prüfe ob bereits Wetten vorhanden
        $betCount = $db->fetchOne("SELECT COUNT(*) as count FROM bets WHERE event_id = :id", ['id' => $eventId]);
        
        if ($betCount['count'] > 0) {
            return ['success' => false, 'message' => 'Event kann nicht gelöscht werden - bereits Wetten vorhanden'];
        }
        
        // Lösche Event-Optionen
        $db->delete('event_options', 'event_id = :id', ['id' => $eventId]);
        
        // Lösche Event
        $db->delete('events', 'id = :id', ['id' => $eventId]);
        
        Logger::logUserAction('delete_event', $_SESSION['user_id'], ['event_id' => $eventId]);
        
        return ['success' => true, 'message' => 'Event erfolgreich gelöscht'];
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'delete_event']);
        return ['success' => false, 'message' => 'Fehler beim Löschen'];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Backend - RapMarket.de</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        .admin-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        
        .event-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .event-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .event-card.inactive {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
        
        .option-input {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .sidebar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            height: fit-content;
        }
        
        .sidebar .form-control,
        .sidebar .form-select {
            background-color: white !important;
            color: #212529 !important;
            border: 1px solid #ced4da !important;
        }
        
        .sidebar .form-control:focus,
        .sidebar .form-select:focus {
            background-color: white !important;
            color: #212529 !important;
            border-color: #86b7fe !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }
        
        .sidebar label {
            color: #212529 !important;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-cogs me-2"></i>Admin Backend</h1>
                    <p class="mb-0">Event- und Wett-Management für RapMarket.de</p>
                </div>
                <div class="col-md-6 text-end">
                    <span class="me-3">Willkommen, <strong><?= htmlspecialchars($user['username']) ?></strong></span>
                    <a href="index.html" class="btn btn-light">
                        <i class="fas fa-home me-1"></i>Zur App
                    </a>
                    <a href="view_logs.php" class="btn btn-outline-light ms-2">
                        <i class="fas fa-file-alt me-1"></i>Logs
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-4">
                <div class="sidebar">
                    <h5><i class="fas fa-plus me-2"></i>Neues Event erstellen</h5>
                    
                    <form method="POST" id="eventForm">
                        <input type="hidden" name="action" value="create_event">
                        
                        <div class="mb-3">
                            <label class="form-label">Event Titel *</label>
                            <input type="text" class="form-control" name="title" required 
                                   placeholder="z.B. Deutscher Rap Battle 2025">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Beschreibung *</label>
                            <textarea class="form-control" name="description" rows="3" required
                                      placeholder="Detaillierte Beschreibung des Events..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kategorie</label>
                            <select class="form-control" name="category">
                                <option value="battle">Rap Battle</option>
                                <option value="album">Album Release</option>
                                <option value="concert">Konzert</option>
                                <option value="award">Award Show</option>
                                <option value="other">Sonstiges</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Event Datum *</label>
                            <input type="datetime-local" class="form-control" name="event_date" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Min. Einsatz</label>
                                <input type="number" class="form-control" name="min_bet" value="10" min="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Max. Einsatz</label>
                                <input type="number" class="form-control" name="max_bet" value="1000" min="10">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Wett-Optionen *</label>
                            <div id="options-container">
                                <div class="option-input">
                                    <input type="text" class="form-control" placeholder="Option Text (z.B. Artist A gewinnt)" required>
                                    <input type="number" class="form-control" placeholder="Quote (z.B. 2.5)" step="0.1" min="1.1" required>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="option-input">
                                    <input type="text" class="form-control" placeholder="Option Text (z.B. Artist B gewinnt)" required>
                                    <input type="number" class="form-control" placeholder="Quote (z.B. 1.8)" step="0.1" min="1.1" required>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addOption()">
                                <i class="fas fa-plus me-1"></i>Option hinzufügen
                            </button>
                            
                            <input type="hidden" name="options" id="options-json">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i>Event erstellen
                        </button>
                    </form>
                </div>
                
                <!-- Quick Stats -->
                <div class="stats-card mt-4">
                    <div class="stats-number"><?= count($events) ?></div>
                    <div>Gesamt Events</div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-calendar-alt me-2"></i>Event Übersicht</h3>
                    <div>
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-refresh me-1"></i>Aktualisieren
                        </button>
                    </div>
                </div>

                <?php if (empty($events)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                        <h5>Noch keine Events erstellt</h5>
                        <p class="text-muted">Erstelle dein erstes Event über das Formular links.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $options = $db->fetchAll("SELECT * FROM event_options WHERE event_id = :id", ['id' => $event['id']]);
                        ?>
                        <div class="event-card <?= $event['status'] === 'inactive' ? 'inactive' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <?= htmlspecialchars($event['title']) ?>
                                        <span class="badge bg-<?= $event['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
                                            <?= $event['status'] ?>
                                        </span>
                                    </h5>
                                    
                                    <p class="text-muted mb-2"><?= htmlspecialchars($event['description']) ?></p>
                                    
                                    <div class="row text-sm">
                                        <div class="col-md-6">
                                            <small><i class="fas fa-calendar me-1"></i><?= isset($event['start_date']) ? date('d.m.Y H:i', strtotime($event['start_date'])) : 'Kein Datum' ?></small><br>
                                            <small><i class="fas fa-tag me-1"></i><?= ucfirst($event['category']) ?></small><br>
                                            <small><i class="fas fa-coins me-1"></i><?= $event['min_bet'] ?> - <?= $event['max_bet'] ?> Punkte</small>
                                        </div>
                                        <div class="col-md-6">
                                            <small><i class="fas fa-chart-line me-1"></i><?= $event['bet_count'] ?? 0 ?> Wetten</small><br>
                                            <small><i class="fas fa-money-bill me-1"></i><?= number_format($event['total_bets'] ?? 0) ?> Punkte gesetzt</small><br>
                                            <small><i class="fas fa-clock me-1"></i><?= date('d.m.Y', strtotime($event['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($options)): ?>
                                        <div class="mt-3">
                                            <strong>Wett-Optionen:</strong>
                                            <div class="row mt-2">
                                                <?php foreach ($options as $option): ?>
                                                    <div class="col-md-6">
                                                        <small class="d-block p-2 bg-light rounded">
                                                            <?= htmlspecialchars($option['option_text']) ?> 
                                                            <span class="fw-bold">(<?= $option['odds'] ?>x)</span>
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="ms-3">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_event">
                                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                        <button type="submit" class="btn btn-outline-<?= $event['status'] === 'active' ? 'warning' : 'success' ?> btn-sm">
                                            <i class="fas fa-<?= $event['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if (($event['bet_count'] ?? 0) == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Event wirklich löschen?')">
                                            <input type="hidden" name="action" value="delete_event">
                                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addOption() {
            const container = document.getElementById('options-container');
            const div = document.createElement('div');
            div.className = 'option-input';
            div.innerHTML = `
                <input type="text" class="form-control" placeholder="Option Text" required>
                <input type="number" class="form-control" placeholder="Quote" step="0.1" min="1.1" required>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(this)">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(div);
        }
        
        function removeOption(button) {
            const container = document.getElementById('options-container');
            if (container.children.length > 2) {
                button.parentElement.remove();
            } else {
                alert('Mindestens 2 Optionen erforderlich!');
            }
        }
        
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            const options = [];
            const optionInputs = document.querySelectorAll('.option-input');
            
            optionInputs.forEach(function(input) {
                const text = input.querySelector('input[type="text"]').value;
                const odds = input.querySelector('input[type="number"]').value;
                
                if (text && odds) {
                    options.push({
                        text: text,
                        odds: parseFloat(odds)
                    });
                }
            });
            
            if (options.length < 2) {
                alert('Mindestens 2 Wett-Optionen erforderlich!');
                e.preventDefault();
                return;
            }
            
            document.getElementById('options-json').value = JSON.stringify(options);
        });
        
        // Set minimum date to today
        document.querySelector('input[name="event_date"]').min = new Date().toISOString().slice(0, 16);
    </script>
</body>
</html>