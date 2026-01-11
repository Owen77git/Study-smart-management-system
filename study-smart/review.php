<?php
// review.php - Answer Review Page
require_once 'config.php';

// Check if user is student
if ($_SESSION['role'] !== 'student') {
    redirect('index.php', 'Please select student mode first.');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instance_id = $_POST['instance_id'] ?? null;
    $unit_id = $_POST['unit_id'] ?? null;
    $question_type = $_POST['question_type'] ?? null;
    $answers = $_POST['answer'] ?? [];
    
    if (!$instance_id || empty($answers)) {
        redirect('student.php', 'No answers submitted.');
    }
    
    // Save answers to database
    saveAnswers($conn, $instance_id, $answers, $question_type);
    
    // Calculate score
    $score = calculateScore($conn, $instance_id, $question_type);
    
    // Save grade
    saveGrade($conn, $instance_id, $score);
    
    // Set session variables for review
    $_SESSION['review_instance'] = $instance_id;
    $_SESSION['review_score'] = $score;
    
} else {
    // If accessed directly without submission
    redirect('student.php', 'Please complete a quiz first.');
}

$page_title = 'Review Answers - Quiz Master';
require_once 'includes/header.php';

// Get review data
$instance_id = $_SESSION['review_instance'];
$score = $_SESSION['review_score'];

// Get questions and answers for review
$review_data = getReviewData($conn, $instance_id);
?>

<div class="review-container">
    <div class="review-header">
        <h1>
            <i class="fas fa-clipboard-check"></i>
            Answer Review
        </h1>
        <div class="review-meta">
            <a href="results.php?instance=<?php echo $instance_id; ?>" class="results-btn">
                <i class="fas fa-chart-bar"></i>
                Check Results
            </a>
            <span class="score-preview">Score: <?php echo $score; ?>/30</span>
        </div>
    </div>
    
    <div class="instance-nav">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <a href="?instance=<?php echo $i; ?>" class="instance-tab <?php echo ($i == ($_GET['instance'] ?? 1)) ? 'active' : ''; ?>">
                Quiz <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    
    <div class="answers-review">
        <?php 
        $question_count = 0;
        foreach ($review_data as $question): 
            $question_count++;
            $is_correct = $question['is_correct'];
            $student_answer = $question['student_answer'];
            $correct_answer = $question['correct_answer'];
        ?>
            <div class="review-question">
                <div class="review-question-header <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                    <div class="status-indicator">
                        <?php if ($is_correct): ?>
                            <i class="fas fa-check-circle"></i>
                            <span>Correct</span>
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i>
                            <span>Incorrect</span>
                        <?php endif; ?>
                    </div>
                    <span class="question-number">Question <?php echo $question_count; ?></span>
                </div>
                
                <div class="review-question-body">
                    <div class="question-text">
                        <strong>Question:</strong>
                        <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                    </div>
                    
                    <div class="answer-comparison">
                        <div class="student-answer">
                            <strong>Your Answer:</strong>
                            <div class="answer-content <?php echo !$is_correct ? 'wrong' : ''; ?>">
                                <?php 
                                if ($question_type === 'mcq') {
                                    echo '<span class="mcq-answer">' . htmlspecialchars($student_answer) . '</span>';
                                } else {
                                    echo '<div class="text-answer">' . nl2br(htmlspecialchars($student_answer)) . '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if (!$is_correct): ?>
                            <div class="correct-answer">
                                <strong>Correct Answer:</strong>
                                <div class="answer-content correct">
                                    <?php 
                                    if ($question_type === 'mcq') {
                                        echo '<span class="mcq-answer correct">' . htmlspecialchars($correct_answer) . '</span>';
                                    } else {
                                        echo '<div class="text-answer correct">' . nl2br(htmlspecialchars($correct_answer)) . '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="review-actions">
        <a href="student.php" class="action-btn secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Practice
        </a>
        <a href="results.php?instance=<?php echo $instance_id; ?>" class="action-btn primary">
            View Detailed Results
            <i class="fas fa-arrow-right"></i>
        </a>
        <button class="action-btn repeat-btn" onclick="repeatQuiz()">
            <i class="fas fa-redo"></i>
            Repeat This Quiz
        </button>
    </div>
</div>

<script>
function repeatQuiz() {
    if (confirm('Repeat this quiz? Your previous score will be cleared.')) {
        fetch('api/repeat_quiz.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({instance_id: <?php echo $instance_id; ?>})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'student.php?unit=<?php echo $unit_id; ?>&type=<?php echo $question_type; ?>';
            }
        });
    }
}
</script>

<?php
// Helper functions for review.php
function saveAnswers($conn, $instance_id, $answers, $question_type) {
    foreach ($answers as $order => $answer) {
        $order = (int)$order;
        $answer = trim($answer);
        
        if ($question_type === 'mcq') {
            $stmt = $conn->prepare("
                INSERT INTO student_answers (instance_id, question_order, mcq_answer)
                SELECT ?, ?, ?
                FROM instance_questions iq
                WHERE iq.instance_id = ? AND iq.question_order = ?
            ");
            $stmt->bind_param("iisii", $instance_id, $order, $answer, $instance_id, $order);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO student_answers (instance_id, question_order, non_mcq_answer)
                SELECT ?, ?, ?
                FROM instance_questions iq
                WHERE iq.instance_id = ? AND iq.question_order = ?
            ");
            $stmt->bind_param("iisii", $instance_id, $order, $answer, $instance_id, $order);
        }
        $stmt->execute();
        $stmt->close();
    }
}

function calculateScore($conn, $instance_id, $question_type) {
    $score = 0;
    
    if ($question_type === 'mcq') {
        // For MCQ: exact match
        $query = "
            SELECT sa.mcq_answer, m.correct_answer
            FROM student_answers sa
            JOIN instance_questions iq ON sa.instance_id = iq.instance_id AND sa.question_order = iq.question_order
            JOIN mcq_questions m ON iq.mcq_id = m.mcq_id
            WHERE sa.instance_id = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $instance_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (strtoupper(trim($row['mcq_answer'])) === strtoupper(trim($row['correct_answer']))) {
                $score++;
            }
        }
        $stmt->close();
        
    } else {
        // For Non-MCQ: keyword matching for main points
        $query = "
            SELECT sa.non_mcq_answer, n.correct_answer
            FROM student_answers sa
            JOIN instance_questions iq ON sa.instance_id = iq.instance_id AND sa.question_order = iq.question_order
            JOIN non_mcq_questions n ON iq.non_mcq_id = n.non_mcq_id
            WHERE sa.instance_id = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $instance_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (checkNonMCQAnswer($row['non_mcq_answer'], $row['correct_answer'])) {
                $score++;
            }
        }
        $stmt->close();
    }
    
    return $score;
}

function checkNonMCQAnswer($student_answer, $correct_answer) {
    // Simple keyword matching for main points
    $student_lower = strtolower(trim($student_answer));
    $correct_lower = strtolower(trim($correct_answer));
    
    // Extract key terms from correct answer (simple approach)
    preg_match_all('/\b\w+\b/', $correct_lower, $keywords);
    $keywords = array_unique($keywords[0]);
    
    // Remove common words
    $common_words = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
    $keywords = array_diff($keywords, $common_words);
    
    // Check if student answer contains keywords
    $match_count = 0;
    foreach ($keywords as $keyword) {
        if (strlen($keyword) > 3 && strpos($student_lower, $keyword) !== false) {
            $match_count++;
        }
    }
    
    // Consider correct if more than 60% of keywords match
    $threshold = count($keywords) * 0.6;
    return $match_count >= $threshold;
}

function saveGrade($conn, $instance_id, $score) {
    $percentage = ($score / QUESTIONS_PER_SESSION) * 100;
    $stmt = $conn->prepare("
        INSERT INTO grades (instance_id, score, total_questions, percentage) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiid", $instance_id, $score, QUESTIONS_PER_SESSION, $percentage);
    $stmt->execute();
    $stmt->close();
}

function getReviewData($conn, $instance_id) {
    $query = "
        SELECT 
            iq.question_order,
            COALESCE(m.question_text, n.question_text) as question_text,
            COALESCE(sa.mcq_answer, sa.non_mcq_answer) as student_answer,
            COALESCE(m.correct_answer, n.correct_answer) as correct_answer,
            CASE 
                WHEN m.mcq_id IS NOT NULL THEN 
                    UPPER(TRIM(sa.mcq_answer)) = UPPER(TRIM(m.correct_answer))
                ELSE 
                    -- Non-MCQ checking logic would go here
                    NULL
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
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    
    return $data;
}

require_once 'includes/footer.php';
?>
