<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'student') {
    redirect('index.php');
}

$instance_id = $_GET['instance'] ?? 1;

// Get results data
$query = "
    SELECT 
        iq.question_order,
        CASE WHEN iq.mcq_id IS NOT NULL THEN 'mcq' ELSE 'non_mcq' END as question_type,
        COALESCE(m.question_text, n.question_text) as question_text,
        COALESCE(sa.mcq_answer, sa.non_mcq_answer) as student_answer,
        COALESCE(m.correct_answer, n.correct_answer) as correct_answer,
        CASE 
            WHEN m.mcq_id IS NOT NULL THEN 
                UPPER(TRIM(sa.mcq_answer)) = UPPER(TRIM(m.correct_answer))
            ELSE 
                sa.non_mcq_answer IS NOT NULL AND n.correct_answer IS NOT NULL
        END as is_correct
    FROM instance_questions iq
    LEFT JOIN mcq_questions m ON iq.mcq_id = m.mcq_id
    LEFT JOIN non_mcq_questions n ON iq.non_mcq_id = n.non_mcq_id
    LEFT JOIN student_answers sa ON iq.instance_id = sa.instance_id AND iq.question_order = sa.question_order
    WHERE iq.instance_id = ?
    ORDER BY iq.question_order
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $instance_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate score
$score = 0;
foreach ($results as $result) {
    if ($result['is_correct']) $score++;
}
$percentage = ($score / 30) * 100;

// Get grade data
$grade_query = "SELECT * FROM grades WHERE instance_id = ?";
$grade_stmt = $conn->prepare($grade_query);
$grade_stmt->bind_param("i", $instance_id);
$grade_stmt->execute();
$grade_data = $grade_stmt->get_result()->fetch_assoc();

// Save grade if not exists
if (!$grade_data) {
    $save_stmt = $conn->prepare("INSERT INTO grades (instance_id, score, total_questions, percentage) VALUES (?, ?, 30, ?)");
    $save_stmt->bind_param("iid", $instance_id, $score, $percentage);
    $save_stmt->execute();
    $grade_data = ['score' => $score, 'percentage' => $percentage];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Results - StudySmart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container">
    <!-- NAVBAR -->
    <div class="navbar">
        <div class="logo">StudySmart</div>
        <div class="nav-links">
            <a href="student.php">Dashboard</a>
            <a href="index.php">Switch Role</a>
            <a href="results.php">Results</a>
        </div>
    </div>

    <div class="results-container">
        <!-- Results Header -->
        <div class="results-header">
            <h1>Quiz Results</h1>
            <div class="results-tabs">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button class="result-tab <?php echo $i == $instance_id ? 'active' : ''; ?>" 
                            onclick="window.location.href='results.php?instance=<?php echo $i; ?>'">
                        Quiz <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Score Display -->
        <div style="text-align:center;margin:40px 0;">
            <div class="score-display">
                <?php echo $score; ?>/30
            </div>
            <div class="score-label">
                <?php echo number_format($percentage, 1); ?>% Correct
            </div>
            
            <!-- Percentage Bar -->
            <div class="percentage-bar" style="max-width:400px;margin:20px auto;">
                <div class="percentage-fill" style="width:<?php echo $percentage; ?>%;"></div>
            </div>
        </div>
        
        <!-- Results Breakdown -->
        <div style="margin:40px 0;">
            <h3>Answer Breakdown</h3>
            
            <?php foreach ($results as $index => $result): 
                $question_num = $index + 1;
                $is_correct = $result['is_correct'];
                $student_answer = $result['student_answer'] ?? 'No answer';
                $correct_answer = $result['correct_answer'] ?? '';
            ?>
            <div class="result-question">
                <div class="question-header">
                    <span>Question <?php echo $question_num; ?></span>
                    <span class="status-badge <?php echo $is_correct ? 'correct-badge' : 'incorrect-badge'; ?>">
                        <?php echo $is_correct ? '✓ Correct' : '✗ Incorrect'; ?>
                    </span>
                </div>
                
                <div class="question-text">
                    <?php echo htmlspecialchars($result['question_text']); ?>
                </div>
                
                <div class="answer-comparison">
                    <div class="answer-section your-answer <?php echo $is_correct ? 'correct' : ''; ?>">
                        <h4>Your Answer:</h4>
                        <div class="answer-content">
                            <?php if ($result['question_type'] === 'mcq'): ?>
                                <span style="font-weight:bold;"><?php echo htmlspecialchars($student_answer); ?></span>
                            <?php else: ?>
                                <?php echo nl2br(htmlspecialchars($student_answer)); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!$is_correct && !empty($correct_answer)): ?>
                    <div class="answer-section correct-answer">
                        <h4>Correct Answer:</h4>
                        <div class="answer-content">
                            <?php if ($result['question_type'] === 'mcq'): ?>
                                <span style="font-weight:bold;color:#2ecc71;"><?php echo htmlspecialchars($correct_answer); ?></span>
                            <?php else: ?>
                                <?php echo nl2br(htmlspecialchars($correct_answer)); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Actions -->
        <div style="display:flex;gap:15px;justify-content:center;margin-top:40px;padding-top:40px;border-top:1px solid rgba(255,255,255,0.1);">
            <button onclick="window.location.href='student.php'" class="secondary-btn" style="padding:12px 25px;">
                <i class="fas fa-arrow-left"></i> Back to Practice
            </button>
            <button onclick="window.print()" class="primary-btn" style="padding:12px 25px;">
                <i class="fas fa-print"></i> Print Results
            </button>
            <button onclick="repeatQuiz()" class="review-btn" style="padding:12px 25px;">
                <i class="fas fa-redo"></i> Repeat Quiz
            </button>
        </div>
    </div>
</div>

<script>
function repeatQuiz() {
    if (confirm('Repeat this quiz? Your previous score will be cleared.')) {
        fetch('repeat_quiz.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({instance_id: <?php echo $instance_id; ?>})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Get unit and type info from results
                const urlParams = new URLSearchParams(window.location.search);
                const instance = urlParams.get('instance') || 1;
                window.location.href = `student.php?review_instance=${instance}`;
            }
        });
    }
}
</script>
</body>
</html>
