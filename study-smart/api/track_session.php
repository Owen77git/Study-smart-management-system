<?php
require_once '../config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? 'start';
$today = date('Y-m-d');

if ($action === 'start') {
    // Check if session already exists for today
    $check = $conn->query("SELECT track_id FROM usage_tracking WHERE session_date = '$today' AND end_time IS NULL LIMIT 1");
    
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO usage_tracking (session_date, start_time) VALUES ('$today', NOW())");
    }
    
    echo json_encode(['success' => true]);
    
} elseif ($action === 'reset') {
    // Update existing session end time
    $conn->query("UPDATE usage_tracking SET end_time = NOW() WHERE session_date = '$today' AND end_time IS NULL");
    
    // Start new session
    $conn->query("INSERT INTO usage_tracking (session_date, start_time) VALUES ('$today', NOW())");
    
    echo json_encode(['success' => true]);
    
} elseif ($action === 'end') {
    $conn->query("UPDATE usage_tracking SET end_time = NOW() WHERE session_date = '$today' AND end_time IS NULL");
    echo json_encode(['success' => true]);
}
?>
