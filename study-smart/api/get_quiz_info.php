<?php
require_once '../config.php';

header('Content-Type: application/json');

$instance_id = $_GET['instance'] ?? null;

if ($instance_id) {
    $result = $conn->query("
        SELECT unit_id, question_type 
        FROM question_instances 
        WHERE instance_id = $instance_id
    ");
    
    if ($result->num_rows > 0) {
        $info = $result->fetch_assoc();
        echo json_encode($info);
    } else {
        echo json_encode(['error' => 'Instance not found']);
    }
} else {
    echo json_encode(['error' => 'No instance specified']);
}
?>
