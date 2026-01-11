<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'instructor') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

$question_id = $_POST['id'] ?? 0;
$type = $_POST['type'] ?? 'mcq';

if (!$question_id || !in_array($type, ['mcq', 'non_mcq'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$table = $type === 'mcq' ? 'mcq_questions' : 'non_mcq_questions';
$id_field = $type === 'mcq' ? 'mcq_id' : 'non_mcq_id';

// Check if question has been pushed to students
$check_query = "SELECT is_pushed_to_main FROM $table WHERE $id_field = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $question_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Question not found']);
    exit;
}

$question = $check_result->fetch_assoc();
if ($question['is_pushed_to_main']) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete: Question has been pushed to students']);
    exit;
}

// Delete the question
$delete_query = "DELETE FROM $table WHERE $id_field = ?";
$delete_stmt = $conn->prepare($delete_query);
$delete_stmt->bind_param("i", $question_id);

if ($delete_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>
