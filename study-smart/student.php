<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'student') {
    redirect('index.php');
}

$selected_unit = $_GET['unit'] ?? null;
$selected_type = $_GET['type'] ?? null;
$instance_num = $_GET['instance'] ?? 1;

// Get available units with question counts and study progress
$units = $conn->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = TRUE) as mcq_count,
    (SELECT COUNT(*) FROM non_mcq_questions WHERE unit_id = u.unit_id AND is_pushed_to_main = TRUE) as non_mcq_count,
    COALESCE(sp.progress_percentage, 0) as study_progress,
    COALESCE(sp.last_studied, 'Never') as last_studied
    FROM units u 
    LEFT JOIN study_progress sp ON u.unit_id = sp.unit_id AND sp.user_id = {$_SESSION['user_id']}
    WHERE u.is_active = TRUE
");

// Get today's study stats
$today = date('Y-m-d');
$study_stats = $conn->query("
    SELECT 
        COALESCE(SUM(minutes_studied), 0) as today_minutes,
        (SELECT COUNT(*) FROM study_sessions WHERE user_id = {$_SESSION['user_id']} AND DATE(session_date) = '$today') as sessions_today
    FROM study_tracking 
    WHERE user_id = {$_SESSION['user_id']} AND study_date = '$today'
")->fetch_assoc();

// If unit selected, load questions for modal
$questions = [];
if ($selected_unit && $selected_type) {
    if ($selected_type === 'mcq') {
        $query = "SELECT * FROM mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
    } else {
        $query = "SELECT * FROM non_mcq_questions WHERE unit_id = ? AND is_pushed_to_main = TRUE ORDER BY RAND() LIMIT 30";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_unit);
    $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - StudySmart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container">
    <!-- NAVBAR -->
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            StudySmart
        </div>
        <div class="nav-links">
            <a href="student.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="study_plan.php"><i class="fas fa-calendar-alt"></i> Study Plan</a>
            <a href="projects.php"><i class="fas fa-project-diagram"></i> Projects</a>
            <a href="progress.php"><i class="fas fa-chart-line"></i> Progress</a>
            <a href="results.php"><i class="fas fa-clipboard-check"></i> Results</a>
            <a href="index.php"><i class="fas fa-random"></i> Switch Role</a>
        </div>
    </div>

    <!-- Today's Focus -->
    <div class="todays-focus">
        <div class="focus-header">
            <h3><i class="fas fa-bullseye"></i> Today's Study Focus</h3>
            <div class="study-stats">
                <span class="stat-item">
                    <i class="fas fa-clock"></i>
                    <strong><?php echo $study_stats['today_minutes']; ?></strong> min studied
                </span>
                <span class="stat-item">
                    <i class="fas fa-layer-group"></i>
                    <strong><?php echo $study_stats['sessions_today']; ?></strong> sessions
                </span>
                <button class="start-session-btn" onclick="startStudySession()">
                    <i class="fas fa-play-circle"></i>
                    Start Study Session
                </button>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card" onclick="window.location.href='study_plan.php'">
                <div class="action-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <span>Plan Study</span>
            </div>
            <div class="action-card" onclick="window.location.href='projects.php?action=add'">
                <div class="action-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <span>Add Project</span>
            </div>
            <div class="action-card" onclick="window.location.href='progress.php'">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>View Progress</span>
            </div>
            <div class="action-card" onclick="window.location.href='#units'">
                <div class="action-icon">
                    <i class="fas fa-book"></i>
                </div>
                <span>Practice Quiz</span>
            </div>
        </div>
    </div>

    <div class="content" id="units">
        <h2><i class="fas fa-books"></i> Learning Units</h2>
        <p class="subtitle">Select a unit to study or practice. Track your progress as you learn.</p>
        
        <!-- Units Grid -->
        <div class="units-grid">
            <?php if ($units->num_rows > 0): ?>
                <?php while ($unit = $units->fetch_assoc()): 
                    $mcq_available = $unit['mcq_count'] >= 30;
                    $non_mcq_available = $unit['non_mcq_count'] >= 30;
                    $progress = $unit['study_progress'];
                ?>
                <div class="unit-card">
                    <!-- Progress Badge -->
                    <div class="progress-badge" style="background: <?php echo getProgressColor($progress); ?>">
                        <?php echo $progress; ?>%
                    </div>
                    
                    <div class="unit-icon">
                        <?php echo getUnitIcon($progress); ?>
                    </div>
                    
                    <h3><?php echo htmlspecialchars($unit['unit_name']); ?></h3>
                    
                    <?php if (!empty($unit['description'])): ?>
                    <p class="unit-description"><?php echo htmlspecialchars(substr($unit['description'], 0, 100)); ?>...</p>
                    <?php endif; ?>
                    
                    <!-- Progress Bar -->
                    <div class="unit-progress">
                        <div class="progress-label">
                            <span>Study Progress</span>
                            <span><?php echo $progress; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <div class="progress-meta">
                            <small><i class="far fa-clock"></i> Last studied: <?php echo formatDate($unit['last_studied']); ?></small>
                        </div>
                    </div>
                    
                    <!-- Unit Stats -->
                    <div class="unit-stats">
                        <div class="stat">
                            <i class="fas fa-list-ol" style="color: #3498db;"></i>
                            <span>MCQ: <?php echo $unit['mcq_count']; ?>/30 <?php echo $mcq_available ? '✅' : '❌'; ?></span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-font" style="color: #9b59b6;"></i>
                            <span>Non-MCQ: <?php echo $unit['non_mcq_count']; ?>/30 <?php echo $non_mcq_available ? '✅' : '❌'; ?></span>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="unit-actions">
                        <button class="btn study-btn" onclick="addToStudyPlan(<?php echo $unit['unit_id']; ?>)">
                            <i class="fas fa-calendar-plus"></i> Plan
                        </button>
                        
                        <?php if ($mcq_available): ?>
                            <a href="student.php?unit=<?php echo $unit['unit_id']; ?>&type=mcq" 
                               class="btn mcq-btn">
                                <i class="fas fa-list-ol"></i> MCQ Quiz
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($non_mcq_available): ?>
                            <a href="student.php?unit=<?php echo $unit['unit_id']; ?>&type=non_mcq" 
                               class="btn non-mcq-btn">
                                <i class="fas fa-font"></i> Non-MCQ
                            </a>
                        <?php endif; ?>
                        
                        <button class="btn review-btn" onclick="reviewUnit(<?php echo $unit['unit_id']; ?>)">
                            <i class="fas fa-eye"></i> Review
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open fa-3x"></i>
                    <h3>No Learning Units Available</h3>
                    <p>Your instructor hasn't added any units yet. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Study Session Modal -->
<div id="studySessionModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-hourglass-start"></i> Start Study Session</h3>
        <form id="studySessionForm">
            <div class="form-group">
                <label><i class="fas fa-book"></i> Select Unit</label>
                <select id="studyUnit" required>
                    <option value="">Choose a unit to study...</option>
                    <?php 
                    $units->data_seek(0);
                    while ($unit = $units->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $unit['unit_id']; ?>">
                        <?php echo htmlspecialchars($unit['unit_name']); ?> (<?php echo $unit['study_progress']; ?>% complete)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-clock"></i> Study Duration</label>
                <div class="duration-options">
                    <button type="button" class="duration-btn active" data-minutes="25">25 min</button>
                    <button type="button" class="duration-btn" data-minutes="50">50 min</button>
                    <button type="button" class="duration-btn" data-minutes="90">90 min</button>
                    <input type="number" id="customMinutes" placeholder="Custom" min="10" max="180">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-bullseye"></i> Study Goal</label>
                <textarea id="studyGoal" placeholder="What do you want to accomplish in this session?"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn primary">
                    <i class="fas fa-play"></i> Start Session
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quiz Modal -->
<?php if ($selected_unit && $selected_type && count($questions) >= 30): ?>
<div class="quiz-modal active">
    <div class="quiz-header">
        <div>
            <div class="quiz-title">
                <i class="fas fa-brain"></i>
                Quiz Session: <?php echo htmlspecialchars($unit['unit_name'] ?? 'Unit'); ?>
            </div>
            <div class="quiz-subtitle">
                <?php echo $selected_type === 'mcq' ? 'Multiple Choice Questions' : 'Open-ended Questions'; ?>
                • Timer: <span id="quizTimer">30:00</span>
            </div>
        </div>
        <button class="close-quiz" onclick="window.location.href='student.php'">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <div class="questions-container">
        <form method="POST" action="submit_quiz_smart.php" id="quizForm">
            <input type="hidden" name="unit_id" value="<?php echo $selected_unit; ?>">
            <input type="hidden" name="question_type" value="<?php echo $selected_type; ?>">
            <input type="hidden" name="instance" value="<?php echo $instance_num; ?>">
            <input type="hidden" name="study_session_id" id="studySessionId">
            
            <?php foreach ($questions as $index => $question): ?>
            <div class="question-card">
                <div class="question-header">
                    <span class="question-number"><?php echo $index + 1; ?></span>
                    <span class="difficulty-badge">
                        <i class="fas fa-star"></i> Medium
                    </span>
                </div>
                
                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                
                <?php if ($selected_type === 'mcq'): ?>
                <div class="mcq-options">
                    <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                    <label class="mcq-option">
                        <input type="radio" name="answer[<?php echo $index; ?>]" value="<?php echo strtoupper($letter); ?>" 
                               required onchange="updateProgress()">
                        <span class="option-letter"><?php echo strtoupper($letter); ?>)</span>
                        <span class="option-text"><?php echo htmlspecialchars($question['option_' . $letter]); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="non-mcq-answer">
                    <textarea name="answer[<?php echo $index; ?>]" class="answer-textarea" 
                              placeholder="Type your answer here..." required 
                              oninput="autoGrow(this); updateProgress()"></textarea>
                    <div class="word-count">Words: <span id="wordCount<?php echo $index; ?>">0</span></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <div class="submit-section">
                <div class="progress-indicator">
                    <div class="progress-bar-mini">
                        <div id="progressFill" style="width: 0%"></div>
                    </div>
                    <span id="answeredCount">0</span>/30 questions answered
                </div>
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <i class="fas fa-paper-plane"></i>
                    Submit Answers
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Study Session Functions
let studyTimer;
let studyStartTime;
let currentSessionId;

function startStudySession() {
    document.getElementById('studySessionModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('studySessionModal').style.display = 'none';
}

document.getElementById('studySessionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const unitId = document.getElementById('studyUnit').value;
    const minutes = document.querySelector('.duration-btn.active')?.dataset.minutes || 
                    document.getElementById('customMinutes').value || 25;
    const goal = document.getElementById('studyGoal').value;
    
    // Start study session
    fetch('start_study_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            unit_id: unitId,
            minutes: minutes,
            goal: goal
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentSessionId = data.session_id;
            studyStartTime = new Date();
            startStudyTimer(minutes);
            closeModal();
            
            // Show timer notification
            showNotification('Study session started! Timer set for ' + minutes + ' minutes.', 'success');
        }
    });
});

function startStudyTimer(minutes) {
    let timeLeft = minutes * 60;
    
    studyTimer = setInterval(() => {
        timeLeft--;
        if (timeLeft <= 0) {
            clearInterval(studyTimer);
            endStudySession();
        }
    }, 1000);
}

function endStudySession() {
    fetch('end_study_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({session_id: currentSessionId})
    })
    .then(() => {
        showNotification('Study session completed! Great work!', 'success');
    });
}

// Quiz Functions
let quizTime = 30 * 60; // 30 minutes in seconds

function updateQuizTimer() {
    const minutes = Math.floor(quizTime / 60);
    const seconds = quizTime % 60;
    document.getElementById('quizTimer').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    if (quizTime <= 0) {
        document.getElementById('quizForm').submit();
    } else {
        quizTime--;
    }
}

// Start quiz timer if quiz modal is active
if (document.querySelector('.quiz-modal.active')) {
    setInterval(updateQuizTimer, 1000);
}

// Helper Functions
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        ${message}
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function addToStudyPlan(unitId) {
    fetch('add_to_study_plan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({unit_id: unitId})
    })
    .then(response => response.json())
    .then(data => {
        showNotification('Added to study plan!', 'success');
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
}
</script>

<?php
// Helper functions for student.php
function getProgressColor($progress) {
    if ($progress >= 80) return '#2ecc71';
    if ($progress >= 50) return '#f39c12';
    return '#e74c3c';
}

function getUnitIcon($progress) {
    if ($progress >= 80) return '🎯';
    if ($progress >= 50) return '📚';
    return '📖';
}

function formatDate($date) {
    if ($date === 'Never') return 'Never';
    $dateTime = new DateTime($date);
    $now = new DateTime();
    $interval = $now->diff($dateTime);
    
    if ($interval->days === 0) return 'Today';
    if ($interval->days === 1) return 'Yesterday';
    if ($interval->days < 7) return $interval->days . ' days ago';
    
    return $dateTime->format('M d, Y');
}
?>
</body>
</html>
