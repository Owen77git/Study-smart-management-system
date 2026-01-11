<?php
require_once '../config.php';

header('Content-Type: application/json');

$today = date('Y-m-d');

// Calculate daily total minutes
$result = $conn->query("
    SELECT 
        SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))) as daily_minutes,
        TIMESTAMPDIFF(MINUTE, MAX(start_time), NOW()) as session_minutes
    FROM usage_tracking 
    WHERE session_date = '$today'
");

$data = $result->fetch_assoc();

echo json_encode([
    'daily_minutes' => $data['daily_minutes'] ?? 0,
    'session_minutes' => $data['session_minutes'] ?? 0
]);
?>
