<?php
require_once 'config.php';

header('Content-Type: application/json');

$question_id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? 'mcq';

if (!$question_id || !in_array($type, ['mcq', 'non_mcq'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$table = $type === 'mcq' ? 'mcq_questions' : 'non_mcq_questions';
$id_field = $type === 'mcq' ? 'mcq_id' : 'non_mcq_id';

$query = "SELECT * FROM $table WHERE $id_field = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $question_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Question not found']);
} else {
    $question = $result->fetch_assoc();
    echo json_encode(['success' => true, 'question' => $question]);
}
?>
