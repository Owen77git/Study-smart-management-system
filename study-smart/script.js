// script.js - COMPLETE VERSION

// Global Variables
let sessionTimer;
let dailyTimer;
let currentSessionStart;
let darkMode = localStorage.getItem('darkMode') === 'true';

// Initialize when DOM loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dark mode
    if (darkMode) {
        document.body.classList.add('dark-mode');
        updateDarkModeToggle();
    }
    
    // Initialize sidebar toggle
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            menuToggle.innerHTML = sidebar.classList.contains('show') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });
    }
    
    if (closeSidebar) {
        closeSidebar.addEventListener('click', function() {
            sidebar.classList.remove('show');
            if (menuToggle) {
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
    }
    
    // Initialize unit submenus
    document.querySelectorAll('.toggle-submenu').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const unitHeader = this.closest('.unit-header');
            unitHeader.classList.toggle('active');
        });
    });
    
    // Initialize add unit modal
    const addUnitBtn = document.getElementById('addUnitBtn');
    const unitModal = document.getElementById('unitModal');
    
    if (addUnitBtn && unitModal) {
        addUnitBtn.addEventListener('click', function() {
            unitModal.style.display = 'flex';
            document.getElementById('unit_name').focus();
        });
        
        // Close modal when clicking outside
        unitModal.addEventListener('click', function(e) {
            if (e.target === unitModal) {
                closeModal();
            }
        });
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && unitModal.style.display === 'flex') {
                closeModal();
            }
        });
    }
    
    // Initialize back to top button
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTop.style.display = 'flex';
            } else {
                backToTop.style.display = 'none';
            }
        });
        
        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // Initialize student session timer if on student page
    if (document.getElementById('liveTimer') || document.getElementById('sessionTime')) {
        startSessionTracking();
    }
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize auto-growing textareas
    document.querySelectorAll('.answer-textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            autoGrow(this);
            checkQuizCompletion();
        });
    });
    
    // Initialize MCQ radio buttons
    document.querySelectorAll('.mcq-radio').forEach(radio => {
        radio.addEventListener('change', checkQuizCompletion);
    });
    
    // Initialize instance number navigation
    document.querySelectorAll('.instance-number, .instance-tab, .result-tab').forEach(tab => {
        tab.addEventListener('click', function(e) {
            if (!this.getAttribute('href')) {
                e.preventDefault();
                const instanceNum = this.textContent.trim();
                loadInstance(instanceNum);
            }
        });
    });
});

// Modal Functions
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }
}

function closeModal() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
        modal.classList.remove('show');
    });
}

// Dark Mode Toggle
function toggleDarkMode() {
    darkMode = !darkMode;
    document.body.classList.toggle('dark-mode', darkMode);
    localStorage.setItem('darkMode', darkMode);
    updateDarkModeToggle();
}

function updateDarkModeToggle() {
    const toggleBtns = document.querySelectorAll('[onclick="toggleDarkMode()"]');
    toggleBtns.forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = darkMode ? 'fas fa-sun' : 'fas fa-moon';
        }
    });
}

// Timer Functions
function startSessionTracking() {
    currentSessionStart = new Date();
    
    // Update live timer every second
    if (document.getElementById('liveTimer')) {
        sessionTimer = setInterval(updateLiveTimer, 1000);
    }
    
    // Update session time every minute
    if (document.getElementById('sessionTime')) {
        dailyTimer = setInterval(updateDailyTime, 60000);
        updateDailyTime();
    }
    
    // Send start time to server
    fetch('api/track_session.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start' })
    });
}

function updateLiveTimer() {
    const now = new Date();
    const diff = Math.floor((now - currentSessionStart) / 1000);
    const minutes = Math.floor(diff / 60);
    const seconds = diff % 60;
    
    const timerEl = document.getElementById('liveTimer');
    if (timerEl) {
        timerEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
}

async function updateDailyTime() {
    try {
        const response = await fetch('api/get_daily_time.php');
        const data = await response.json();
        
        const dailyTimeEl = document.getElementById('dailyTime');
        const sessionTimeEl = document.getElementById('sessionTime');
        
        if (dailyTimeEl && data.daily_minutes) {
            dailyTimeEl.textContent = data.daily_minutes;
        }
        
        if (sessionTimeEl && data.session_minutes) {
            sessionTimeEl.textContent = data.session_minutes;
        }
    } catch (error) {
        console.error('Error updating daily time:', error);
    }
}

function resetTimer() {
    if (confirm('Reset current session timer?')) {
        clearInterval(sessionTimer);
        clearInterval(dailyTimer);
        
        fetch('api/track_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset' })
        }).then(() => {
            startSessionTracking();
        });
    }
}

// Form Functions
function autoGrow(element) {
    element.style.height = 'auto';
    element.style.height = (element.scrollHeight) + 'px';
}

function checkQuizCompletion() {
    let answered = 0;
    const totalQuestions = 30;
    
    // Check MCQ answers
    document.querySelectorAll('.mcq-radio:checked').forEach(() => answered++);
    
    // Check Non-MCQ answers
    document.querySelectorAll('.answer-textarea').forEach(textarea => {
        if (textarea.value.trim().length > 0) answered++;
    });
    
    // Update UI
    const submitBtn = document.getElementById('submitBtn');
    const answeredCount = document.getElementById('answeredCount');
    const progressIndicator = document.querySelector('.progress-indicator');
    
    if (answeredCount) answeredCount.textContent = answered;
    
    if (submitBtn) {
        const isComplete = answered === totalQuestions;
        submitBtn.disabled = !isComplete;
        submitBtn.classList.toggle('ready', isComplete);
        
        if (isComplete) {
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span>SUBMIT ANSWERS</span>';
        } else {
            const remaining = totalQuestions - answered;
            submitBtn.innerHTML = `<i class="fas fa-clock"></i><span>${remaining} QUESTIONS LEFT</span>`;
        }
    }
    
    if (progressIndicator) {
        const percentage = (answered / totalQuestions) * 100;
        progressIndicator.style.background = `linear-gradient(to right, var(--primary) ${percentage}%, var(--light) ${percentage}%)`;
        progressIndicator.style.backgroundSize = '100% 3px';
        progressIndicator.style.backgroundRepeat = 'no-repeat';
        progressIndicator.style.backgroundPosition = 'bottom';
    }
    
    return answered === totalQuestions;
}

function initializeFormValidation() {
    const forms = document.querySelectorAll('form:not(.no-validate)');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Create error message if not exists
                    if (!field.nextElementSibling?.classList.contains('error-message')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        errorMsg.textContent = 'This field is required';
                        errorMsg.style.color = 'var(--danger)';
                        errorMsg.style.fontSize = '0.8rem';
                        errorMsg.style.marginTop = '0.3rem';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                } else {
                    field.classList.remove('error');
                    const errorMsg = field.nextElementSibling;
                    if (errorMsg?.classList.contains('error-message')) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
}

// Instance Management
function loadInstance(instanceNum) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('instance', instanceNum);
    
    // Show loading state
    const mainContent = document.querySelector('.main-content-area');
    if (mainContent) {
        mainContent.innerHTML = `
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading quiz instance ${instanceNum}...</p>
            </div>
        `;
    }
    
    window.location.href = currentUrl.toString();
}

function repeatQuiz(instanceId) {
    if (confirm('Repeat this quiz? Your previous score will be cleared.')) {
        fetch('api/repeat_quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ instance_id: instanceId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page or redirect
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while repeating the quiz.');
        });
    }
}

// Export Functions
function exportResults(format = 'pdf') {
    const instanceId = new URLSearchParams(window.location.search).get('instance');
    
    fetch(`api/export_results.php?instance=${instanceId}&format=${format}`)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `quiz_results_${instanceId}.${format}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
        })
        .catch(error => {
            console.error('Export error:', error);
            alert('Error exporting results.');
        });
}

// Utility Functions
function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Event Listeners for Dynamic Elements
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('role-input')) {
        const startBtn = document.getElementById('startBtn');
        if (startBtn) {
            startBtn.disabled = false;
            startBtn.style.opacity = '1';
            startBtn.style.transform = 'scale(1.02)';
        }
    }
});

// Watermark Click Handler
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('watermark') || 
        e.target.closest('.watermark') || 
        e.target.classList.contains('watermark-big')) {
        const watermark = e.target.classList.contains('watermark') ? e.target : 
                         e.target.closest('.watermark') || e.target;
        
        watermark.style.opacity = '0';
        watermark.style.transform = 'scale(0.8)';
        
        setTimeout(() => {
            watermark.style.display = 'none';
            
            // Show next section if exists
            const nextSection = watermark.nextElementSibling || 
                               watermark.parentNode.nextElementSibling;
            if (nextSection && nextSection.style.display === 'none') {
                nextSection.style.display = 'block';
                setTimeout(() => {
                    nextSection.style.opacity = '1';
                    nextSection.style.transform = 'translateY(0)';
                }, 50);
            }
        }, 500);
    }
});

// Print Results
function printResults() {
    window.print();
}

// Copy to Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show success message
        const msg = document.createElement('div');
        msg.className = 'copy-success';
        msg.textContent = 'Copied to clipboard!';
        msg.style.position = 'fixed';
        msg.style.bottom = '20px';
        msg.style.right = '20px';
        msg.style.background = 'var(--secondary)';
        msg.style.color = 'white';
        msg.style.padding = '0.5rem 1rem';
        msg.style.borderRadius = 'var(--radius-sm)';
        msg.style.zIndex = '10000';
        
        document.body.appendChild(msg);
        
        setTimeout(() => {
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 300);
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard.');
    });
}

// Initialize tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.title;
            tooltip.style.position = 'absolute';
            tooltip.style.background = 'var(--dark)';
            tooltip.style.color = 'white';
            tooltip.style.padding = '0.5rem';
            tooltip.style.borderRadius = 'var(--radius-sm)';
            tooltip.style.fontSize = '0.9rem';
            tooltip.style.zIndex = '10000';
            tooltip.style.whiteSpace = 'nowrap';
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
}

// Call initialization
setTimeout(initTooltips, 1000);
