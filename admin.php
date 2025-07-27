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
                
            case 'manage_user':
                $result = manageUser($_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'resolve_event':
                $result = resolveEvent($_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'create_category':
                $result = createCategory($_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'delete_category':
                $result = deleteCategory($_POST['category_name']);
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

// Hole Users für User-Management
$users = $db->fetchAll("
    SELECT u.*, 
           COUNT(b.id) as total_bets,
           SUM(CASE WHEN b.status = 'won' THEN 1 ELSE 0 END) as won_bets,
           SUM(b.amount) as total_wagered
    FROM users u
    LEFT JOIN bets b ON u.id = b.user_id
    GROUP BY u.id
    ORDER BY u.points DESC, u.created_at DESC
    LIMIT 50
");

// Hole Kategorien
$categories = [
    'battle' => 'Rap Battles',
    'charts' => 'Charts', 
    'streaming' => 'Streaming',
    'tour' => 'Tours & Konzerte',
    'awards' => 'Awards',
    'general' => 'Allgemein'
];

// Hole Statistiken
$stats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'total_events' => count($events),
    'total_bets' => $db->fetchOne("SELECT COUNT(*) as count FROM bets")['count'],
    'total_volume' => $db->fetchOne("SELECT SUM(amount) as total FROM bets")['total'] ?? 0
];

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

function manageUser($data) {
    global $db;
    
    try {
        $userId = (int)$data['user_id'];
        $action = $data['user_action'];
        
        $user = $db->fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
        if (!$user) {
            return ['success' => false, 'message' => 'User nicht gefunden'];
        }
        
        switch ($action) {
            case 'toggle_active':
                $newStatus = $user['is_active'] ? 0 : 1;
                $db->update('users', ['is_active' => $newStatus], 'id = :id', ['id' => $userId]);
                $message = $newStatus ? 'User aktiviert' : 'User deaktiviert';
                break;
                
            case 'make_admin':
                $db->update('users', ['is_admin' => 1], 'id = :id', ['id' => $userId]);
                $message = 'User zu Admin gemacht';
                break;
                
            case 'remove_admin':
                if ($userId == $_SESSION['user_id']) {
                    return ['success' => false, 'message' => 'Du kannst dir nicht selbst Admin-Rechte entziehen'];
                }
                $db->update('users', ['is_admin' => 0], 'id = :id', ['id' => $userId]);
                $message = 'Admin-Rechte entfernt';
                break;
                
            case 'add_points':
                $points = (int)$data['points_amount'];
                $db->update('users', ['points' => $user['points'] + $points], 'id = :id', ['id' => $userId]);
                $message = "{$points} Punkte hinzugefügt";
                break;
                
            default:
                return ['success' => false, 'message' => 'Ungültige Aktion'];
        }
        
        Logger::logUserAction('admin_manage_user', $_SESSION['user_id'], [
            'target_user_id' => $userId,
            'action' => $action,
            'data' => $data
        ]);
        
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'manage_user']);
        return ['success' => false, 'message' => 'Fehler bei User-Management'];
    }
}

function resolveEvent($data) {
    global $db;
    
    try {
        $eventId = (int)$data['event_id'];
        $winningOptionId = (int)$data['winning_option'];
        
        $event = $db->fetchOne("SELECT * FROM events WHERE id = :id", ['id' => $eventId]);
        if (!$event) {
            return ['success' => false, 'message' => 'Event nicht gefunden'];
        }
        
        if ($event['status'] === 'resolved') {
            return ['success' => false, 'message' => 'Event bereits aufgelöst'];
        }
        
        // Markiere gewinnende Option
        $db->update('event_options', ['is_winning_option' => 0], 'event_id = :id', ['id' => $eventId]);
        $db->update('event_options', ['is_winning_option' => 1], 'id = :id', ['id' => $winningOptionId]);
        
        // Update Event Status
        $db->update('events', [
            'status' => 'resolved',
            'result_date' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $eventId]);
        
        // Berechne Gewinne und verluste
        $bets = $db->fetchAll("SELECT * FROM bets WHERE event_id = :id", ['id' => $eventId]);
        
        foreach ($bets as $bet) {
            if ($bet['option_id'] == $winningOptionId) {
                // Gewinnende Wette
                $winnings = $bet['amount'] * $bet['odds'];
                $db->update('bets', [
                    'status' => 'won',
                    'actual_winnings' => $winnings,
                    'resolved_at' => date('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $bet['id']]);
                
                // Punkte dem User gutschreiben
                $currentUser = $db->fetchOne("SELECT points FROM users WHERE id = :id", ['id' => $bet['user_id']]);
                $currentPoints = (int)$currentUser['points'];
                $winnings = (int)$winnings;
                $newPoints = $currentPoints + $winnings;
                
                Logger::debug('Points calculation', [
                    'user_id' => $bet['user_id'],
                    'current_points' => $currentPoints,
                    'winnings' => $winnings,
                    'new_points' => $newPoints,
                    'bet_amount' => $bet['amount'],
                    'bet_odds' => $bet['odds']
                ]);
                
                $db->update('users', 
                    ['points' => $newPoints], 
                    'id = :id', 
                    ['id' => $bet['user_id']]
                );
            } else {
                // Verlorene Wette
                $db->update('bets', [
                    'status' => 'lost',
                    'resolved_at' => date('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $bet['id']]);
            }
        }
        
        Logger::logUserAction('resolve_event', $_SESSION['user_id'], [
            'event_id' => $eventId,
            'winning_option_id' => $winningOptionId,
            'bets_processed' => count($bets)
        ]);
        
        return ['success' => true, 'message' => 'Event erfolgreich aufgelöst'];
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'resolve_event']);
        return ['success' => false, 'message' => 'Fehler beim Auflösen des Events'];
    }
}

function createCategory($data) {
    global $categories;
    
    try {
        $categoryKey = trim($data['category_key']);
        $categoryName = trim($data['category_name']);
        
        if (empty($categoryKey) || empty($categoryName)) {
            return ['success' => false, 'message' => 'Kategorie-Schlüssel und Name sind erforderlich'];
        }
        
        if (isset($categories[$categoryKey])) {
            return ['success' => false, 'message' => 'Kategorie existiert bereits'];
        }
        
        // Für diese Demo speichern wir Kategorien in einer einfachen Datei
        // In einer echten Anwendung würde man eine separate Kategorien-Tabelle verwenden
        $categoriesFile = 'data/categories.json';
        
        if (!file_exists('data')) {
            mkdir('data', 0755, true);
        }
        
        $existingCategories = [];
        if (file_exists($categoriesFile)) {
            $existingCategories = json_decode(file_get_contents($categoriesFile), true) ?? [];
        }
        
        $existingCategories[$categoryKey] = $categoryName;
        file_put_contents($categoriesFile, json_encode($existingCategories, JSON_PRETTY_PRINT));
        
        Logger::logUserAction('create_category', $_SESSION['user_id'], [
            'category_key' => $categoryKey,
            'category_name' => $categoryName
        ]);
        
        return ['success' => true, 'message' => 'Kategorie erfolgreich erstellt'];
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'create_category']);
        return ['success' => false, 'message' => 'Fehler beim Erstellen der Kategorie'];
    }
}

function deleteCategory($categoryKey) {
    try {
        if (empty($categoryKey)) {
            return ['success' => false, 'message' => 'Kategorie-Schlüssel erforderlich'];
        }
        
        // Standard-Kategorien nicht löschbar
        $defaultCategories = ['battle', 'charts', 'streaming', 'tour', 'awards', 'general'];
        if (in_array($categoryKey, $defaultCategories)) {
            return ['success' => false, 'message' => 'Standard-Kategorien können nicht gelöscht werden'];
        }
        
        $categoriesFile = 'data/categories.json';
        
        if (!file_exists($categoriesFile)) {
            return ['success' => false, 'message' => 'Keine benutzerdefinierten Kategorien gefunden'];
        }
        
        $existingCategories = json_decode(file_get_contents($categoriesFile), true) ?? [];
        
        if (!isset($existingCategories[$categoryKey])) {
            return ['success' => false, 'message' => 'Kategorie nicht gefunden'];
        }
        
        unset($existingCategories[$categoryKey]);
        file_put_contents($categoriesFile, json_encode($existingCategories, JSON_PRETTY_PRINT));
        
        Logger::logUserAction('delete_category', $_SESSION['user_id'], [
            'category_key' => $categoryKey
        ]);
        
        return ['success' => true, 'message' => 'Kategorie erfolgreich gelöscht'];
        
    } catch (Exception $e) {
        Logger::logException($e, ['context' => 'delete_category']);
        return ['success' => false, 'message' => 'Fehler beim Löschen der Kategorie'];
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

    <div class="container-fluid">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="adminTabs">
            <li class="nav-item">
                <a class="nav-link active" id="events-tab" data-bs-toggle="tab" href="#events">
                    <i class="fas fa-calendar-alt me-2"></i>Events
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="users-tab" data-bs-toggle="tab" href="#users">
                    <i class="fas fa-users me-2"></i>User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="categories-tab" data-bs-toggle="tab" href="#categories">
                    <i class="fas fa-tags me-2"></i>Kategorien
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="resolution-tab" data-bs-toggle="tab" href="#resolution">
                    <i class="fas fa-gavel me-2"></i>Event Resolution
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="stats-tab" data-bs-toggle="tab" href="#stats">
                    <i class="fas fa-chart-bar me-2"></i>Statistiken
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="adminTabContent">
            <!-- Events Tab -->
            <div class="tab-pane fade show active" id="events">
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
                            <select class="form-control" name="category" id="categorySelect">
                                <option value="battle">Rap Battle</option>
                                <option value="charts">Charts</option>
                                <option value="streaming">Streaming</option>
                                <option value="tour">Tour & Konzerte</option>
                                <option value="awards">Awards</option>
                                <option value="general">Allgemein</option>
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
            
            <!-- Users Tab -->
            <div class="tab-pane fade" id="users">
                <div class="row">
                    <div class="col-12">
                        <h4><i class="fas fa-users me-2"></i>User Management</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>E-Mail</th>
                                        <th>Punkte</th>
                                        <th>Wetten</th>
                                        <th>Status</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userItem): ?>
                                        <tr>
                                            <td><?= $userItem['id'] ?></td>
                                            <td>
                                                <?= htmlspecialchars($userItem['username']) ?>
                                                <?php if ($userItem['is_admin']): ?>
                                                    <span class="badge bg-danger">Admin</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($userItem['email']) ?></td>
                                            <td><?= number_format($userItem['points']) ?></td>
                                            <td><?= $userItem['total_bets'] ?? 0 ?></td>
                                            <td>
                                                <span class="badge bg-<?= $userItem['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $userItem['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="manage_user">
                                                        <input type="hidden" name="user_id" value="<?= $userItem['id'] ?>">
                                                        <input type="hidden" name="user_action" value="toggle_active">
                                                        <button type="submit" class="btn btn-sm btn-outline-<?= $userItem['is_active'] ? 'warning' : 'success' ?>">
                                                            <i class="fas fa-<?= $userItem['is_active'] ? 'pause' : 'play' ?>"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if (!$userItem['is_admin']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="manage_user">
                                                            <input type="hidden" name="user_id" value="<?= $userItem['id'] ?>">
                                                            <input type="hidden" name="user_action" value="make_admin">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-crown"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <?php if ($userItem['id'] != $_SESSION['user_id']): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="manage_user">
                                                                <input type="hidden" name="user_id" value="<?= $userItem['id'] ?>">
                                                                <input type="hidden" name="user_action" value="remove_admin">
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                                    <i class="fas fa-user-minus"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="showAddPointsModal(<?= $userItem['id'] ?>, '<?= htmlspecialchars($userItem['username']) ?>')">
                                                        <i class="fas fa-coins"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div class="tab-pane fade" id="categories">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-plus me-2"></i>Neue Kategorie erstellen</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_category">
                                    <div class="mb-3">
                                        <label class="form-label">Kategorie-Schlüssel</label>
                                        <input type="text" class="form-control" name="category_key" 
                                               placeholder="z.B. freestyle" required>
                                        <small class="form-text text-muted">Eindeutiger Schlüssel ohne Leerzeichen</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategorie-Name</label>
                                        <input type="text" class="form-control" name="category_name" 
                                               placeholder="z.B. Freestyle Battles" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Kategorie erstellen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-list me-2"></i>Verfügbare Kategorien</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Lade benutzerdefinierte Kategorien
                                $customCategories = [];
                                if (file_exists('data/categories.json')) {
                                    $customCategories = json_decode(file_get_contents('data/categories.json'), true) ?? [];
                                }
                                $allCategories = array_merge($categories, $customCategories);
                                ?>
                                
                                <div class="list-group">
                                    <?php foreach ($allCategories as $key => $name): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($name) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($key) ?></small>
                                            </div>
                                            <div>
                                                <?php if (!in_array($key, ['battle', 'charts', 'streaming', 'tour', 'awards', 'general'])): ?>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Kategorie wirklich löschen?')">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <input type="hidden" name="category_name" value="<?= $key ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Standard</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resolution Tab -->
            <div class="tab-pane fade" id="resolution">
                <div class="row">
                    <div class="col-12">
                        <h4><i class="fas fa-gavel me-2"></i>Event Resolution</h4>
                        <p class="text-muted">Events mit Wetten auflösen und Gewinne auszahlen</p>
                        
                        <div class="row">
                            <?php 
                            $activeEventsWithBets = array_filter($events, function($event) {
                                return $event['status'] === 'active' && ($event['bet_count'] ?? 0) > 0;
                            });
                            ?>
                            
                            <?php if (empty($activeEventsWithBets)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Keine aktiven Events mit Wetten zur Auflösung verfügbar.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activeEventsWithBets as $event): ?>
                                    <?php
                                    $options = $db->fetchAll("SELECT * FROM event_options WHERE event_id = :id", ['id' => $event['id']]);
                                    ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6><?= htmlspecialchars($event['title']) ?></h6>
                                                <small class="text-muted"><?= $event['bet_count'] ?> Wetten • <?= number_format($event['total_bets']) ?> Punkte</small>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" onsubmit="return confirm('Event wirklich auflösen? Dies kann nicht rückgängig gemacht werden!')">
                                                    <input type="hidden" name="action" value="resolve_event">
                                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Gewinnende Option:</label>
                                                        <?php foreach ($options as $option): ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" 
                                                                       name="winning_option" value="<?= $option['id'] ?>" 
                                                                       id="option_<?= $option['id'] ?>" required>
                                                                <label class="form-check-label" for="option_<?= $option['id'] ?>">
                                                                    <?= htmlspecialchars($option['option_text']) ?> 
                                                                    <span class="text-muted">(<?= $option['odds'] ?>x)</span>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-check me-1"></i>Event auflösen
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Tab -->
            <div class="tab-pane fade" id="stats">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $stats['total_users'] ?></div>
                            <div>Aktive User</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $stats['total_events'] ?></div>
                            <div>Gesamt Events</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $stats['total_bets'] ?></div>
                            <div>Gesamt Wetten</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= number_format($stats['total_volume']) ?></div>
                            <div>Wett-Volumen</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Points Modal -->
    <div class="modal fade" id="addPointsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Punkte hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="manage_user">
                        <input type="hidden" name="user_action" value="add_points">
                        <input type="hidden" name="user_id" id="pointsUserId">
                        
                        <p>Punkte für <strong id="pointsUsername"></strong> hinzufügen:</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Anzahl Punkte</label>
                            <input type="number" class="form-control" name="points_amount" 
                                   min="1" max="10000" value="100" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Punkte hinzufügen</button>
                    </div>
                </form>
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
        
        function showAddPointsModal(userId, username) {
            document.getElementById('pointsUserId').value = userId;
            document.getElementById('pointsUsername').textContent = username;
            new bootstrap.Modal(document.getElementById('addPointsModal')).show();
        }
    </script>
</body>
</html>