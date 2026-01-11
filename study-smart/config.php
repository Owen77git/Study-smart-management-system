<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quiz_app_db');

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Initialize session
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'none';
    $_SESSION['user_id'] = 1;
    $_SESSION['study_plan'] = [];
}

// Constants
define('QUESTIONS_PER_SESSION', 30);
define('MAX_STUDY_SESSIONS', 5);
define('DEFAULT_STUDY_TIME', 25); // minutes
define('BREAK_TIME', 5); // minutes

// Study Smart Theme Colors
define('PRIMARY_COLOR', '#3498db');
define('SECONDARY_COLOR', '#2c3e50');
define('SUCCESS_COLOR', '#2ecc71');
define('WARNING_COLOR', '#f39c12');
define('DANGER_COLOR', '#e74c3c');
define('INFO_COLOR', '#9b59b6');

// Helper functions
function redirect($url, $message = '') {
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
    }
    header("Location: $url");
    exit();
}

function sanitize($input) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

function getStudyProgress($user_id, $unit_id) {
    global $conn;
    $query = "SELECT * FROM study_progress WHERE user_id = ? AND unit_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $unit_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updateStudyTime($user_id, $minutes) {
    global $conn;
    $today = date('Y-m-d');
    $query = "INSERT INTO study_tracking (user_id, study_date, minutes_studied) 
              VALUES (?, ?, ?) 
              ON DUPLICATE KEY UPDATE minutes_studied = minutes_studied + ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isii", $user_id, $today, $minutes, $minutes);
    return $stmt->execute();
}

function calculateProductivity($user_id) {
    global $conn;
    $query = "SELECT 
                AVG(percentage) as avg_score,
                COUNT(*) as total_quizzes,
                SUM(CASE WHEN percentage >= 70 THEN 1 ELSE 0 END) as passed_quizzes
              FROM grades g
              JOIN question_instances qi ON g.instance_id = qi.instance_id
              WHERE qi.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
?>
