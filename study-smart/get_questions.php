<?php
require_once 'config.php';

$unit_id = $_GET['unit_id'] ?? 0;
$type = $_GET['type'] ?? 'mcq';

header('Content-Type: application/json');

if (!$unit_id || !in_array($type, ['mcq', 'non_mcq'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if ($type === 'mcq') {
    $query = "SELECT * FROM mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
} else {
    $query = "SELECT * FROM non_mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

if (count($questions) < 30) {
    echo json_encode(['success' => false, 'message' => 'Not enough questions available']);
} else {
    echo json_encode(['success' => true, 'questions' => $questions]);
}
?>
