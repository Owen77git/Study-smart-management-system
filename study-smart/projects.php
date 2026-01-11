<?php
require_once 'config.php';

if ($_SESSION['role'] !== 'student') {
    redirect('index.php');
}

// Handle project actions
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_project'])) {
        $project_data = [
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'deadline' => $_POST['deadline'],
            'priority' => $_POST['priority'],
            'tasks' => $_POST['tasks'] ?? []
        ];
        
        // Save project to database
        $stmt = $conn->prepare("
            INSERT INTO academic_projects 
            (user_id, title, description, deadline, priority, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("issss", $_SESSION['user_id'], 
            $project_data['title'], 
            $project_data['description'],
            $project_data['deadline'],
            $project_data['priority']
        );
        $stmt->execute();
        
        redirect('projects.php', 'Project added successfully!');
    }
}

// Get user's projects
$projects = $conn->query("
    SELECT * FROM academic_projects 
    WHERE user_id = {$_SESSION['user_id']}
    ORDER BY 
        CASE priority 
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END,
        deadline ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Projects - StudySmart</title>
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
            <a href="study_plan.php"><i class="fas fa-calendar-alt"></i> Study Plan</a>
            <a href="projects.php" class="active"><i class="fas fa-project-diagram"></i> Projects</a>
            <a href="progress.php"><i class="fas fa-chart-line"></i> Progress</a>
            <a href="results.php"><i class="fas fa-clipboard-check"></i> Results</a>
        </div>
    </div>

    <div class="content">
        <!-- Projects Header -->
        <div class="page-header">
            <h1><i class="fas fa-project-diagram"></i> Academic Projects</h1>
            <p>Manage your assignments, research papers, and academic projects</p>
            <button class="add-project-btn" onclick="showAddProjectModal()">
                <i class="fas fa-plus"></i> Add New Project
            </button>
        </div>

        <!-- Projects Grid -->
        <div class="projects-grid">
            <?php if ($projects->num_rows > 0): ?>
                <?php while ($project = $projects->fetch_assoc()): 
                    $days_left = floor((strtotime($project['deadline']) - time()) / (60 * 60 * 24));
                    $progress = $project['progress'] ?? 0;
                ?>
                <div class="project-card <?php echo $project['priority']; ?>">
                    <!-- Priority Badge -->
                    <div class="priority-badge">
                        <i class="fas fa-<?php echo $project['priority'] === 'high' ? 'exclamation-triangle' : 
                                                ($project['priority'] === 'medium' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                        <?php echo ucfirst($project['priority']); ?> Priority
                    </div>
                    
                    <!-- Project Info -->
                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                    <p class="project-description"><?php echo htmlspecialchars(substr($project['description'], 0, 100)); ?>...</p>
                    
                    <!-- Deadline -->
                    <div class="deadline-info">
                        <i class="far fa-calendar-alt"></i>
                        <span>Due: <?php echo date('M d, Y', strtotime($project['deadline'])); ?></span>
                        <span class="days-left <?php echo $days_left <= 3 ? 'urgent' : ''; ?>">
                            (<?php echo $days_left > 0 ? $days_left . ' days left' : 'Overdue'; ?>)
                        </span>
                    </div>
                    
                    <!-- Progress -->
                    <div class="project-progress">
                        <div class="progress-header">
                            <span>Progress</span>
                            <span><?php echo $progress; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="project-actions">
                        <button class="btn view-btn" onclick="viewProject(<?php echo $project['project_id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn edit-btn" onclick="editProject(<?php echo $project['project_id']; ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn delete-btn" onclick="deleteProject(<?php echo $project['project_id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks fa-3x"></i>
                    <h3>No Projects Yet</h3>
                    <p>Start by adding your first academic project or assignment</p>
                    <button class="add-project-btn" onclick="showAddProjectModal()">
                        <i class="fas fa-plus"></i> Add Your First Project
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Project Modal -->
<div id="projectModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-plus-circle"></i> Add New Project</h3>
        <form method="POST" id="projectForm">
            <div class="form-group">
                <label><i class="fas fa-heading"></i> Project Title</label>
                <input type="text" name="title" placeholder="e.g., Research Paper on AI, Math Assignment" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Description</label>
                <textarea name="description" rows="4" placeholder="Describe your project, requirements, and goals..." required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="far fa-calendar-alt"></i> Deadline</label>
                    <input type="date" name="deadline" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-flag"></i> Priority</label>
                    <select name="priority" required>
                        <option value="high">High Priority</option>
                        <option value="medium" selected>Medium Priority</option>
                        <option value="low">Low Priority</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-tasks"></i> Tasks (Optional)</label>
                <div id="taskContainer">
                    <div class="task-item">
                        <input type="text" name="tasks[]" placeholder="Task description">
                        <button type="button" class="remove-task" onclick="removeTask(this)">×</button>
                    </div>
                </div>
                <button type="button" class="add-task-btn" onclick="addTask()">
                    <i class="fas fa-plus"></i> Add Task
                </button>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" name="add_project" class="btn primary">Save Project</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddProjectModal() {
    document.getElementById('projectModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('projectModal').style.display = 'none';
}

function addTask() {
    const container = document.getElementById('taskContainer');
    const taskDiv = document.createElement('div');
    taskDiv.className = 'task-item';
    taskDiv.innerHTML = `
        <input type="text" name="tasks[]" placeholder="Task description">
        <button type="button" class="remove-task" onclick="removeTask(this)">×</button>
    `;
    container.appendChild(taskDiv);
}

function removeTask(button) {
    button.parentElement.remove();
}

function viewProject(projectId) {
    window.location.href = `project_view.php?id=${projectId}`;
}

function editProject(projectId) {
    // Load project data and show edit modal
    fetch(`get_project.php?id=${projectId}`)
    .then(response => response.json())
    .then(data => {
        // Populate form with project data
        // Show edit modal (similar to add modal)
    });
}

function deleteProject(projectId) {
    if (confirm('Delete this project? This action cannot be undone.')) {
        fetch(`delete_project.php?id=${projectId}`, { method: 'DELETE' })
        .then(() => location.reload());
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
}
</script>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.add-project-btn {
    background: linear-gradient(135deg, var(--success), #27ae60);
    color: white;
    border: none;
    padding: 15px 25px;
    border-radius: 25px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.add-project-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
}

.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.project-card {
    background: linear-gradient(145deg, #162a38, #1a2f3e);
    padding: 25px;
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    transition: all 0.3s ease;
}

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.project-card.high {
    border-left: 5px solid var(--danger);
}

.project-card.medium {
    border-left: 5px solid var(--warning);
}

.project-card.low {
    border-left: 5px solid var(--primary);
}

.priority-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 6px;
}

.project-card.high .priority-badge {
    background: rgba(231, 76, 60, 0.2);
    color: var(--danger);
}

.project-card.medium .priority-badge {
    background: rgba(243, 156, 18, 0.2);
    color: var(--warning);
}

.project-card.low .priority-badge {
    background: rgba(52, 152, 219, 0.2);
    color: var(--primary);
}

.project-card h3 {
    margin-bottom: 15px;
    color: white;
    font-size: 18px;
}

.project-description {
    color: #b0b0b0;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.5;
}

.deadline-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    color: #b0b0b0;
    font-size: 14px;
}

.days-left {
    margin-left: auto;
    font-weight: 600;
}

.days-left.urgent {
    color: var(--danger);
}

.project-progress {
    margin-bottom: 20px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
    color: #b0b0b0;
}

.project-actions {
    display: flex;
    gap: 10px;
}

.project-actions .btn {
    flex: 1;
    padding: 10px;
    font-size: 13px;
}

.view-btn {
    background: rgba(52, 152, 219, 0.2);
    color: var(--primary);
}

.edit-btn {
    background: rgba(243, 156, 18, 0.2);
    color: var(--warning);
}

.delete-btn {
    background: rgba(231, 76, 60, 0.2);
    color: var(--danger);
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.task-item {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.task-item input {
    flex: 1;
}

.remove-task {
    background: var(--danger);
    color: white;
    border: none;
    width: 30px;
    border-radius: 5px;
    cursor: pointer;
}

.add-task-btn {
    background: rgba(52, 152, 219, 0.2);
    color: var(--primary);
    border: 1px dashed var(--primary);
    padding: 10px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 10px;
    transition: all 0.3s ease;
}

.add-task-btn:hover {
    background: rgba(52, 152, 219, 0.4);
}
</style>
</body>
</html>