<?php
require_once 'config.php';

// Handle role selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'])) {
    $_SESSION['role'] = $_POST['role'];
    
    // Start session tracking for student
    if ($_POST['role'] === 'student') {
        $_SESSION['study_start'] = time();
        $today = date('Y-m-d');
        $check = $conn->query("SELECT track_id FROM usage_tracking WHERE session_date = '$today' AND end_time IS NULL LIMIT 1");
        if ($check->num_rows === 0) {
            $conn->query("INSERT INTO usage_tracking (session_date) VALUES ('$today')");
        }
    }
    
    redirect($_POST['role'] === 'instructor' ? 'instructor.php' : 'student.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Study Smart – Your Academic Success Partner</title>
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
            <a href="#features"><i class="fas fa-star"></i> Features</a>
            <a href="#how-it-works"><i class="fas fa-play-circle"></i> How It Works</a>
            <a href="#testimonials"><i class="fas fa-comment"></i> Testimonials</a>
        </div>
    </div>

    <!-- HERO SECTION -->
    <div class="hero">
        <h1>Study Smart, Not Hard</h1>
        <p class="tagline">Transform your study habits with intelligent planning, consistent tracking, and academic project management designed for student success.</p>
        
        <!-- Role Selection -->
        <div class="role-selection">
            <h2 style="margin-bottom:30px;text-align:center;">Get Started with Study Smart</h2>
            <div class="role-grid">
                <label class="role-option">
                    <input type="radio" name="role" value="instructor" hidden>
                    <div class="role-card">
                        <div class="role-icon">👨‍🏫</div>
                        <h3>INSTRUCTOR</h3>
                        <p>Create learning units, manage content, and track student progress</p>
                        <div class="role-features">
                            <span><i class="fas fa-check"></i> Upload Questions</span>
                            <span><i class="fas fa-check"></i> Track Performance</span>
                            <span><i class="fas fa-check"></i> Manage Units</span>
                        </div>
                    </div>
                </label>
                
                <label class="role-option">
                    <input type="radio" name="role" value="student" hidden>
                    <div class="role-card">
                        <div class="role-icon">👨‍🎓</div>
                        <h3>STUDENT</h3>
                        <p>Plan studies, track progress, practice quizzes, and manage projects</p>
                        <div class="role-features">
                            <span><i class="fas fa-check"></i> Study Planning</span>
                            <span><i class="fas fa-check"></i> Progress Tracking</span>
                            <span><i class="fas fa-check"></i> Quiz Practice</span>
                        </div>
                    </div>
                </label>
            </div>
            
            <form method="POST" class="role-form">
                <input type="hidden" name="role" id="selectedRole">
                <button type="submit" class="start-btn" id="startBtn" disabled>
                    <i class="fas fa-rocket"></i>
                    Launch Study Smart
                </button>
            </form>
        </div>
        
        <!-- Key Features -->
        <div id="features" class="features-section">
            <h2>Smart Features for Academic Success</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Study Planning</h3>
                    <p>Create personalized study schedules with smart time allocation</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Progress Tracking</h3>
                    <p>Monitor your academic growth with detailed analytics</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Project Management</h3>
                    <p>Break down assignments into manageable tasks</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>Smart Quizzes</h3>
                    <p>Adaptive practice questions with instant feedback</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleCards = document.querySelectorAll('.role-card');
    const selectedRoleInput = document.getElementById('selectedRole');
    const startBtn = document.getElementById('startBtn');
    
    roleCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selection from all cards
            roleCards.forEach(c => c.classList.remove('selected'));
            
            // Add selection to clicked card
            this.classList.add('selected');
            
            // Set the role value
            const role = this.closest('.role-option').querySelector('input').value;
            selectedRoleInput.value = role;
            
            // Enable start button
            startBtn.disabled = false;
            startBtn.style.opacity = '1';
            startBtn.style.transform = 'scale(1.05)';
        });
    });
    
    // Auto-select student role after 3 seconds
    setTimeout(() => {
        if (!selectedRoleInput.value) {
            const studentCard = document.querySelector('input[value="student"]').closest('.role-option').querySelector('.role-card');
            studentCard.click();
        }
    }, 3000);
});
</script>
</body>
</html>
