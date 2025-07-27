<?php
/**
 * Categories API - Lade dynamische Kategorien
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Lade Kategorien aus JSON-Datei
    $categoriesFile = '../data/categories.json';
    $categories = [];
    
    if (file_exists($categoriesFile)) {
        $categoriesData = file_get_contents($categoriesFile);
        $categories = json_decode($categoriesData, true) ?? [];
    }
    
    // Standard-Icons für verschiedene Kategorie-Types
    $defaultIcons = [
        'battle' => 'fas fa-fist-raised',
        'charts' => 'fas fa-chart-line', 
        'streaming' => 'fas fa-play',
        'tour' => 'fas fa-microphone',
        'awards' => 'fas fa-trophy',
        'general' => 'fas fa-star',
        'freestyle' => 'fas fa-magic',
        'cypher' => 'fas fa-circle',
        'album' => 'fas fa-compact-disc',
        'single' => 'fas fa-music',
        'collaboration' => 'fas fa-handshake',
        'feature' => 'fas fa-users',
        'live' => 'fas fa-broadcast-tower',
        'podcast' => 'fas fa-podcast',
        'interview' => 'fas fa-microphone-alt',
        'news' => 'fas fa-newspaper'
    ];
    
    // Erstelle Array mit Icon-Zuordnung
    $result = [];
    foreach ($categories as $key => $name) {
        $icon = $defaultIcons[$key] ?? 'fas fa-tag';
        $result[] = [
            'key' => $key,
            'name' => $name,
            'icon' => $icon
        ];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Laden der Kategorien: ' . $e->getMessage()
    ]);
}
?>