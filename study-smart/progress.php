<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'student') {
    redirect('index.php');
}

// Get study statistics
$user_id = $_SESSION['user_id'];

// Weekly study time
$weekly_stats = $conn->query("
    SELECT 
        DAYNAME(study_date) as day,
        SUM(minutes_studied) as minutes
    FROM study_tracking 
    WHERE user_id = $user_id 
    AND study_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY study_date
    ORDER BY study_date
")->fetch_all(MYSQLI_ASSOC);

// Unit progress
$unit_progress = $conn->query("
    SELECT 
        u.unit_name,
        sp.progress_percentage,
        COUNT(DISTINCT g.instance_id) as quiz_count,
        AVG(g.percentage) as avg_score
    FROM units u
    LEFT JOIN study_progress sp ON u.unit_id = sp.unit_id AND sp.user_id = $user_id
    LEFT JOIN question_instances qi ON u.unit_id = qi.unit_id
    LEFT JOIN grades g ON qi.instance_id = g.instance_id
    WHERE u.is_active = TRUE
    GROUP BY u.unit_id, u.unit_name, sp.progress_percentage
")->fetch_all(MYSQLI_ASSOC);

// Productivity metrics
$productivity = calculateProductivity($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Progress Dashboard - StudySmart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="student.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="study_plan.php"><i class="fas fa-calendar-alt"></i> Study Plan</a>
            <a href="projects.php"><i class="fas fa-project-diagram"></i> Projects</a>
            <a href="progress.php" class="active"><i class="fas fa-chart-line"></i> Progress</a>
            <a href="results.php"><i class="fas fa-clipboard-check"></i> Results</a>
        </div>
    </div>

    <div class="content">
        <!-- Progress Header -->
        <div class="page-header">
            <h1><i class="fas fa-chart-line"></i> Progress Dashboard</h1>
            <p>Track your academic journey, study habits, and performance metrics</p>
        </div>

        <!-- Key Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(52, 152, 219, 0.2);">
                    <i class="fas fa-clock" style="color: var(--primary);"></i>
                </div>
                <div class="metric-info">
                    <h3>Study Time</h3>
                    <div class="metric-value"><?php echo calculateTotalStudyTime($user_id); ?> hours</div>
                    <div class="metric-change">+2.5h this week</div>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(46, 204, 113, 0.2);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
                <div class="metric-info">
                    <h3>Completion Rate</h3>
                    <div class="metric-value"><?php echo $productivity['passed_quizzes'] ?? 0; ?> quizzes</div>
                    <div class="metric-change"><?php echo $productivity['avg_score'] ?? 0; ?>% avg score</div>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(155, 89, 182, 0.2);">
                    <i class="fas fa-tasks" style="color: var(--info);"></i>
                </div>
                <div class="metric-info">
                    <h3>Projects</h3>
                    <div class="metric-value">3 active</div>
                    <div class="metric-change">2 completed</div>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(243, 156, 18, 0.2);">
                    <i class="fas fa-fire" style="color: var(--warning);"></i>
                </div>
                <div class="metric-info">
                    <h3>Consistency</h3>
                    <div class="metric-value">7 day streak</div>
                    <div class="metric-change">Keep it up!</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-container">
            <!-- Study Time Chart -->
            <div class="chart-card">
                <h3><i class="fas fa-chart-bar"></i> Weekly Study Time</h3>
                <div class="chart-wrapper">
                    <canvas id="studyTimeChart"></canvas>
                </div>
            </div>
            
            <!-- Progress Chart -->
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Unit Progress</h3>
                <div class="chart-wrapper">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Progress -->
        <div class="detailed-progress">
            <h3><i class="fas fa-list-ol"></i> Unit-wise Progress</h3>
            <div class="progress-table">
                <table>
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Progress</th>
                            <th>Quizzes Taken</th>
                            <th>Average Score</th>
                            <th>Last Studied</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unit_progress as $unit): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                            <td>
                                <div class="progress-cell">
                                    <div class="progress-bar-small">
                                        <div class="progress-fill" style="width: <?php echo $unit['progress_percentage'] ?? 0; ?>%"></div>
                                    </div>
                                    <span><?php echo $unit['progress_percentage'] ?? 0; ?>%</span>
                                </div>
                            </td>
                            <td><?php echo $unit['quiz_count'] ?? 0; ?></td>
                            <td>
                                <span class="score-badge <?php echo ($unit['avg_score'] ?? 0) >= 70 ? 'good' : 'average'; ?>">
                                    <?php echo round($unit['avg_score'] ?? 0, 1); ?>%
                                </span>
                            </td>
                            <td>2 days ago</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Study Time Chart
const studyTimeCtx = document.getElementById('studyTimeChart').getContext('2d');
const studyTimeChart = new Chart(studyTimeCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($weekly_stats, 'day')); ?>,
        datasets: [{
            label: 'Study Time (minutes)',
            data: <?php echo json_encode(array_column($weekly_stats, 'minutes')); ?>,
            backgroundColor: 'rgba(52, 152, 219, 0.7)',
            borderColor: 'rgba(52, 152, 219, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: {
                    color: '#fff'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#b0b0b0'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            },
            x: {
                ticks: {
                    color: '#b0b0b0'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            }
        }
    }
});

// Progress Chart
const progressCtx = document.getElementById('progressChart').getContext('2d');
const progressChart = new Chart(progressCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($unit_progress, 'unit_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($unit_progress, 'progress_percentage')); ?>,
            backgroundColor: [
                'rgba(52, 152, 219, 0.7)',
                'rgba(46, 204, 113, 0.7)',
                'rgba(155, 89, 182, 0.7)',
                'rgba(243, 156, 18, 0.7)',
                'rgba(231, 76, 60, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#fff',
                    padding: 20
                }
            }
        }
    }
});
</script>

<?php
// Helper function for total study time
function calculateTotalStudyTime($user_id) {
    global $conn;
    $result = $conn->query("
        SELECT SUM(minutes_studied) as total_minutes 
        FROM study_tracking 
        WHERE user_id = $user_id
    ");
    $total = $result->fetch_assoc()['total_minutes'] ?? 0;
    return round($total / 60, 1);
}
?>
</body>
</html>