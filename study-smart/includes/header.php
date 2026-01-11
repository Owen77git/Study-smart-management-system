<?php
// includes/header.php
if (!isset($page_title)) {
    $page_title = 'Quiz Master';
}

// Set default role if not set
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'none';
}

$role = $_SESSION['role'];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Quiz Master</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/icons/favicon.ico">
    
    <!-- CSS -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    
    <!-- Theme Color for Mobile Browsers -->
    <meta name="theme-color" content="#3498db">
    
    <!-- iOS Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>
    <?php if ($role !== 'none'): ?>
    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-left">
            <?php if ($role === 'instructor'): ?>
                <!-- Instructor: Add Unit Button -->
                <button class="nav-btn" id="addUnitBtn" title="Add New Unit">
                    <i class="fas fa-plus"></i>
                    <span class="nav-btn-text">Add Unit</span>
                </button>
                
                <!-- Current Page Indicator -->
                <div class="page-indicator">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>INSTRUCTOR MODE</span>
                </div>
                
            <?php elseif ($role === 'student'): ?>
                <!-- Student: Menu Toggle -->
                <button class="nav-btn" id="menuToggle" title="Toggle Menu">
                    <i class="fas fa-bars"></i>
                    <span class="nav-btn-text">Menu</span>
                </button>
                
                <!-- Current Page Indicator -->
                <div class="page-indicator">
                    <i class="fas fa-graduation-cap"></i>
                    <span>STUDENT MODE</span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Center: Instance Numbers (Student only on quiz pages) -->
        <div class="nav-center">
            <?php if ($role === 'student' && ($current_page === 'student.php' || $current_page === 'review.php' || $current_page === 'results.php')): ?>
                <div class="instance-numbers">
                    <?php
                    // Show instance numbers 1-5
                    for ($i = 1; $i <= 5; $i++): 
                        $is_active = false;
                        if (isset($_GET['instance']) && $_GET['instance'] == $i) {
                            $is_active = true;
                        } elseif (!isset($_GET['instance']) && $i == 1) {
                            $is_active = true;
                        }
                    ?>
                        <a href="<?php echo $current_page; ?>?instance=<?php echo $i; ?><?php echo isset($_GET['unit']) ? '&unit=' . $_GET['unit'] : ''; ?><?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] : ''; ?>" 
                           class="instance-number <?php echo $is_active ? 'active' : ''; ?>"
                           title="Quiz Instance <?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right: User Actions -->
        <div class="nav-right">
            <!-- Role Switch -->
            <?php if ($role === 'instructor'): ?>
                <a href="student.php" class="nav-action" title="Switch to Student Mode">
                    <i class="fas fa-graduation-cap"></i>
                    <span class="nav-action-text">Student View</span>
                </a>
            <?php elseif ($role === 'student'): ?>
                <a href="instructor.php" class="nav-action" title="Switch to Instructor Mode">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span class="nav-action-text">Instructor View</span>
                </a>
            <?php endif; ?>
            
            <!-- Home Button -->
            <a href="<?php echo ($role === 'none') ? 'index.php' : ($role === 'instructor' ? 'instructor.php' : 'student.php'); ?>" 
               class="nav-action" title="Home">
                <i class="fas fa-home"></i>
                <span class="nav-action-text">Home</span>
            </a>
            
            <!-- User Profile -->
            <div class="user-profile">
                <div class="user-avatar">
                    <?php if ($role === 'instructor'): ?>
                        <i class="fas fa-user-tie"></i>
                    <?php elseif ($role === 'student'): ?>
                        <i class="fas fa-user-graduate"></i>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <span class="user-name">OWEN</span>
                    <span class="user-role"><?php echo ucfirst($role); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Student Sidebar Menu -->
    <?php if ($role === 'student'): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>
                <i class="fas fa-layer-group"></i>
                <span>UNITS & QUIZZES</span>
            </h3>
            <button class="close-sidebar" id="closeSidebar" title="Close Menu">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-content">
            <!-- Search Units -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchUnits" placeholder="Search units...">
            </div>
            
            <!-- Units List -->
            <div class="units-list" id="unitsList">
                <?php
                require_once 'config.php';
                $units = $conn->query("SELECT * FROM units WHERE is_active = TRUE ORDER BY unit_name");
                
                if ($units->num_rows > 0):
                    while ($unit = $units->fetch_assoc()): 
                        $unit_id = $unit['unit_id'];
                        
                        // Count available questions
                        $mcq_count = $conn->query("SELECT COUNT(*) as cnt FROM mcq_questions WHERE unit_id = $unit_id AND is_pushed_to_main = TRUE")->fetch_assoc()['cnt'];
                        $non_mcq_count = $conn->query("SELECT COUNT(*) as cnt FROM non_mcq_questions WHERE unit_id = $unit_id AND is_pushed_to_main = TRUE")->fetch_assoc()['cnt'];
                    ?>
                        <div class="unit-item" data-unit-id="<?php echo $unit_id; ?>">
                            <div class="unit-header">
                                <div class="unit-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="unit-details">
                                    <h4><?php echo htmlspecialchars($unit['unit_name']); ?></h4>
                                    <p class="unit-meta">
                                        <span class="question-count">
                                            <i class="fas fa-list-ol"></i> <?php echo $mcq_count; ?> MCQ
                                        </span>
                                        <span class="question-count">
                                            <i class="fas fa-font"></i> <?php echo $non_mcq_count; ?> Non-MCQ
                                        </span>
                                    </p>
                                </div>
                                <i class="fas fa-chevron-down unit-toggle"></i>
                            </div>
                            
                            <div class="unit-options">
                                <!-- MCQ Option -->
                                <a href="student.php?unit=<?php echo $unit_id; ?>&type=mcq" 
                                   class="quiz-option <?php echo ($mcq_count < 30) ? 'disabled' : ''; ?>"
                                   <?php echo ($mcq_count < 30) ? 'onclick="return false;"' : ''; ?>>
                                    <div class="option-icon">
                                        <i class="fas fa-list-ol"></i>
                                    </div>
                                    <div class="option-info">
                                        <span class="option-title">Multiple Choice</span>
                                        <span class="option-desc">
                                            <?php echo ($mcq_count >= 30) ? '30 questions available' : 'Need ' . (30 - $mcq_count) . ' more questions'; ?>
                                        </span>
                                    </div>
                                    <?php if ($mcq_count >= 30): ?>
                                        <span class="option-badge ready">
                                            <i class="fas fa-play"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="option-badge disabled">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                
                                <!-- Non-MCQ Option -->
                                <a href="student.php?unit=<?php echo $unit_id; ?>&type=non_mcq" 
                                   class="quiz-option <?php echo ($non_mcq_count < 30) ? 'disabled' : ''; ?>"
                                   <?php echo ($non_mcq_count < 30) ? 'onclick="return false;"' : ''; ?>>
                                    <div class="option-icon">
                                        <i class="fas fa-font"></i>
                                    </div>
                                    <div class="option-info">
                                        <span class="option-title">Open-ended</span>
                                        <span class="option-desc">
                                            <?php echo ($non_mcq_count >= 30) ? '30 questions available' : 'Need ' . (30 - $non_mcq_count) . ' more questions'; ?>
                                        </span>
                                    </div>
                                    <?php if ($non_mcq_count >= 30): ?>
                                        <span class="option-badge ready">
                                            <i class="fas fa-play"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="option-badge disabled">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No units available</p>
                        <small>Instructor needs to add units first</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <!-- Session Timer -->
            <div class="timer-widget">
                <div class="timer-header">
                    <i class="fas fa-clock"></i>
                    <span>Study Session</span>
                </div>
                <div class="timer-display">
                    <div class="timer-segment">
                        <span class="timer-value" id="sessionTimer">00:00</span>
                        <span class="timer-label">Current</span>
                    </div>
                    <div class="timer-segment">
                        <span class="timer-value" id="dailyTimer">0</span>
                        <span class="timer-label">Today (min)</span>
                    </div>
                </div>
                <button class="timer-control" onclick="resetTimer()" title="Reset Timer">
                    <i class="fas fa-redo"></i>
                </button>
            </div>
            
            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-item">
                    <i class="fas fa-check-circle"></i>
                    <span id="completedQuizzes">0</span>
                    <small>Quizzes</small>
                </div>
                <div class="stat-item">
                    <i class="fas fa-chart-line"></i>
                    <span id="averageScore">0</span>
                    <small>Avg %</small>
                </div>
                <div class="stat-item">
                    <i class="fas fa-fire"></i>
                    <span id="streakDays">0</span>
                    <small>Streak</small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Unit Modal (Instructor Only) -->
    <?php if ($role === 'instructor'): ?>
    <div class="modal-overlay" id="modalOverlay"></div>
    <div class="modal" id="unitModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-plus-circle"></i>
                    <span>Create New Unit</span>
                </h2>
                <button class="modal-close" onclick="closeModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form method="POST" action="instructor.php" class="modal-form" id="unitForm">
                    <div class="form-group">
                        <label for="unit_name">
                            <i class="fas fa-book"></i>
                            <span>Unit Name</span>
                        </label>
                        <input type="text" 
                               id="unit_name" 
                               name="unit_name" 
                               placeholder="e.g., Biology Unit 1, Chemistry Basics, Physics 101"
                               required
                               autofocus>
                        <div class="form-hint">Give your unit a descriptive name</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="unit_description">
                            <i class="fas fa-align-left"></i>
                            <span>Description (Optional)</span>
                        </label>
                        <textarea id="unit_description" 
                                  name="unit_description" 
                                  placeholder="Brief description of what this unit covers..."
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="unit_category">
                            <i class="fas fa-tags"></i>
                            <span>Category (Optional)</span>
                        </label>
                        <select id="unit_category" name="unit_category">
                            <option value="">Select category</option>
                            <option value="science">Science</option>
                            <option value="math">Mathematics</option>
                            <option value="language">Language</option>
                            <option value="history">History</option>
                            <option value="general">General Knowledge</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    <span>Cancel</span>
                </button>
                <button type="submit" form="unitForm" name="add_unit" class="btn btn-primary">
                    <i class="fas fa-check"></i>
                    <span>Create Unit</span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
    </div>

    <!-- Notification Container -->
    <div class="notification-container" id="notificationContainer"></div>

    <!-- Main Content Wrapper -->
    <div class="content-wrapper">
<?php endif; // End of header for logged-in users ?>
