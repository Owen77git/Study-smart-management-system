<?php
require_once 'config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not student
if ($_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit;
}

// Get form data
$unit_id = $_POST['unit_id'] ?? 0;
$question_type = $_POST['question_type'] ?? '';
$answers = $_POST['answer'] ?? [];
$instance_num = $_POST['instance'] ?? 1;

// Validate
if (!$unit_id || empty($answers) || count($answers) !== 30) {
    $_SESSION['error'] = 'Please answer all 30 questions.';
    header('Location: student.php?unit=' . $unit_id . '&type=' . $question_type);
    exit;
}

// Get unit name for reference
$unit_query = $conn->prepare("SELECT unit_name FROM units WHERE unit_id = ?");
$unit_query->bind_param("i", $unit_id);
$unit_query->execute();
$unit_result = $unit_query->get_result()->fetch_assoc();
$unit_name = $unit_result['unit_name'] ?? 'Unknown Unit';

// 1. Create instance
$instance_query = "INSERT INTO question_instances (unit_id, question_type, instance_number, total_questions) VALUES (?, ?, ?, 30)";
$instance_stmt = $conn->prepare($instance_query);
$instance_stmt->bind_param("isi", $unit_id, $question_type, $instance_num);
$instance_stmt->execute();
$instance_id = $conn->insert_id;

// 2. Get random questions for this unit
if ($question_type === 'mcq') {
    $questions_query = "SELECT mcq_id, question_text, correct_answer FROM mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
} else {
    $questions_query = "SELECT non_mcq_id, question_text, correct_answer FROM non_mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
}

$questions_stmt = $conn->prepare($questions_query);
$questions_stmt->bind_param("i", $unit_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

$questions = [];
$question_ids = [];
$question_texts = [];
$correct_answers = [];

while ($row = $questions_result->fetch_assoc()) {
    if ($question_type === 'mcq') {
        $question_ids[] = $row['mcq_id'];
    } else {
        $question_ids[] = $row['non_mcq_id'];
    }
    $question_texts[] = $row['question_text'];
    $correct_answers[] = $row['correct_answer'];
}

// 3. Save answers and calculate score
$score = 0;
foreach ($answers as $order => $answer) {
    $order_num = (int)$order;
    $answer = trim($answer);
    
    if ($question_type === 'mcq') {
        // Link question to instance
        $link_stmt = $conn->prepare("INSERT INTO instance_questions (instance_id, mcq_id, question_order) VALUES (?, ?, ?)");
        $link_stmt->bind_param("iii", $instance_id, $question_ids[$order_num], $order_num + 1);
        $link_stmt->execute();
        
        // Check if answer is correct
        $is_correct = strtoupper($answer) === strtoupper($correct_answers[$order_num]);
        
        // Save answer
        $save_stmt = $conn->prepare("INSERT INTO student_answers (instance_id, question_order, mcq_id, mcq_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
        $save_stmt->bind_param("iiisi", $instance_id, $order_num + 1, $question_ids[$order_num], $answer, $is_correct);
        $save_stmt->execute();
        
        if ($is_correct) $score++;
        
    } else {
        // Link question to instance
        $link_stmt = $conn->prepare("INSERT INTO instance_questions (instance_id, non_mcq_id, question_order) VALUES (?, ?, ?)");
        $link_stmt->bind_param("iii", $instance_id, $question_ids[$order_num], $order_num + 1);
        $link_stmt->execute();
        
        // For Non-MCQ, any non-empty answer is considered correct
        $is_correct = !empty($answer);
        
        // Save answer
        $save_stmt = $conn->prepare("INSERT INTO student_answers (instance_id, question_order, non_mcq_id, non_mcq_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
        $save_stmt->bind_param("iiisi", $instance_id, $order_num + 1, $question_ids[$order_num], $answer, $is_correct);
        $save_stmt->execute();
        
        if ($is_correct) $score++;
    }
}

// 4. Save grade
$percentage = ($score / 30) * 100;
$grade_stmt = $conn->prepare("INSERT INTO grades (instance_id, score, total_questions, percentage) VALUES (?, ?, 30, ?)");
$grade_stmt->bind_param("iid", $instance_id, $score, $percentage);
$grade_stmt->execute();

// 5. Redirect to results page
header('Location: results.php?instance=' . $instance_id);
exit;
?>
