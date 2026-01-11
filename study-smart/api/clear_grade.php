<?php
require_once '../config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$instance_id = $data['instance_id'] ?? null;

if ($instance_id) {
    // Clear student answers for this instance
    $conn->query("DELETE FROM student_answers WHERE instance_id = $instance_id");
    
    // Clear grade
    $conn->query("DELETE FROM grades WHERE instance_id = $instance_id");
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No instance ID provided']);
}
?>
