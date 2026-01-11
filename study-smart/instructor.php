<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'instructor') {
    redirect('index.php');
}

$message = '';
$message_type = '';
$action = $_GET['action'] ?? '';

// Handle delete unit
if ($action === 'delete_unit' && isset($_GET['unit_id'])) {
    $unit_id = $_GET['unit_id'];
    
    // Check if unit has pushed questions
    $check_pushed = $conn->query("
        SELECT COUNT(*) as count FROM mcq_questions WHERE unit_id = $unit_id AND is_pushed_to_main = TRUE
        UNION ALL
        SELECT COUNT(*) as count FROM non_mcq_questions WHERE unit_id = $unit_id AND is_pushed_to_main = TRUE
    ");
    
    $has_pushed = false;
    while ($row = $check_pushed->fetch_assoc()) {
        if ($row['count'] > 0) {
            $has_pushed = true;
            break;
        }
    }
    
    if ($has_pushed) {
        $message = "✗ Cannot delete unit with published questions. Delete questions first.";
        $message_type = 'error';
    } else {
        // Delete unpushed questions first
        $conn->query("DELETE FROM mcq_questions WHERE unit_id = $unit_id AND is_pushed_to_main = FALSE");
        $conn->query("DELETE FROM non_mcq_questions WHERE unit_id = $unit_id AND is_pushed_to_main = FALSE");
        
        // Soft delete the unit (set inactive)
        $stmt = $conn->prepare("UPDATE units SET is_active = FALSE WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        
        if ($stmt->execute()) {
            $message = "✓ Unit deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "✗ Error deleting unit: " . $conn->error;
            $message_type = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle unit creation
    if (isset($_POST['add_unit'])) {
        $unit_name = trim($_POST['unit_name']);
        $unit_description = trim($_POST['unit_description'] ?? '');
        
        if (!empty($unit_name)) {
            $check = $conn->prepare("SELECT unit_id FROM units WHERE unit_name = ? AND is_active = TRUE");
            $check->bind_param("s", $unit_name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "✗ Unit already exists!";
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO units (unit_name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $unit_name, $unit_description);
                if ($stmt->execute()) {
                    $message = "✓ Unit created successfully!";
                    $message_type = 'success';
                } else {
                    $message = "✗ Error creating unit: " . $conn->error;
                    $message_type = 'error';
                }
            }
        } else {
            $message = "✗ Unit name cannot be empty!";
            $message_type = 'error';
        }
    }
  // Handle MCQ upload
if (isset($_POST['upload_mcq']) && isset($_POST['unit_id']) && isset($_POST['mcq_content'])) {
    $unit_id = $_POST['unit_id'];
    $content = trim($_POST['mcq_content']);
    $success = 0;
    $error = 0;
    
    // First, normalize line endings and split into blocks
    $content = str_replace("\r\n", "\n", $content); // Normalize line endings
    $content = preg_replace('/\n{3,}/', "\n\n", $content); // Replace 3+ newlines with 2
    
    // Split by double newlines (empty lines)
    $question_blocks = preg_split('/\n\s*\n/', $content);
    
    // Remove empty blocks
    $question_blocks = array_filter(array_map('trim', $question_blocks));
    
    // Validate minimum 30 questions
    $question_count = count($question_blocks);
    if ($question_count < 30) {
        $message = "✗ Need at least 30 questions. Found only " . $question_count . ".";
        $message_type = 'error';
    } else {
        foreach ($question_blocks as $block) {
            $lines = array_filter(explode("\n", $block));
            
            // Skip if block doesn't have enough lines
            if (count($lines) < 6) {
                $error++;
                continue;
            }
            
            // Extract question (first line)
            $question_text = trim($lines[0]);
            
            // Extract options (next 4 lines)
            $options = [];
            for ($i = 1; $i <= 4 && $i < count($lines); $i++) {
                // Remove A), B), C), D) prefix if present
                $option_line = trim($lines[$i]);
                $option_line = preg_replace('/^[A-D][).]\s*/', '', $option_line);
                $options[] = $option_line;
            }
            
            // Find correct answer (look for "Answer: X" pattern)
            $correct = '';
            foreach ($lines as $line) {
                if (preg_match('/^Answer:\s*([A-D])/i', $line, $matches)) {
                    $correct = strtoupper(trim($matches[1]));
                    break;
                }
            }
            
            // Validate we have all required data
            if (!empty($question_text) && 
                !empty($options[0]) && 
                !empty($options[1]) && 
                !empty($options[2]) && 
                !empty($options[3]) && 
                !empty($correct) && 
                in_array($correct, ['A', 'B', 'C', 'D'])) {
                
                $stmt = $conn->prepare("INSERT INTO mcq_questions (unit_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssss", $unit_id, $question_text, $options[0], $options[1], $options[2], $options[3], $correct);
                if ($stmt->execute()) {
                    $success++;
                } else {
                    $error++;
                }
            } else {
                $error++;
            }
        }
        
        if ($success > 0) {
            $message = "✓ Uploaded $success MCQ questions" . ($error > 0 ? " ($error failed)" : "");
            $message_type = 'success';
        } else {
            $message = "✗ No questions were uploaded. Please check your format.";
            $message_type = 'error';
        }
    }
}
    // Handle Non-MCQ upload
    if (isset($_POST['upload_non_mcq']) && isset($_POST['unit_id']) && isset($_POST['non_mcq_content'])) {
        $unit_id = $_POST['unit_id'];
        $content = $_POST['non_mcq_content'];
        $question_blocks = preg_split('/\n\s*\n/', trim($content));
        $success = 0;
        $error = 0;
        
        // Validate minimum 30 questions
        if (count($question_blocks) < 30) {
            $message = "✗ Need at least 30 questions. Found only " . count($question_blocks) . ".";
            $message_type = 'error';
        } else {
            foreach ($question_blocks as $block) {
                $parts = preg_split('/\n\n+/', trim($block), 2);
                if (count($parts) === 2) {
                    $question_text = trim($parts[0]);
                    $correct_answer = trim($parts[1]);
                    
                    if (!empty($question_text) && !empty($correct_answer)) {
                        $stmt = $conn->prepare("INSERT INTO non_mcq_questions (unit_id, question_text, correct_answer) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $unit_id, $question_text, $correct_answer);
                        if ($stmt->execute()) $success++;
                        else $error++;
                    } else {
                        $error++;
                    }
                } else {
                    $error++;
                }
            }
            $message = "✓ Uploaded $success Non-MCQ questions" . ($error > 0 ? " ($error failed)" : "");
            $message_type = $success > 0 ? 'success' : 'error';
        }
    }
}

// Get all active units with counts
$units = $conn->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = FALSE) as mcq_unpushed,
    (SELECT COUNT(*) FROM non_mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = FALSE) as non_mcq_unpushed,
    (SELECT COUNT(*) FROM mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = TRUE) as mcq_pushed,
    (SELECT COUNT(*) FROM non_mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = TRUE) as non_mcq_pushed
    FROM units u WHERE u.is_active = TRUE ORDER BY u.unit_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Instructor Dashboard - StudySmart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* FIXED BUTTON STYLES - NO AUTO RELOAD */
        .action-btn {
            padding: 8px 12px !important;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px !important;
            min-width: 0 !important;
            width: 100% !important;
            height: 36px !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            flex-shrink: 1;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        
        .button-group {
            max-width: 100% !important;
            overflow: hidden !important;
            padding: 12px !important;
        }
        
        .unit-card {
            max-width: 100% !important;
            overflow: hidden !important;
            min-width: 380px !important;
        }
        
        .units-grid {
            width: 100% !important;
            max-width: 100% !important;
            overflow: visible !important;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)) !important;
            gap: 20px !important;
        }
        
        .btn-full {
            width: 100% !important;
            margin-top: 6px !important;
        }
        
        /* Prevent horizontal scroll */
        body {
            overflow-x: hidden !important;
            max-width: 100vw !important;
        }
        
        .container {
            max-width: 100% !important;
            padding: 0 15px !important;
        }
        
        /* Fix button hover */
        .action-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2) !important;
        }
        
        /* Button colors */
        .primary-btn { background: linear-gradient(135deg, #3498db, #2980b9) !important; color: white !important; border: 2px solid rgba(52, 152, 219, 0.4) !important; }
        .secondary-btn { background: linear-gradient(135deg, #2c3e50, #34495e) !important; color: white !important; border: 2px solid rgba(255, 255, 255, 0.15) !important; }
        .upload-btn { background: linear-gradient(135deg, #2ecc71, #27ae60) !important; color: white !important; border: 2px solid rgba(46, 204, 113, 0.4) !important; }
        .review-btn { background: linear-gradient(135deg, #f39c12, #e67e22) !important; color: white !important; border: 2px solid rgba(243, 156, 18, 0.4) !important; }
        .push-btn { background: linear-gradient(135deg, #9b59b6, #8e44ad) !important; color: white !important; border: 2px solid rgba(155, 89, 182, 0.4) !important; }
        .delete-btn { background: linear-gradient(135deg, #e74c3c, #c0392b) !important; color: white !important; border: 2px solid rgba(231, 76, 60, 0.4) !important; }
        .success-btn { background: linear-gradient(135deg, #1abc9c, #16a085) !important; color: white !important; border: 2px solid rgba(26, 188, 156, 0.4) !important; }
        .info-btn { background: linear-gradient(135deg, #3498db, #2980b9) !important; color: white !important; border: 2px solid rgba(52, 152, 219, 0.4) !important; }
        
        /* Disabled state */
        .action-btn:disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
            transform: none !important;
        }
        
        /* Progress bars */
        .progress-container {
            margin: 15px 0 !important;
        }
        
        .progress-item {
            margin-bottom: 12px !important;
        }
        
        .progress-header {
            font-size: 12px !important;
        }
        
        /* Unit stats */
        .unit-stats {
            font-size: 11px !important;
            padding-top: 12px !important;
            margin-top: 12px !important;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- NAVBAR -->
    <div class="navbar">
        <div class="logo">StudySmart</div>
        <div class="nav-links">
            <a href="instructor.php">Dashboard</a>
            <a href="index.php">Switch Role</a>
            <button onclick="showUnitModal()" class="primary-btn" style="padding:8px 16px;border-radius:20px;font-size:13px;">
                <i class="fas fa-plus"></i> Add Unit
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message" style="background:<?php echo $message_type === 'success' ? '#2ecc71' : '#e74c3c'; ?>;color:white;padding:12px;border-radius:8px;margin:15px;text-align:center;font-weight:bold;display:flex;align-items:center;justify-content:center;gap:8px;font-size:14px;">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="content">
        <h2 style="display:flex;align-items:center;gap:10px;font-size:24px;">
            <i class="fas fa-chalkboard-teacher" style="color:#3498db;"></i>
            Instructor Dashboard
        </h2>
        <p style="font-size:14px;">Manage your units and questions</p>
        
        <!-- Quick Stats -->
        <div class="instructor-actions">
            <div style="display:flex;gap:12px;flex-wrap:wrap;width:100%;">
                <div style="flex:1;min-width:180px;background:rgba(255,255,255,0.05);padding:15px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);">
                    <div style="color:#b0b0b0;font-size:12px;margin-bottom:8px;">Total Units</div>
                    <div style="font-size:28px;font-weight:bold;color:#3498db;"><?php echo $units->num_rows; ?></div>
                </div>
                
                <?php 
                // Calculate totals
                $units_data = $conn->query("
                    SELECT 
                    (SELECT COUNT(*) FROM mcq_questions WHERE is_pushed_to_main = FALSE) as mcq_unpushed_total,
                    (SELECT COUNT(*) FROM non_mcq_questions WHERE is_pushed_to_main = FALSE) as non_mcq_unpushed_total,
                    (SELECT COUNT(*) FROM mcq_questions WHERE is_pushed_to_main = TRUE) as mcq_pushed_total,
                    (SELECT COUNT(*) FROM non_mcq_questions WHERE is_pushed_to_main = TRUE) as non_mcq_pushed_total
                ");
                $totals = $units_data->fetch_assoc();
                ?>
                
                <div style="flex:1;min-width:180px;background:rgba(255,255,255,0.05);padding:15px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);">
                    <div style="color:#b0b0b0;font-size:12px;margin-bottom:8px;">MCQ Questions</div>
                    <div style="font-size:28px;font-weight:bold;color:#2ecc71;">
                        <?php echo ($totals['mcq_unpushed_total'] + $totals['mcq_pushed_total']); ?>
                    </div>
                    <div style="font-size:11px;color:#b0b0b0;margin-top:4px;">
                        <?php echo $totals['mcq_pushed_total']; ?> published • <?php echo $totals['mcq_unpushed_total']; ?> pending
                    </div>
                </div>
                
                <div style="flex:1;min-width:180px;background:rgba(255,255,255,0.05);padding:15px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);">
                    <div style="color:#b0b0b0;font-size:12px;margin-bottom:8px;">Non-MCQ Questions</div>
                    <div style="font-size:28px;font-weight:bold;color:#9b59b6;">
                        <?php echo ($totals['non_mcq_unpushed_total'] + $totals['non_mcq_pushed_total']); ?>
                    </div>
                    <div style="font-size:11px;color:#b0b0b0;margin-top:4px;">
                        <?php echo $totals['non_mcq_pushed_total']; ?> published • <?php echo $totals['non_mcq_unpushed_total']; ?> pending
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Units Grid -->
        <div class="units-grid">
            <?php if ($units->num_rows > 0): ?>
                <?php while ($unit = $units->fetch_assoc()): 
                    $mcq_ready = $unit['mcq_unpushed'] >= 30;
                    $non_mcq_ready = $unit['non_mcq_unpushed'] >= 30;
                    $mcq_pushed_ready = $unit['mcq_pushed'] >= 30;
                    $non_mcq_pushed_ready = $unit['non_mcq_pushed'] >= 30;
                    $can_delete = ($unit['mcq_pushed'] == 0 && $unit['non_mcq_pushed'] == 0);
                ?>
                <div class="unit-card">
                    <!-- Unit Header with Delete -->
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;">
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                                <div class="unit-icon" style="font-size:2rem;">📚</div>
                                <div style="flex:1;">
                                    <h3 style="margin:0 0 4px;font-size:16px;"><?php echo htmlspecialchars($unit['unit_name']); ?></h3>
                                    <?php if (!empty($unit['description'])): ?>
                                        <p style="color:#b0b0b0;font-size:12px;line-height:1.4;"><?php echo htmlspecialchars(substr($unit['description'], 0, 80)); ?>...</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Unit Actions -->
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;">
                            <div style="display:flex;gap:4px;">
                                <button onclick="showEditUnitModal(<?php echo $unit['unit_id']; ?>, '<?php echo htmlspecialchars($unit['unit_name']); ?>', '<?php echo htmlspecialchars($unit['description'] ?? ''); ?>')" 
                                        class="action-btn secondary-btn" style="padding:4px 8px;font-size:11px;width:auto;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteUnit(<?php echo $unit['unit_id']; ?>, '<?php echo htmlspecialchars($unit['unit_name']); ?>', <?php echo $can_delete ? 'true' : 'false'; ?>)" 
                                        class="action-btn delete-btn" style="padding:4px 8px;font-size:11px;width:auto;" <?php echo !$can_delete ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <!-- Status Badges -->
                            <div style="display:flex;flex-direction:column;gap:3px;">
                                <?php if ($mcq_pushed_ready): ?>
                                    <span style="background:rgba(46, 204, 113, 0.2);color:#2ecc71;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:bold;">
                                        <i class="fas fa-check"></i> MCQ Published
                                    </span>
                                <?php endif; ?>
                                <?php if ($non_mcq_pushed_ready): ?>
                                    <span style="background:rgba(155, 89, 182, 0.2);color:#9b59b6;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:bold;">
                                        <i class="fas fa-check"></i> Non-MCQ Published
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bars -->
                    <div class="progress-container">
                        <!-- MCQ Progress -->
                        <div class="progress-item">
                            <div class="progress-header" style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:11px;">
                                <span style="color:#3498db;font-weight:600;">
                                    <i class="fas fa-list-ol"></i> MCQ Questions
                                </span>
                                <span style="color:#b0b0b0;">
                                    <?php echo $unit['mcq_unpushed']; ?>/30
                                </span>
                            </div>
                            <div class="progress-bar mcq-progress" style="height:6px;background:rgba(255,255,255,0.1);border-radius:3px;overflow:hidden;">
                                <div class="progress-fill" style="width:<?php echo min(100, ($unit['mcq_unpushed']/30)*100); ?>%;height:100%;background:#3498db;border-radius:3px;"></div>
                            </div>
                        </div>
                        
                        <!-- Non-MCQ Progress -->
                        <div class="progress-item">
                            <div class="progress-header" style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:11px;">
                                <span style="color:#9b59b6;font-weight:600;">
                                    <i class="fas fa-font"></i> Non-MCQ Questions
                                </span>
                                <span style="color:#b0b0b0;">
                                    <?php echo $unit['non_mcq_unpushed']; ?>/30
                                </span>
                            </div>
                            <div class="progress-bar non-mcq-progress" style="height:6px;background:rgba(255,255,255,0.1);border-radius:3px;overflow:hidden;">
                                <div class="progress-fill" style="width:<?php echo min(100, ($unit['non_mcq_unpushed']/30)*100); ?>%;height:100%;background:#9b59b6;border-radius:3px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons (FIXED SIZING) -->
                    <div class="button-group">
                        <!-- Upload Section -->
                        <div style="margin-bottom:10px;">
                            <div style="color:#b0b0b0;font-size:10px;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Upload</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                                <button onclick="showUploadModal('mcq', <?php echo $unit['unit_id']; ?>, '<?php echo htmlspecialchars($unit['unit_name']); ?>')" 
                                        class="action-btn upload-btn">
                                    <i class="fas fa-list-ol"></i>
                                    <span>MCQ</span>
                                </button>
                                <button onclick="showUploadModal('non_mcq', <?php echo $unit['unit_id']; ?>, '<?php echo htmlspecialchars($unit['unit_name']); ?>')" 
                                        class="action-btn success-btn">
                                    <i class="fas fa-font"></i>
                                    <span>Non-MCQ</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Review Section -->
                        <div style="margin-bottom:10px;">
                            <div style="color:#b0b0b0;font-size:10px;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Review</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                                <button onclick="reviewQuestions(<?php echo $unit['unit_id']; ?>, 'mcq')" 
                                        class="action-btn review-btn" <?php echo $unit['mcq_unpushed'] == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check-circle"></i>
                                    <span>MCQ</span>
                                    <?php if ($unit['mcq_unpushed'] > 0): ?>
                                    <span style="background:rgba(255,255,255,0.2);padding:1px 4px;border-radius:6px;font-size:9px;">
                                        <?php echo $unit['mcq_unpushed']; ?>
                                    </span>
                                    <?php endif; ?>
                                </button>
                                <button onclick="reviewQuestions(<?php echo $unit['unit_id']; ?>, 'non_mcq')" 
                                        class="action-btn info-btn" <?php echo $unit['non_mcq_unpushed'] == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check-double"></i>
                                    <span>Non-MCQ</span>
                                    <?php if ($unit['non_mcq_unpushed'] > 0): ?>
                                    <span style="background:rgba(255,255,255,0.2);padding:1px 4px;border-radius:6px;font-size:9px;">
                                        <?php echo $unit['non_mcq_unpushed']; ?>
                                    </span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Publish to Students Section -->
                        <div>
                            <div style="color:#b0b0b0;font-size:10px;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Publish</div>
                            <?php if ($mcq_ready): ?>
                            <button onclick="pushToMain(<?php echo $unit['unit_id']; ?>, 'mcq')" 
                                    class="action-btn push-btn btn-full">
                                <i class="fas fa-paper-plane"></i>
                                <span>Publish MCQ</span>
                            </button>
                            <?php else: ?>
                            <button class="action-btn secondary-btn btn-full" disabled>
                                <i class="fas fa-lock"></i>
                                <span><?php echo 30 - $unit['mcq_unpushed']; ?> more MCQ</span>
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($non_mcq_ready): ?>
                            <button onclick="pushToMain(<?php echo $unit['unit_id']; ?>, 'non_mcq')" 
                                    class="action-btn push-btn btn-full" style="margin-top:6px;">
                                <i class="fas fa-paper-plane"></i>
                                <span>Publish Non-MCQ</span>
                            </button>
                            <?php else: ?>
                            <button class="action-btn secondary-btn btn-full" style="margin-top:6px;" disabled>
                                <i class="fas fa-lock"></i>
                                <span><?php echo 30 - $unit['non_mcq_unpushed']; ?> more</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Unit Stats -->
                    <div class="unit-stats">
                        <div>
                            <i class="fas fa-list-ol" style="color:#3498db;font-size:11px;"></i>
                            <span style="font-size:11px;"><?php echo $unit['mcq_unpushed']; ?> pending • <?php echo $unit['mcq_pushed']; ?> published</span>
                        </div>
                        <div>
                            <i class="fas fa-font" style="color:#9b59b6;font-size:11px;"></i>
                            <span style="font-size:11px;"><?php echo $unit['non_mcq_unpushed']; ?> pending • <?php echo $unit['non_mcq_pushed']; ?> published</span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center;padding:50px 20px;grid-column:1/-1;background:rgba(255,255,255,0.05);border-radius:12px;border:2px dashed rgba(255,255,255,0.1);">
                    <div style="font-size:48px;margin-bottom:15px;color:#7f8c8d;">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3 style="margin-bottom:10px;color:#b0b0b0;font-size:18px;">No Learning Units Yet</h3>
                    <p style="color:#7f8c8d;margin-bottom:25px;max-width:350px;margin-left:auto;margin-right:auto;font-size:14px;">
                        Create your first unit to start adding questions and quizzes for students.
                    </p>
                    <button onclick="showUnitModal()" class="action-btn primary-btn" style="padding:12px 24px;font-size:14px;">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Your First Unit</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Unit Modal -->
<div id="unitModal" class="modal">
    <div class="modal-content">
        <h3 style="display:flex;align-items:center;gap:10px;font-size:18px;">
            <i class="fas fa-plus-circle" style="color:#3498db;"></i>
            Create New Unit
        </h3>
        <form method="POST" id="unitForm" onsubmit="return validateUnitForm()">
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:6px;color:#b0b0b0;font-size:13px;">
                    <i class="fas fa-book"></i> Unit Name
                </label>
                <input type="text" name="unit_name" placeholder="e.g., Biology Unit 1, Chemistry Basics" 
                       required style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:5px;font-size:14px;">
                <div style="color:#7f8c8d;font-size:11px;margin-top:4px;">Give your unit a descriptive name</div>
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:6px;color:#b0b0b0;font-size:13px;">
                    <i class="fas fa-align-left"></i> Description (Optional)
                </label>
                <textarea name="unit_description" placeholder="Brief description of what this unit covers..." 
                          rows="3" style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:5px;font-size:14px;"></textarea>
            </div>
            
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="closeModal()" class="action-btn secondary-btn" style="flex:1;">
                    <i class="fas fa-times"></i>
                    <span>Cancel</span>
                </button>
                <button type="submit" name="add_unit" class="action-btn primary-btn" style="flex:1;">
                    <i class="fas fa-check"></i>
                    <span>Create Unit</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Unit Modal -->
<div id="editUnitModal" class="modal">
    <div class="modal-content">
        <h3 style="display:flex;align-items:center;gap:10px;font-size:18px;">
            <i class="fas fa-edit" style="color:#f39c12;"></i>
            Edit Unit
        </h3>
        <form id="editUnitForm">
            <input type="hidden" id="editUnitId" name="unit_id">
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:6px;color:#b0b0b0;font-size:13px;">
                    <i class="fas fa-book"></i> Unit Name
                </label>
                <input type="text" id="editUnitName" name="unit_name" required 
                       style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:5px;font-size:14px;">
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:6px;color:#b0b0b0;font-size:13px;">
                    <i class="fas fa-align-left"></i> Description (Optional)
                </label>
                <textarea id="editUnitDescription" name="unit_description" rows="3" 
                          style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:5px;font-size:14px;"></textarea>
            </div>
            
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="closeModal()" class="action-btn secondary-btn" style="flex:1;">
                    <i class="fas fa-times"></i>
                    <span>Cancel</span>
                </button>
                <button type="button" onclick="saveUnitEdit()" class="action-btn primary-btn" style="flex:1;">
                    <i class="fas fa-save"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Upload Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle" style="display:flex;align-items:center;gap:10px;">
            <i class="fas fa-upload"></i>
            Upload Questions
        </h3>
        <form method="POST" id="uploadForm">
            <input type="hidden" name="unit_id" id="modalUnitId">
            
            <div id="uploadContent">
                <!-- Dynamic content will be loaded here -->
            </div>
            
            <div id="questionCounter" style="margin-top:15px;padding:10px;background:rgba(52, 152, 219, 0.1);border-radius:8px;display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <i class="fas fa-list-ol" style="color:#3498db;"></i>
                        <span style="color:#b0b0b0;font-size:14px;">Questions Detected: </span>
                        <span id="questionCount" style="color:white;font-weight:bold;">0</span>
                    </div>
                    <div id="questionStatus" style="font-size:12px;padding:4px 10px;border-radius:12px;"></div>
                </div>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:25px;">
                <button type="button" onclick="closeModal()" class="action-btn secondary-btn" style="flex:1;padding:12px;">
                    <i class="fas fa-times"></i>
                    <span>Cancel</span>
                </button>
                <button type="submit" id="uploadSubmitBtn" class="action-btn primary-btn" style="flex:1;padding:12px;" disabled>
                    <i class="fas fa-upload"></i>
                    <span>Upload</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Floating Action Button -->
<button onclick="showUnitModal()" class="fab pulse" style="background:linear-gradient(135deg, #3498db, #2980b9);">
    <i class="fas fa-plus"></i>
</button>

<script>
// Modal Functions
function showUnitModal() {
    document.getElementById('unitModal').style.display = 'flex';
}

function showEditUnitModal(unitId, unitName, unitDescription) {
    document.getElementById('editUnitId').value = unitId;
    document.getElementById('editUnitName').value = unitName;
    document.getElementById('editUnitDescription').value = unitDescription;
    document.getElementById('editUnitModal').style.display = 'flex';
}

function showUploadModal(type, unitId, unitName) {
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('uploadContent');
    const submitBtn = document.getElementById('uploadSubmitBtn');
    
    title.innerHTML = `<i class="fas fa-upload"></i> Upload ${type === 'mcq' ? 'MCQ' : 'Non-MCQ'} for <span style="color:#3498db">${unitName}</span>`;
    document.getElementById('modalUnitId').value = unitId;
    
    if (type === 'mcq') {
        content.innerHTML = `
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:10px;color:#b0b0b0;font-size:14px;">
                    <i class="fas fa-paste"></i> Paste MCQ Questions (Minimum 30)
                </label>
                <textarea name="mcq_content" id="mcqTextarea" rows="14" placeholder="Paste your MCQ questions here...

Format Example:
What is the capital of France?
A) London
B) Berlin
C) Paris
D) Madrid
Answer: C

Which planet is known as the Red Planet?
A) Earth
B) Mars
C) Jupiter
D) Saturn
Answer: B

Important:
• Each question block separated by one empty line
• Options must start with A), B), C), D)
• Answer line must be exactly: 'Answer: X' (where X is A, B, C, or D)
• Need at least 30 questions" 
                style="width:100%;padding:15px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-family:monospace;font-size:13px;"></textarea>
                <div style="color:#f39c12;font-size:12px;margin-top:8px;display:flex;align-items:center;gap:5px;">
                    <i class="fas fa-lightbulb"></i>
                    <span>System will auto-extract answers from 'Answer: X' format</span>
                </div>
            </div>
        `;
        submitBtn.name = 'upload_mcq';
    } else {
        content.innerHTML = `
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:10px;color:#b0b0b0;font-size:14px;">
                    <i class="fas fa-paste"></i> Paste Non-MCQ Questions (Minimum 30)
                </label>
                <textarea name="non_mcq_content" id="nonMcqTextarea" rows="14" placeholder="Paste your Non-MCQ questions here...

Format Example:
Explain the process of photosynthesis.

Plants convert sunlight, water, and carbon dioxide into glucose and oxygen.

Describe Newton's First Law of Motion.

An object at rest stays at rest, and an object in motion stays in motion unless acted upon by an external force.

Important:
• Each question block separated by TWO empty lines
• Question and answer separated by ONE empty line
• Need at least 30 questions"
                style="width:100%;padding:15px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;font-family:monospace;font-size:13px;"></textarea>
                <div style="color:#f39c12;font-size:12px;margin-top:8px;display:flex;align-items:center;gap:5px;">
                    <i class="fas fa-lightbulb"></i>
                    <span>System separates questions and answers using empty lines</span>
                </div>
            </div>
        `;
        submitBtn.name = 'upload_non_mcq';
    }
    
    document.getElementById('uploadModal').style.display = 'flex';
    document.getElementById('questionCounter').style.display = 'block';
    submitBtn.disabled = true;
    
    // Setup textarea listener
    const textarea = type === 'mcq' ? document.getElementById('mcqTextarea') : document.getElementById('nonMcqTextarea');
    textarea.addEventListener('input', function() {
        validateQuestionCount(type, this.value);
    });
}

function validateQuestionCount(type, content) {
    let questionCount = 0;
    
    if (type === 'mcq') {
        // Count MCQ questions (separated by double newlines)
        questionCount = content.trim().split('\n\n').filter(block => block.trim().length > 0).length;
    } else {
        // Count Non-MCQ questions (separated by double newlines with content)
        questionCount = content.trim().split(/\n\s*\n/).filter(block => {
            const parts = block.split(/\n\n+/);
            return parts.length >= 2 && parts[0].trim() && parts[1].trim();
        }).length;
    }
    
    document.getElementById('questionCount').textContent = questionCount;
    const submitBtn = document.getElementById('uploadSubmitBtn');
    const statusDiv = document.getElementById('questionStatus');
    
    if (questionCount >= 30) {
        submitBtn.disabled = false;
        statusDiv.style.background = 'rgba(46, 204, 113, 0.2)';
        statusDiv.style.color = '#2ecc71';
        statusDiv.innerHTML = `<i class="fas fa-check"></i> Ready to upload`;
    } else if (questionCount > 0) {
        submitBtn.disabled = true;
        statusDiv.style.background = 'rgba(243, 156, 18, 0.2)';
        statusDiv.style.color = '#f39c12';
        statusDiv.innerHTML = `<i class="fas fa-exclamation"></i> Need ${30 - questionCount} more`;
    } else {
        submitBtn.disabled = true;
        statusDiv.style.background = 'rgba(149, 165, 166, 0.2)';
        statusDiv.style.color = '#95a5a6';
        statusDiv.innerHTML = `<i class="fas fa-info"></i> Enter questions`;
    }
}

function reviewQuestions(unitId, type) {
    window.location.href = `instructor_review.php?unit_id=${unitId}&type=${type}`;
}

function pushToMain(unitId, type) {
    if (confirm(`Publish 30 ${type} questions to students? This will make them available for quizzes.`)) {
        window.location.href = `instructor_review.php?unit_id=${unitId}&type=${type}&action=push`;
    }
}

function deleteUnit(unitId, unitName, canDelete) {
    if (!canDelete) {
        alert('Cannot delete unit with published questions. Delete published questions first.');
        return;
    }
    
    if (confirm(`Delete unit "${unitName}"? This will also delete all unpublished questions.`)) {
        window.location.href = `instructor.php?action=delete_unit&unit_id=${unitId}`;
    }
}

function closeModal() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
}

// Form validation for upload forms
document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
    const textarea = this.querySelector('textarea');
    const unitId = this.querySelector('input[type="hidden"]').value;
    
    if (!unitId || unitId === '0') {
        e.preventDefault();
        alert('Please select a unit first.');
        return;
    }
    
    if (!textarea.value.trim()) {
        e.preventDefault();
        alert('Please enter some content.');
        textarea.focus();
        return;
    }
});

// Unit form validation
document.getElementById('unitForm')?.addEventListener('submit', function(e) {
    const unitName = this.querySelector('input[name="unit_name"]').value.trim();
    
    if (!unitName) {
        e.preventDefault();
        alert('Please enter a unit name.');
        return;
    }
});

// Edit unit form
document.getElementById('editUnitForm')?.addEventListener('submit', function(e) {
    const unitName = this.querySelector('#editUnitName').value.trim();
    
    if (!unitName) {
        e.preventDefault();
        alert('Please enter a unit name.');
        return;
    }
    
    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    // Submit via AJAX
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('edit_unit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Unit updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error. Please try again.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Button hover effects
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            if (!this.disabled) {
                this.style.transform = 'translateY(-3px)';
            }
        });
        
        btn.addEventListener('mouseleave', function() {
            if (!this.disabled) {
                this.style.transform = 'translateY(0)';
            }
        });
    });
});

// Auto-show units if coming from index
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('show_units')) {
    document.querySelector('.units-grid').scrollIntoView({ behavior: 'smooth' });
}
</script>

<style>
.fab {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
    z-index: 100;
    transition: all 0.3s ease;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: 0 10px 30px rgba(52, 152, 219, 0.6);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.fab.pulse {
    animation: pulse 2s infinite;
}
</style>
</body>
</html>
