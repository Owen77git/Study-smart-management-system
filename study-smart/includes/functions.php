<?php
// includes/functions.php

/**
 * Get count of questions for a unit
 */
function getUnitQuestionsCount($conn, $unit_id, $type) {
    $table = ($type === 'mcq') ? 'mcq_questions' : 'non_mcq_questions';
    $query = "SELECT COUNT(*) as count FROM $table WHERE unit_id = ? AND is_pushed_to_main = FALSE";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    return $count;
}

/**
 * Get all available units with counts
 */
function getAvailableUnits($conn) {
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM mcq_questions WHERE unit_id = u.unit_id) as mcq_count,
              (SELECT COUNT(*) FROM non_mcq_questions WHERE unit_id = u.unit_id) as non_mcq_count
              FROM units u WHERE u.is_active = TRUE ORDER BY u.unit_name";
    
    $result = $conn->query($query);
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    return $units;
}

/**
 * Get next instance number for a unit and type
 */
function getInstanceNumber($conn, $unit_id, $type) {
    $query = "SELECT MAX(instance_number) as max_instance FROM question_instances WHERE unit_id = ? AND question_type = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $unit_id, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return ($row['max_instance'] ?? 0) + 1;
}

/**
 * Check if Non-MCQ answer is correct (keyword matching)
 */
function checkNonMCQAnswer($student_answer, $correct_answer) {
    if (empty(trim($student_answer))) return false;
    
    $student_lower = strtolower(trim($student_answer));
    $correct_lower = strtolower(trim($correct_answer));
    
    // Extract meaningful words (4+ characters)
    preg_match_all('/\b\w{4,}\b/', $correct_lower, $keywords);
    $keywords = array_unique($keywords[0]);
    
    // Remove very common words
    $common_words = ['that', 'this', 'with', 'from', 'have', 'were', 'they', 'which'];
    $keywords = array_diff($keywords, $common_words);
    
    if (empty($keywords)) {
        // If no keywords found, do simple string comparison
        similar_text($student_lower, $correct_lower, $similarity);
        return $similarity > 60;
    }
    
    // Check for keyword matches
    $match_count = 0;
    foreach ($keywords as $keyword) {
        if (strpos($student_lower, $keyword) !== false) {
            $match_count++;
        }
    }
    
    // Require at least 60% of keywords or 2 keywords minimum
    $threshold = max(2, count($keywords) * 0.6);
    return $match_count >= $threshold;
}

/**
 * Get student's progress statistics
 */
function getStudentProgress($conn, $user_id = 1) {
    $stats = [];
    
    // Total questions answered
    $result = $conn->query("SELECT COUNT(*) as total FROM student_answers");
    $stats['total_answered'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Average score
    $result = $conn->query("SELECT AVG(percentage) as avg_score FROM grades");
    $stats['average_score'] = round($result->fetch_assoc()['avg_score'] ?? 0, 1);
    
    // Today's study time
    $today = date('Y-m-d');
    $result = $conn->query("
        SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))) as today_minutes 
        FROM usage_tracking 
        WHERE session_date = '$today'
    ");
    $stats['today_minutes'] = $result->fetch_assoc()['today_minutes'] ?? 0;
    
    // Total units studied
    $result = $conn->query("SELECT COUNT(DISTINCT unit_id) as units FROM question_instances");
    $stats['units_studied'] = $result->fetch_assoc()['units'] ?? 0;
    
    return $stats;
}

/**
 * Generate a unique quiz instance
 */
function generateQuizInstance($conn, $unit_id, $type) {
    $instance_num = getInstanceNumber($conn, $unit_id, $type);
    
    // Create instance
    $stmt = $conn->prepare("INSERT INTO question_instances (unit_id, question_type, instance_number, total_questions) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $unit_id, $type, $instance_num, QUESTIONS_PER_SESSION);
    $stmt->execute();
    $instance_id = $conn->insert_id;
    $stmt->close();
    
    // Get random questions
    if ($type === 'mcq') {
        $query = "SELECT mcq_id FROM mcq_questions WHERE unit_id = ? ORDER BY RAND() LIMIT ?";
    } else {
        $query = "SELECT non_mcq_id FROM non_mcq_questions WHERE unit_id = ? ORDER BY RAND() LIMIT ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $unit_id, QUESTIONS_PER_SESSION);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Link questions
    $order = 1;
    while ($row = $result->fetch_assoc()) {
        if ($type === 'mcq') {
            $link_stmt = $conn->prepare("INSERT INTO instance_questions (instance_id, mcq_id, question_order) VALUES (?, ?, ?)");
            $link_stmt->bind_param("iii", $instance_id, $row['mcq_id'], $order);
        } else {
            $link_stmt = $conn->prepare("INSERT INTO instance_questions (instance_id, non_mcq_id, question_order) VALUES (?, ?, ?)");
            $link_stmt->bind_param("iii", $instance_id, $row['non_mcq_id'], $order);
        }
        $link_stmt->execute();
        $link_stmt->close();
        $order++;
    }
    
    $stmt->close();
    return $instance_id;
}

/**
 * Log student activity
 */
function logActivity($conn, $activity, $details = '') {
    $user_id = $_SESSION['user_id'] ?? 1;
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $activity, $details);
    $stmt->execute();
    $stmt->close();
}
?>
