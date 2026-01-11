<?php
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Submit quiz request\n", FILE_APPEND);
file_put_contents('debug.log', "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

if ($_SESSION['role'] !== 'student') {
    file_put_contents('debug.log', "Unauthorized access\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

// Log session and post data
file_put_contents('debug.log', "Session role: " . $_SESSION['role'] . "\n", FILE_APPEND);
file_put_contents('debug.log', "Unit ID: " . ($_POST['unit_id'] ?? 'not set') . "\n", FILE_APPEND);

$unit_id = $_POST['unit_id'] ?? 0;
$question_type = $_POST['question_type'] ?? '';
$answers_json = $_POST['answers'] ?? '[]';
$instance_num = $_POST['instance'] ?? 1;

file_put_contents('debug.log', "Answers JSON: " . $answers_json . "\n", FILE_APPEND);

// Decode the JSON answers
$answers = json_decode($answers_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    file_put_contents('debug.log', "JSON decode error: " . json_last_error_msg() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format: ' . json_last_error_msg()]);
    exit;
}

if (!$unit_id) {
    file_put_contents('debug.log', "No unit ID\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'No unit selected']);
    exit;
}

if (empty($answers) || !is_array($answers)) {
    file_put_contents('debug.log', "No answers or invalid format\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'No answers submitted']);
    exit;
}

if (count($answers) !== 30) {
    file_put_contents('debug.log', "Wrong number of answers: " . count($answers) . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Please answer all 30 questions. Found ' . count($answers) . ' answers.']);
    exit;
}

try {
    // Create instance
    $instance_query = "INSERT INTO question_instances (unit_id, question_type, instance_number, total_questions) VALUES (?, ?, ?, 30)";
    $instance_stmt = $conn->prepare($instance_query);
    $instance_stmt->bind_param("isi", $unit_id, $question_type, $instance_num);
    
    if (!$instance_stmt->execute()) {
        throw new Exception("Failed to create instance: " . $conn->error);
    }
    
    $instance_id = $conn->insert_id;
    file_put_contents('debug.log', "Created instance ID: $instance_id\n", FILE_APPEND);

    // Get questions for this instance
    if ($question_type === 'mcq') {
        $questions_query = "SELECT mcq_id FROM mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
    } else {
        $questions_query = "SELECT non_mcq_id FROM non_mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
    }

    $questions_stmt = $conn->prepare($questions_query);
    $questions_stmt->bind_param("i", $unit_id);
    
    if (!$questions_stmt->execute()) {
        throw new Exception("Failed to get questions: " . $conn->error);
    }
    
    $questions_result = $questions_stmt->get_result();
    $question_ids = [];
    
    while ($row = $questions_result->fetch_assoc()) {
        $question_ids[] = $question_type === 'mcq' ? $row['mcq_id'] : $row['non_mcq_id'];
    }
    
    file_put_contents('debug.log', "Got " . count($question_ids) . " question IDs\n", FILE_APPEND);

    // Save answers and link questions
    $score = 0;
    foreach ($answers as $order => $answer) {
        $order_num = (int)$order;
        $answer = trim($answer);
        
        if ($question_type === 'mcq') {
            // Link question to instance
            $link_stmt = $conn->prepare("INSERT INTO instance_questions (instance_id, mcq_id, question_order) VALUES (?, ?, ?)");
            $link_stmt->bind_param("iii", $instance_id, $question_ids[$order_num], $order_num + 1);
            
            if (!$link_stmt->execute()) {
                throw new Exception("Failed to link MCQ question: " . $conn->error);
            }
            
            // Get correct answer
            $correct_stmt = $conn->prepare("SELECT correct_answer FROM mcq_questions WHERE mcq_id = ?");
            $correct_stmt->bind_param("i", $question_ids[$order_num]);
            
            if (!$correct_stmt->execute()) {
                throw new Exception("Failed to get correct answer: " . $conn->error);
            }
            
            $correct_result = $correct_stmt->get_result()->fetch_assoc();
            $is_correct = strtoupper($answer) === strtoupper($correct_result['correct_answer'] ?? '');
            
            // Save answer
            $save_stmt = $conn->prepare("INSERT INTO student_answers (instance_id, question_order, mcq_id, mcq_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
            $save_stmt->bind_param("iiisi", $instance_id, $order_num + 1, $question_ids[$order_num], $answer, $is_correct);
            
            if (!$save_stmt->execute()) {
                throw new Exception("Failed to save MCQ answer: " . $conn->error);
            }
            
            if ($is_correct) $score++;
            
        } else {
            // Link question to instance
            $link_stmt = $conn->prepare("INSERT INTO instance_questions (instance_id, non_mcq_id, question_order) VALUES (?, ?, ?)");
            $link_stmt->bind_param("iii", $instance_id, $question_ids[$order_num], $order_num + 1);
            
            if (!$link_stmt->execute()) {
                throw new Exception("Failed to link Non-MCQ question: " . $conn->error);
            }
            
            // For Non-MCQ, consider any non-empty answer as correct
            $is_correct = !empty($answer);
            
            // Save answer
            $save_stmt = $conn->prepare("INSERT INTO student_answers (instance_id, question_order, non_mcq_id, non_mcq_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
            $save_stmt->bind_param("iiisi", $instance_id, $order_num + 1, $question_ids[$order_num], $answer, $is_correct);
            
            if (!$save_stmt->execute()) {
                throw new Exception("Failed to save Non-MCQ answer: " . $conn->error);
            }
            
            if ($is_correct) $score++;
        }
    }

    // Save grade
    $percentage = ($score / 30) * 100;
    $grade_stmt = $conn->prepare("INSERT INTO grades (instance_id, score, total_questions, percentage) VALUES (?, ?, 30, ?)");
    $grade_stmt->bind_param("iid", $instance_id, $score, $percentage);
    
    if (!$grade_stmt->execute()) {
        throw new Exception("Failed to save grade: " . $conn->error);
    }
    
    file_put_contents('debug.log', "Quiz submitted successfully. Score: $score/30 ($percentage%)\n", FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quiz submitted successfully',
        'score' => $score,
        'percentage' => number_format($percentage, 1),
        'instance_id' => $instance_id
    ]);
    
} catch (Exception $e) {
    file_put_contents('debug.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
