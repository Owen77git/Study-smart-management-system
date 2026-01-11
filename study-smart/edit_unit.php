<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'instructor') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

$unit_id = $_POST['unit_id'] ?? 0;
$unit_name = trim($_POST['unit_name'] ?? '');
$unit_description = trim($_POST['unit_description'] ?? '');

if (!$unit_id || empty($unit_name)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Check if new name already exists (excluding current unit)
$check = $conn->prepare("SELECT unit_id FROM units WHERE unit_name = ? AND unit_id != ? AND is_active = TRUE");
$check->bind_param("si", $unit_name, $unit_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Unit name already exists']);
    exit;
}

// Update unit
$stmt = $conn->prepare("UPDATE units SET unit_name = ?, description = ? WHERE unit_id = ?");
$stmt->bind_param("ssi", $unit_name, $unit_description, $unit_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Unit updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>
