<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'instructor') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

$question_id = $_POST['question_id'] ?? 0;
$question_type = $_POST['question_type'] ?? '';
$unit_id = $_POST['unit_id'] ?? 0;

if (!$question_id || !$question_type || !$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

if ($question_type === 'mcq') {
    $question_text = $_POST['question_text'] ?? '';
    $option_a = $_POST['option_a'] ?? '';
    $option_b = $_POST['option_b'] ?? '';
    $option_c = $_POST['option_c'] ?? '';
    $option_d = $_POST['option_d'] ?? '';
    $correct_answer = $_POST['correct_answer'] ?? '';
    
    if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_answer)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    $query = "UPDATE mcq_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ? WHERE mcq_id = ? AND unit_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssii", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $question_id, $unit_id);
    
} else {
    $question_text = $_POST['question_text'] ?? '';
    $correct_answer = $_POST['correct_answer'] ?? '';
    
    if (empty($question_text) || empty($correct_answer)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    $query = "UPDATE non_mcq_questions SET question_text = ?, correct_answer = ? WHERE non_mcq_id = ? AND unit_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssii", $question_text, $correct_answer, $question_id, $unit_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>
