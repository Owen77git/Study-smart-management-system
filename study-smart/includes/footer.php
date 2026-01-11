<?php
// includes/footer.php
if (!isset($role)) {
    $role = $_SESSION['role'] ?? 'none';
}

$current_year = date('Y');
?>

<?php if ($role !== 'none'): ?>
    </div> <!-- Close content-wrapper -->
<?php endif; ?>

<?php if ($role === 'none'): ?>
    <!-- Landing Page Footer -->
    <footer class="landing-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <div class="brand-logo">
                    <i class="fas fa-brain"></i>
                    <span>Quiz Master</span>
                </div>
                <p class="brand-tagline">Your personalized learning companion for better retention</p>
                <div class="social-links">
                    <a href="#" class="social-link" title="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="#" class="social-link" title="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link" title="LinkedIn">
                        <i class="fab fa-linkedin"></i>
                    </a>
                </div>
            </div>
            
            <div class="footer-links">
                <div class="link-group">
                    <h4><i class="fas fa-rocket"></i> Features</h4>
                    <a href="#smart-upload">Smart Upload</a>
                    <a href="#time-tracking">Time Tracking</a>
                    <a href="#spaced-repetition">Spaced Repetition</a>
                    <a href="#analytics">Progress Analytics</a>
                </div>
                
                <div class="link-group">
                    <h4><i class="fas fa-question-circle"></i> Help</h4>
                    <a href="#" onclick="showHelp()">Getting Started</a>
                    <a href="#" onclick="showFAQ()">FAQ</a>
                    <a href="#" onclick="showContact()">Contact</a>
                </div>
                
                <div class="link-group">
                    <h4><i class="fas fa-info-circle"></i> About</h4>
                    <a href="#" onclick="showAbout()">Our Story</a>
                    <a href="#" onclick="showPrivacy()">Privacy Policy</a>
                    <a href="#" onclick="showTerms()">Terms of Service</a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo $current_year; ?> Quiz Master. Built for Owen's learning journey.</p>
            <p class="version">Version 1.0.0</p>
        </div>
    </footer>
    
<?php else: ?>
    <!-- Main Application Footer -->
    <footer class="app-footer">
        <div class="footer-main">
            <!-- Quick Actions -->
            <div class="footer-section">
                <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
                <div class="action-buttons">
                    <?php if ($role === 'instructor'): ?>
                        <button class="action-btn" onclick="showModal('unitModal')">
                            <i class="fas fa-plus"></i>
                            <span>New Unit</span>
                        </button>
                        <a href="instructor.php" class="action-btn">
                            <i class="fas fa-upload"></i>
                            <span>Upload Questions</span>
                        </a>
                        <a href="#" class="action-btn" onclick="showStats()">
                            <i class="fas fa-chart-bar"></i>
                            <span>View Stats</span>
                        </a>
                    <?php else: ?>
                        <button class="action-btn" onclick="toggleSidebar()">
                            <i class="fas fa-book"></i>
                            <span>Units</span>
                        </button>
                        <a href="review.php" class="action-btn">
                            <i class="fas fa-history"></i>
                            <span>Review</span>
                        </a>
                        <a href="results.php" class="action-btn">
                            <i class="fas fa-trophy"></i>
                            <span>Results</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Live Stats -->
            <div class="footer-section">
                <h4><i class="fas fa-chart-line"></i> Live Stats</h4>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon today">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-value" id="footerTodayTime">0</span>
                            <span class="stat-label">Minutes Today</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-value" id="footerTotalQuizzes">0</span>
                            <span class="stat-label">Quizzes Done</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon accuracy">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-value" id="footerAccuracy">0%</span>
                            <span class="stat-label">Accuracy</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Progress -->
            <div class="footer-section">
                <h4><i class="fas fa-tasks"></i> Today's Progress</h4>
                <div class="progress-section">
                    <div class="progress-header">
                        <span>Daily Goal: 60 minutes</span>
                        <span id="progressPercentage">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-hint" id="progressHint">
                        <i class="fas fa-info-circle"></i>
                        <span>Start studying to track progress</span>
                    </div>
                </div>
            </div>
            
            <!-- User Quick Info -->
            <div class="footer-section">
                <h4><i class="fas fa-user-graduate"></i> Quick Info</h4>
                <div class="user-quick-info">
                    <div class="user-avatar-small">
                        <?php if ($role === 'instructor'): ?>
                            <i class="fas fa-user-tie"></i>
                        <?php else: ?>
                            <i class="fas fa-user-graduate"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <strong>OWEN</strong>
                        <div class="user-meta">
                            <span class="user-role-badge <?php echo $role; ?>">
                                <?php echo ucfirst($role); ?>
                            </span>
                            <span class="user-since">Since <?php echo date('M Y', strtotime('-1 month')); ?></span>
                        </div>
                    </div>
                    <button class="user-menu-btn" onclick="toggleUserMenu()" title="User Menu">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
                <div class="user-menu" id="userMenu">
                    <a href="#" onclick="showProfile()">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="#" onclick="showSettings()">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="#" onclick="showAchievements()">
                        <i class="fas fa-trophy"></i>
                        <span>Achievements</span>
                    </a>
                    <a href="index.php?logout=true" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom-bar">
            <!-- Left: Copyright & Info -->
            <div class="footer-left">
                <div class="app-info">
                    <i class="fas fa-brain"></i>
                    <span>Quiz Master v1.0</span>
                </div>
                <div class="copyright">
                    &copy; <?php echo $current_year; ?> Personalized Learning App
                </div>
                <div class="server-status">
                    <i class="fas fa-circle" id="serverStatusIcon"></i>
                    <span id="serverStatusText">Online</span>
                </div>
            </div>
            
            <!-- Center: Navigation -->
            <div class="footer-center">
                <nav class="footer-nav">
                    <a href="<?php echo ($role === 'instructor') ? 'instructor.php' : 'student.php'; ?>">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                    <?php if ($role === 'instructor'): ?>
                        <a href="#" onclick="showModal('unitModal')">
                            <i class="fas fa-plus"></i>
                            <span>Add Unit</span>
                        </a>
                        <a href="instructor.php?view=questions">
                            <i class="fas fa-database"></i>
                            <span>Question Bank</span>
                        </a>
                    <?php else: ?>
                        <a href="#" onclick="toggleSidebar()">
                            <i class="fas fa-layer-group"></i>
                            <span>Units</span>
                        </a>
                        <a href="results.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Progress</span>
                        </a>
                    <?php endif; ?>
                    <a href="#" onclick="showHelpModal()">
                        <i class="fas fa-question-circle"></i>
                        <span>Help</span>
                    </a>
                </nav>
            </div>
            
            <!-- Right: Controls -->
            <div class="footer-right">
                <button class="footer-control" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <i class="fas fa-moon" id="darkModeIcon"></i>
                </button>
                <button class="footer-control" onclick="resetTimer()" title="Reset Timer">
                    <i class="fas fa-redo"></i>
                </button>
                <button class="footer-control" onclick="printPage()" title="Print Page">
                    <i class="fas fa-print"></i>
                </button>
                <button class="footer-control" onclick="toggleFullscreen()" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
                <button class="footer-control" id="backToTopBtn" onclick="scrollToTop()" title="Back to Top">
                    <i class="fas fa-arrow-up"></i>
                </button>
            </div>
        </div>
        
        <!-- Session Warning -->
        <div class="session-warning" id="sessionWarning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Your session will expire in <span id="sessionCountdown">5:00</span></span>
            <button class="session-extend" onclick="extendSession()">Extend Session</button>
        </div>
    </footer>
    
    <!-- Help Modal -->
    <div class="modal" id="helpModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-question-circle"></i>
                    <span>Help & Support</span>
                </h2>
                <button class="modal-close" onclick="closeHelpModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="help-content">
                    <h3><?php echo ucfirst($role); ?> Guide</h3>
                    <?php if ($role === 'instructor'): ?>
                        <p><strong>Adding Units:</strong> Click the + button in the top left to create new units.</p>
                        <p><strong>Uploading Questions:</strong> Click the watermark, select a unit, then choose MCQ or Non-MCQ upload.</p>
                        <p><strong>Format for MCQ:</strong> Paste questions with A), B), C), D) options and "Answer: X" on last line.</p>
                        <p><strong>Pushing to Main:</strong> Review questions before pushing them to the student view.</p>
                    <?php else: ?>
                        <p><strong>Starting a Quiz:</strong> Use the menu (☰) to select a unit, then choose MCQ or Non-MCQ.</p>
                        <p><strong>Answering Questions:</strong> You must answer all 30 questions before submitting.</p>
                        <p><strong>Reviewing Answers:</strong> After submission, you'll see which answers were correct/incorrect.</p>
                        <p><strong>Tracking Progress:</strong> Check your results and stats in the Results page.</p>
                    <?php endif; ?>
                    <div class="contact-support">
                        <h4>Need More Help?</h4>
                        <p>Contact: <strong>owen@example.com</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- JavaScript -->
<script src="script.js"></script>

<!-- Initialize Scripts -->
<script>
// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Update footer stats
    updateFooterStats();
    
    // Initialize session timer
    if (typeof startSessionTimer === 'function') {
        startSessionTimer();
    }
    
    // Check server status
    checkServerStatus();
    
    // Update progress bar
    updateProgressBar();
    
    // Initialize tooltips
    initTooltips();
    
    // Show welcome message for first visit
    if (!localStorage.getItem('welcomeShown')) {
        setTimeout(showWelcomeMessage, 1000);
        localStorage.setItem('welcomeShown', 'true');
    }
});

// Update footer statistics
async function updateFooterStats() {
    try {
        const response = await fetch('api/get_stats.php');
        const data = await response.json();
        
        // Update today's time
        const todayTime = document.getElementById('footerTodayTime');
        if (todayTime) todayTime.textContent = data.today_minutes || '0';
        
        // Update total quizzes
        const totalQuizzes = document.getElementById('footerTotalQuizzes');
        if (totalQuizzes) totalQuizzes.textContent = data.total_quizzes || '0';
        
        // Update accuracy
        const accuracy = document.getElementById('footerAccuracy');
        if (accuracy) accuracy.textContent = data.accuracy + '%' || '0%';
        
        // Update sidebar stats
        const completedQuizzes = document.getElementById('completedQuizzes');
        if (completedQuizzes) completedQuizzes.textContent = data.total_quizzes || '0';
        
        const averageScore = document.getElementById('averageScore');
        if (averageScore) averageScore.textContent = data.average_score || '0';
        
        const streakDays = document.getElementById('streakDays');
        if (streakDays) streakDays.textContent = data.streak_days || '0';
        
    } catch (error) {
        console.error('Error updating stats:', error);
    }
}

// Check server status
async function checkServerStatus() {
    try {
        await fetch('api/ping.php');
        document.getElementById('serverStatusIcon').style.color = '#2ecc71';
        document.getElementById('serverStatusText').textContent = 'Online';
    } catch (error) {
        document.getElementById('serverStatusIcon').style.color = '#e74c3c';
        document.getElementById('serverStatusText').textContent = 'Offline';
    }
}

// Update progress bar
function updateProgressBar() {
    const todayTime = parseInt(document.getElementById('footerTodayTime')?.textContent || '0');
    const goal = 60; // 60 minutes daily goal
    const percentage = Math.min(100, (todayTime / goal) * 100);
    
    const progressFill = document.getElementById('progressFill');
    const progressPercentage = document.getElementById('progressPercentage');
    const progressHint = document.getElementById('progressHint');
    
    if (progressFill) progressFill.style.width = percentage + '%';
    if (progressPercentage) progressPercentage.textContent = Math.round(percentage) + '%';
    
    if (progressHint) {
        if (percentage >= 100) {
            progressHint.innerHTML = '<i class="fas fa-check-circle"></i><span>Daily goal achieved! 🎉</span>';
            progressHint.style.color = '#2ecc71';
        } else if (percentage >= 50) {
            progressHint.innerHTML = '<i class="fas fa-tachometer-alt"></i><span>Keep going! ' + (goal - todayTime) + ' minutes to go</span>';
            progressHint.style.color = '#f39c12';
        } else {
            progressHint.innerHTML = '<i class="fas fa-clock"></i><span>' + (goal - todayTime) + ' minutes remaining</span>';
            progressHint.style.color = '#95a5a6';
        }
    }
}

// Toggle user menu
function toggleUserMenu() {
    const userMenu = document.getElementById('userMenu');
    userMenu.classList.toggle('show');
}

// Toggle sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('show');
}

// Show modal
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    const overlay = document.getElementById('modalOverlay');
    if (modal && overlay) {
        modal.classList.add('show');
        overlay.classList.add('show');
    }
}

// Close modal
function closeModal() {
    const modals = document.querySelectorAll('.modal.show');
    const overlays = document.querySelectorAll('.modal-overlay.show');
    modals.forEach(modal => modal.classList.remove('show'));
    overlays.forEach(overlay => overlay.classList.remove('show'));
}

// Close help modal
function closeHelpModal() {
    document.getElementById('helpModal').classList.remove('show');
}

// Show help modal
function showHelpModal() {
    document.getElementById('helpModal').classList.add('show');
}

// Toggle dark mode
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const icon = document.getElementById('darkModeIcon');
    if (icon) {
        icon.className = document.body.classList.contains('dark-mode') 
            ? 'fas fa-sun' 
            : 'fas fa-moon';
    }
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// Print page
function printPage() {
    window.print();
}

// Toggle fullscreen
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.log(`Error attempting to enable fullscreen: ${err.message}`);
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// Scroll to top
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Show welcome message
function showWelcomeMessage() {
    const notification = document.createElement('div');
    notification.className = 'notification success';
    notification.innerHTML = `
        <i class="fas fa-star"></i>
        <div>
            <strong>Welcome to Quiz Master!</strong>
            <p>Start your learning journey now. ${<?php echo $role === 'instructor' ? "'Upload some questions or'" : "'Select a unit from the menu to'"; ?>} begin.</p>
        </div>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.getElementById('notificationContainer').appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}

// Show loading
function showLoading() {
    document.getElementById('loadingOverlay').classList.add('show');
}

// Hide loading
function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('show');
}

// Search units in sidebar
document.getElementById('searchUnits')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const unitItems = document.querySelectorAll('.unit-item');
    
    unitItems.forEach(item => {
        const unitName = item.querySelector('h4').textContent.toLowerCase();
        const unitDesc = item.querySelector('.unit-meta').textContent.toLowerCase();
        
        if (unitName.includes(searchTerm) || unitDesc.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

// Toggle unit options
document.querySelectorAll('.unit-toggle').forEach(toggle => {
    toggle.addEventListener('click', function() {
        const unitItem = this.closest('.unit-item');
        unitItem.classList.toggle('expanded');
    });
});

// Update stats every minute
setInterval(updateFooterStats, 60000);

// Update progress bar every 30 seconds
setInterval(updateProgressBar, 30000);

// Check server status every 2 minutes
setInterval(checkServerStatus, 120000);
</script>

</body>
</html>
