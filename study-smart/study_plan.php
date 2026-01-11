<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'student') {
    redirect('index.php');
}

// Handle study plan creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_plan'])) {
        $plan_data = [
            'units' => $_POST['units'] ?? [],
            'schedule' => $_POST['schedule'] ?? [],
            'goals' => $_POST['goals'] ?? '',
            'duration' => $_POST['duration'] ?? 7
        ];
        
        $_SESSION['study_plan'] = $plan_data;
        redirect('study_plan.php', 'Study plan created successfully!');
    }
}

// Get scheduled study sessions
$scheduled_sessions = $conn->query("
    SELECT ss.*, u.unit_name 
    FROM study_sessions ss
    JOIN units u ON ss.unit_id = u.unit_id
    WHERE ss.user_id = {$_SESSION['user_id']}
    AND ss.scheduled_date >= CURDATE()
    ORDER BY ss.scheduled_date, ss.start_time
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Study Plan - StudySmart</title>
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
            <a href="student.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="study_plan.php" class="active"><i class="fas fa-calendar-alt"></i> Study Plan</a>
            <a href="projects.php"><i class="fas fa-project-diagram"></i> Projects</a>
            <a href="progress.php"><i class="fas fa-chart-line"></i> Progress</a>
            <a href="results.php"><i class="fas fa-clipboard-check"></i> Results</a>
        </div>
    </div>

    <div class="content">
        <!-- Study Plan Header -->
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> Study Plan</h1>
            <p>Plan your study sessions, set goals, and track your academic journey</p>
        </div>

        <!-- Weekly Schedule -->
        <div class="weekly-schedule">
            <h2><i class="fas fa-calendar-week"></i> This Week's Schedule</h2>
            <div class="schedule-grid">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($days as $day): 
                ?>
                <div class="schedule-day">
                    <h3><?php echo $day; ?></h3>
                    <div class="day-sessions" id="sessions-<?php echo strtolower($day); ?>">
                        <!-- Sessions will be loaded here -->
                    </div>
                    <button class="add-session-btn" onclick="addSession('<?php echo $day; ?>')">
                        <i class="fas fa-plus"></i> Add Session
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Study Goals -->
        <div class="study-goals">
            <h2><i class="fas fa-bullseye"></i> Study Goals</h2>
            <div class="goals-container">
                <div class="goal-card">
                    <h3><i class="fas fa-book"></i> Weekly Target</h3>
                    <div class="goal-progress">
                        <div class="progress-circle" data-progress="75">
                            <span>75%</span>
                        </div>
                        <div class="goal-details">
                            <p><strong>15/20 hours</strong> studied this week</p>
                            <small>Target: 20 hours per week</small>
                        </div>
                    </div>
                </div>
                <div class="goal-card">
                    <h3><i class="fas fa-check-circle"></i> Completion Rate</h3>
                    <div class="goal-progress">
                        <div class="progress-circle" data-progress="90">
                            <span>90%</span>
                        </div>
                        <div class="goal-details">
                            <p><strong>9/10 sessions</strong> completed</p>
                            <small>Excellent consistency!</small>
                        </div>
                    </div>
                </div>
                <div class="goal-card">
                    <h3><i class="fas fa-chart-line"></i> Performance</h3>
                    <div class="goal-progress">
                        <div class="progress-circle" data-progress="85">
                            <span>85%</span>
                        </div>
                        <div class="goal-details">
                            <p>Average quiz score</p>
                            <small>Goal: 80% or higher</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Session Modal -->
<div id="sessionModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-plus-circle"></i> Add Study Session</h3>
        <form id="sessionForm">
            <div class="form-group">
                <label><i class="fas fa-calendar-day"></i> Day</label>
                <input type="text" id="sessionDay" readonly>
            </div>
            <div class="form-group">
                <label><i class="fas fa-clock"></i> Time</label>
                <input type="time" id="sessionTime" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-book"></i> Unit</label>
                <select id="sessionUnit" required>
                    <option value="">Select a unit...</option>
                    <?php
                    $units = $conn->query("SELECT * FROM units WHERE is_active = TRUE");
                    while ($unit = $units->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $unit['unit_id']; ?>">
                        <?php echo htmlspecialchars($unit['unit_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-hourglass"></i> Duration (minutes)</label>
                <input type="number" id="sessionDuration" min="15" max="180" value="60" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-bullseye"></i> Goal for this session</label>
                <textarea id="sessionGoal" rows="3" placeholder="What do you want to achieve?"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn primary">Save Session</button>
            </div>
        </form>
    </div>
</div>

<script>
function addSession(day) {
    document.getElementById('sessionDay').value = day;
    document.getElementById('sessionModal').style.display = 'flex';
    document.getElementById('sessionTime').focus();
}

function closeModal() {
    document.getElementById('sessionModal').style.display = 'none';
}

document.getElementById('sessionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const sessionData = {
        day: document.getElementById('sessionDay').value,
        time: document.getElementById('sessionTime').value,
        unit_id: document.getElementById('sessionUnit').value,
        duration: document.getElementById('sessionDuration').value,
        goal: document.getElementById('sessionGoal').value
    };
    
    // Save session to database
    fetch('save_study_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(sessionData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            location.reload();
        }
    });
});

// Animate progress circles
document.querySelectorAll('.progress-circle').forEach(circle => {
    const progress = circle.getAttribute('data-progress');
    circle.style.background = `conic-gradient(#3498db ${progress * 3.6}deg, #ecf0f1 0deg)`;
});
</script>

<style>
.weekly-schedule {
    background: linear-gradient(145deg, rgba(44, 62, 80, 0.7), rgba(52, 73, 94, 0.7));
    border-radius: 20px;
    padding: 30px;
    margin: 30px 0;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.schedule-day {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.schedule-day h3 {
    text-align: center;
    margin-bottom: 15px;
    color: var(--primary);
}

.day-sessions {
    min-height: 100px;
    margin-bottom: 15px;
}

.add-session-btn {
    width: 100%;
    padding: 10px;
    background: rgba(52, 152, 219, 0.2);
    border: 1px dashed var(--primary);
    color: white;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.add-session-btn:hover {
    background: rgba(52, 152, 219, 0.4);
}

.study-goals {
    margin-top: 40px;
}

.goals-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.goal-card {
    background: linear-gradient(145deg, #162a38, #1a2f3e);
    padding: 25px;
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.goal-card h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    color: white;
}

.goal-progress {
    display: flex;
    align-items: center;
    gap: 20px;
}

.progress-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.progress-circle::before {
    content: '';
    position: absolute;
    width: 80px;
    height: 80px;
    background: #162a38;
    border-radius: 50%;
}

.progress-circle span {
    position: relative;
    z-index: 2;
    font-weight: bold;
    font-size: 20px;
    color: white;
}

.goal-details p {
    margin-bottom: 5px;
    color: white;
}

.goal-details small {
    color: #b0b0b0;
}
</style>
</body>
</html>