<?php
/**
 * Cleanup Setup Directory
 * Optional security endpoint to remove setup files after installation
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $setupDir = __DIR__ . '/../setup/';
    $setupFile = __DIR__ . '/../setup.php';
    
    $deleted = [];
    $errors = [];
    
    // Delete setup directory recursively
    if (is_dir($setupDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($setupDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            if ($todo($fileinfo->getRealPath())) {
                $deleted[] = $fileinfo->getRealPath();
            } else {
                $errors[] = 'Could not delete: ' . $fileinfo->getRealPath();
            }
        }
        
        if (rmdir($setupDir)) {
            $deleted[] = $setupDir;
        } else {
            $errors[] = 'Could not delete setup directory';
        }
    }
    
    // Delete setup.php
    if (file_exists($setupFile)) {
        if (unlink($setupFile)) {
            $deleted[] = $setupFile;
        } else {
            $errors[] = 'Could not delete setup.php';
        }
    }
    
    if (empty($errors)) {
        echo json_encode([
            'success' => true,
            'message' => 'Setup files deleted successfully',
            'deleted' => count($deleted)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Some files could not be deleted',
            'errors' => $errors,
            'deleted' => count($deleted)
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>