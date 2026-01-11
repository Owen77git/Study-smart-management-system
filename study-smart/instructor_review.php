<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'instructor') {
    redirect('index.php');
}

$unit_id = $_GET['unit_id'] ?? 0;
$type = $_GET['type'] ?? 'mcq'; // 'mcq' or 'non_mcq'
$action = $_GET['action'] ?? '';

// Get unit info
$unit_query = $conn->prepare("SELECT unit_name FROM units WHERE unit_id = ?");
$unit_query->bind_param("i", $unit_id);
$unit_query->execute();
$unit_result = $unit_query->get_result();
$unit = $unit_result->fetch_assoc();
$unit_name = $unit['unit_name'] ?? 'Unknown Unit';

// Get questions for review (not pushed yet)
if ($type === 'mcq') {
    $query = "SELECT * FROM mcq_questions WHERE unit_id = ? AND is_pushed_to_main = FALSE ORDER BY created_at";
    $id_field = 'mcq_id';
} else {
    $query = "SELECT * FROM non_mcq_questions WHERE unit_id = ? AND is_pushed_to_main = FALSE ORDER BY created_at";
    $id_field = 'non_mcq_id';
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_questions = count($questions);

// Handle push action
if ($action === 'push' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($total_questions < 30) {
        $message = "Need 30 questions to push. Only $total_questions available.";
        $message_type = 'error';
    } else {
    // Get unit info with ALL counts
$unit_query = $conn->prepare("
    SELECT u.*, 
    (SELECT COUNT(*) FROM mcq_questions WHERE unit_id = u.unit_id) as mcq_total,
    (SELECT COUNT(*) FROM non_mcq_questions WHERE unit_id = u.unit_id) as non_mcq_total,
    (SELECT COUNT(*) FROM mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = TRUE) as mcq_pushed,
    (SELECT COUNT(*) FROM non_mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = TRUE) as non_mcq_pushed
    FROM units u WHERE u.unit_id = ?
");
$unit_query->bind_param("i", $unit_id);
$unit_query->execute();
$unit_info = $unit_query->get_result()->fetch_assoc();
$unit_name = $unit_info['unit_name'] ?? 'Unknown Unit';
        // Get 30 random questions
        $random_ids = array_column(array_slice($questions, 0, 30), $id_field);
        $ids_str = implode(',', $random_ids);
        
        // Mark as pushed
        $update_query = "UPDATE " . ($type === 'mcq' ? 'mcq_questions' : 'non_mcq_questions') 
                      . " SET is_pushed_to_main = TRUE WHERE $id_field IN ($ids_str)";
        
        if ($conn->query($update_query)) {
            $message = "30 $type questions pushed to students successfully!";
            $message_type = 'success';
            
            // Create instance for students
            $instance_num = 1;
            $check_instance = $conn->prepare("SELECT MAX(instance_number) as max_num FROM question_instances WHERE unit_id = ? AND question_type = ?");
            $check_instance->bind_param("is", $unit_id, $type);
            $check_instance->execute();
            $instance_result = $check_instance->get_result();
            if ($row = $instance_result->fetch_assoc()) {
                $instance_num = $row['max_num'] + 1;
            }
            
            $instance_stmt = $conn->prepare("INSERT INTO question_instances (unit_id, question_type, instance_number, total_questions) VALUES (?, ?, ?, 30)");
            $instance_stmt->bind_param("isi", $unit_id, $type, $instance_num, 30);
            $instance_stmt->execute();
            
            // Redirect after success
            echo '<script>setTimeout(function(){ window.location.href = "instructor.php"; }, 2000);</script>';
        } else {
            $message = "Error pushing questions: " . $conn->error;
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Questions - StudySmart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container">
    <!-- NAVBAR -->
    <div class="navbar">
        <div class="logo">StudySmart</div>
        <div class="nav-links">
            <a href="instructor.php">Dashboard</a>
            <a href="#" onclick="history.back()">Back</a>
        </div>
    </div>

    <!-- Message -->
    <?php if (isset($message)): ?>
    <div class="message" style="background:<?php echo $message_type === 'success' ? '#2ecc71' : '#e74c3c'; ?>;color:white;padding:15px;border-radius:10px;margin:20px;text-align:center;font-weight:bold;">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <div class="content">
        <!-- Review Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);">
            <div>
                <h2>
                    <i class="fas fa-check-circle" style="color:#f39c12;"></i>
                    Review <?php echo strtoupper($type); ?> Questions
                </h2>
                <p style="color:#b0b0b0;margin-top:5px;">
                    Unit: <span style="color:#3498db;font-weight:bold;"><?php echo htmlspecialchars($unit_name); ?></span>
                    • Total: <?php echo $total_questions; ?> questions
                </p>
            </div>
            
            <!-- Action Buttons -->
            <div style="display:flex;gap:15px;">
                <div class="status-badge <?php echo $total_questions >= 30 ? 'correct-badge' : 'incorrect-badge'; ?>" style="padding:10px 20px;">
                    <?php if ($total_questions >= 30): ?>
                        <i class="fas fa-check"></i> Ready to Push
                    <?php else: ?>
                        <i class="fas fa-times"></i> Need <?php echo 30 - $total_questions; ?> more
                    <?php endif; ?>
                </div>
                
                <?php if ($total_questions >= 30): ?>
                <form method="POST" action="instructor_review.php?unit_id=<?php echo $unit_id; ?>&type=<?php echo $type; ?>&action=push" 
                      onsubmit="return confirm('Push 30 <?php echo $type; ?> questions to students? This will make them available for quizzes.')">
                    <button type="submit" class="action-btn push-btn" style="min-width:200px;">
                        <i class="fas fa-paper-plane"></i>
                        <span>Push to Students</span>
                    </button>
                </form>
                <?php else: ?>
                <button class="action-btn secondary-btn" style="min-width:200px;" disabled>
                    <i class="fas fa-lock"></i>
                    <span>Not Enough Questions</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Type Switcher -->
        <div class="type-switcher" style="max-width:300px;margin-bottom:30px;">
            <a href="instructor_review.php?unit_id=<?php echo $unit_id; ?>&type=mcq" 
               class="type-btn <?php echo $type === 'mcq' ? 'active' : ''; ?>">
                MCQ Questions
            </a>
            <a href="instructor_review.php?unit_id=<?php echo $unit_id; ?>&type=non_mcq" 
               class="type-btn <?php echo $type === 'non_mcq' ? 'active' : ''; ?>">
                Non-MCQ
            </a>
        </div>
        
        <!-- Questions Count -->
        <div style="background:rgba(255,255,255,0.05);padding:15px;border-radius:10px;margin-bottom:25px;text-align:center;">
            <div style="font-size:14px;color:#b0b0b0;margin-bottom:5px;">Available for Review</div>
            <div style="font-size:32px;font-weight:bold;color:#3498db;">
                <?php echo $total_questions; ?> <span style="font-size:16px;color:#b0b0b0;">/ 30 needed</span>
            </div>
            <div class="percentage-bar" style="max-width:400px;margin:15px auto;">
                <div class="percentage-fill" style="width:<?php echo min(100, ($total_questions/30)*100); ?>%;"></div>
            </div>
        </div>
        
        <!-- Questions List -->
        <div style="margin-top:40px;">
            <?php if ($total_questions > 0): ?>
                <h3 style="margin-bottom:25px;color:#b0b0b0;display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-list"></i>
                    Questions List
                </h3>
                
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-card" style="margin-bottom:25px;background:#162a38;padding:25px;border-radius:15px;border-left:4px solid #f39c12;">
                    <!-- Question Header -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <div>
                            <span style="background:#f39c12;color:white;padding:5px 15px;border-radius:20px;font-weight:bold;font-size:14px;">
                                Question #<?php echo $index + 1; ?>
                            </span>
                            <?php if ($index < 30): ?>
                            <span style="background:rgba(46, 204, 113, 0.2);color:#2ecc71;padding:5px 15px;border-radius:20px;font-size:12px;margin-left:10px;">
                                <i class="fas fa-check"></i> Will be pushed
                            </span>
                            <?php else: ?>
                            <span style="background:rgba(149, 165, 166, 0.2);color:#95a5a6;padding:5px 15px;border-radius:20px;font-size:12px;margin-left:10px;">
                                <i class="fas fa-clock"></i> For next batch
                            </span>
                            <?php endif; ?>
                        </div>
                        <div style="color:#b0b0b0;font-size:12px;">
                            <?php echo date('M d, Y', strtotime($question['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                    
                    <!-- Question Body -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:bold;color:#3498db;margin-bottom:10px;font-size:14px;">QUESTION:</div>
                        <div style="font-size:16px;line-height:1.6;background:rgba(255,255,255,0.05);padding:15px;border-radius:8px;">
                            <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                        </div>
                    </div>
                    
                    <!-- Answer Section -->
                    <?php if ($type === 'mcq'): ?>
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:bold;color:#3498db;margin-bottom:10px;font-size:14px;">OPTIONS:</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                            <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                            <div style="background:<?php echo strtoupper($option) === $question['correct_answer'] ? 'rgba(46, 204, 113, 0.1)' : 'rgba(255,255,255,0.05)'; ?>;padding:15px;border-radius:8px;border:2px solid <?php echo strtoupper($option) === $question['correct_answer'] ? '#2ecc71' : 'transparent'; ?>;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="background:<?php echo strtoupper($option) === $question['correct_answer'] ? '#2ecc71' : '#7f8c8d'; ?>;color:white;width:25px;height:25px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                                        <?php echo strtoupper($option); ?>
                                    </span>
                                    <span style="<?php echo strtoupper($option) === $question['correct_answer'] ? 'color:#2ecc71;font-weight:bold;' : 'color:white;'; ?>">
                                        <?php echo htmlspecialchars($question['option_' . $option]); ?>
                                    </span>
                                    <?php if (strtoupper($option) === $question['correct_answer']): ?>
                                    <span style="margin-left:auto;background:#2ecc71;color:white;padding:3px 10px;border-radius:10px;font-size:11px;">
                                        <i class="fas fa-check"></i> Correct
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:bold;color:#3498db;margin-bottom:10px;font-size:14px;">CORRECT ANSWER:</div>
                        <div style="background:rgba(46, 204, 113, 0.1);padding:15px;border-radius:8px;border-left:4px solid #2ecc71;">
                            <?php echo nl2br(htmlspecialchars($question['correct_answer'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Question Actions -->
                    <div style="display:flex;gap:10px;margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);">
                        <button class="action-btn secondary-btn btn-small" onclick="editQuestion(<?php echo $question[$id_field]; ?>, '<?php echo $type; ?>')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="action-btn delete-btn btn-small" onclick="deleteQuestion(<?php echo $question[$id_field]; ?>, '<?php echo $type; ?>')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Pagination Info -->
                <div style="text-align:center;margin-top:40px;padding-top:30px;border-top:1px solid rgba(255,255,255,0.1);color:#b0b0b0;font-size:14px;">
                    Showing <?php echo count($questions); ?> questions
                    <?php if ($total_questions > 30): ?>
                    • First 30 will be pushed to students
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Empty State -->
                <div style="text-align:center;padding:60px 20px;">
                    <div style="font-size:48px;margin-bottom:20px;color:#7f8c8d;">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 style="margin-bottom:10px;color:#b0b0b0;">No Questions to Review</h3>
                    <p style="color:#7f8c8d;margin-bottom:25px;max-width:400px;margin-left:auto;margin-right:auto;">
                        All <?php echo $type; ?> questions have been pushed to students or you haven't uploaded any yet.
                    </p>
                    <div style="display:flex;gap:15px;justify-content:center;">
                        <a href="instructor.php" class="action-btn secondary-btn">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <button onclick="window.location.href='instructor.php?show_upload=<?php echo $unit_id; ?>'" class="action-btn upload-btn">
                            <i class="fas fa-upload"></i> Upload More Questions
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3 id="editModalTitle">Edit Question</h3>
        <form id="editForm" method="POST" action="edit_question.php">
            <input type="hidden" id="editQuestionId" name="question_id">
            <input type="hidden" id="editQuestionType" name="question_type">
            <input type="hidden" name="unit_id" value="<?php echo $unit_id; ?>">
            
            <div id="editFormContent">
                <!-- Dynamic content will be loaded here -->
            </div>
            
            <div style="display:flex;gap:10px;margin-top:30px;">
                <button type="button" onclick="closeEditModal()" class="action-btn secondary-btn" style="flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="action-btn primary-btn" style="flex:1;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editQuestion(questionId, questionType) {
    // Show loading
    document.getElementById('editFormContent').innerHTML = `
        <div style="text-align:center;padding:40px;">
            <div class="spinner" style="width:30px;height:30px;border:3px solid #3498db;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 15px;"></div>
            <div style="color:#b0b0b0;">Loading question...</div>
        </div>
    `;
    
    document.getElementById('editModalTitle').textContent = 'Edit ' + (questionType === 'mcq' ? 'MCQ' : 'Non-MCQ') + ' Question';
    document.getElementById('editQuestionId').value = questionId;
    document.getElementById('editQuestionType').value = questionType;
    document.getElementById('editModal').style.display = 'flex';
    
    // Fetch question data
    fetch(`get_question.php?id=${questionId}&type=${questionType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (questionType === 'mcq') {
                    document.getElementById('editFormContent').innerHTML = `
                        <div style="margin-bottom:15px;">
                            <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Question Text</label>
                            <textarea name="question_text" required style="width:100%;padding:12px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;" rows="4">${data.question.question_text}</textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
                            <div>
                                <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Option A</label>
                                <input type="text" name="option_a" value="${data.question.option_a}" required style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Option B</label>
                                <input type="text" name="option_b" value="${data.question.option_b}" required style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Option C</label>
                                <input type="text" name="option_c" value="${data.question.option_c}" required style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Option D</label>
                                <input type="text" name="option_d" value="${data.question.option_d}" required style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;">
                            </div>
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Correct Answer</label>
                            <select name="correct_answer" required style="width:100%;padding:10px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;">
                                <option value="A" ${data.question.correct_answer === 'A' ? 'selected' : ''}>A</option>
                                <option value="B" ${data.question.correct_answer === 'B' ? 'selected' : ''}>B</option>
                                <option value="C" ${data.question.correct_answer === 'C' ? 'selected' : ''}>C</option>
                                <option value="D" ${data.question.correct_answer === 'D' ? 'selected' : ''}>D</option>
                            </select>
                        </div>
                    `;
                } else {
                    document.getElementById('editFormContent').innerHTML = `
                        <div style="margin-bottom:15px;">
                            <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Question Text</label>
                            <textarea name="question_text" required style="width:100%;padding:12px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;" rows="4">${data.question.question_text}</textarea>
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:8px;color:#b0b0b0;font-size:14px;">Correct Answer</label>
                            <textarea name="correct_answer" required style="width:100%;padding:12px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:6px;" rows="6">${data.question.correct_answer}</textarea>
                        </div>
                    `;
                }
            } else {
                document.getElementById('editFormContent').innerHTML = `
                    <div style="text-align:center;padding:20px;color:#e74c3c;">
                        <i class="fas fa-exclamation-triangle" style="font-size:48px;margin-bottom:15px;"></i>
                        <div>Error loading question</div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('editFormContent').innerHTML = `
                <div style="text-align:center;padding:20px;color:#e74c3c;">
                    <i class="fas fa-exclamation-triangle" style="font-size:48px;margin-bottom:15px;"></i>
                    <div>Network error. Please try again.</div>
                </div>
            `;
        });
}

function deleteQuestion(questionId, questionType) {
    if (confirm('Delete this question? This action cannot be undone.')) {
        fetch(`delete_question.php?id=${questionId}&type=${questionType}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Question deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        });
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeEditModal();
    }
}

// Handle form submission
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    fetch('edit_question.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Question updated successfully!');
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
</script>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #3498db;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
</style>
</body>
</html>
