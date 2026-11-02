<?php
// api.php - Handles saving and serving tracked session data
header('Content-Type: application/json; charset=UTF-8');

$sessionDir = __DIR__ . '/sessions';

// ── GET Request: Serve Session Data ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token']);
    $file = $sessionDir . '/' . $token . '.json';
    if (file_exists($file)) {
        readfile($file);
    } else {
        http_response_code(404);
        echo json_encode([]);
    }
    exit;
}

// ── POST Request: Save Session Data ──
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}

// Get the raw POST data from the viewer
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['token']) && isset($data['events']) && is_array($data['events'])) {
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['token']); // Sanitize token
    $file = $sessionDir . '/' . $token . '.json';
    
    $existingEvents = [];
    if (file_exists($file)) {
        $existingEvents = json_decode(file_get_contents($file), true) ?: [];
    }
    
    // Merge new events with existing ones and save
    $mergedEvents = array_merge($existingEvents, $data['events']);
    file_put_contents($file, json_encode($mergedEvents));
    
    echo json_encode(['status' => 'success', 'saved_events' => count($data['events'])]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>