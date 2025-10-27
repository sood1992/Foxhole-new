<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/clean-style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <style>
    .calendar-container {
        background: white;
        padding: 24px;
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
    }

    .fc {
        font-family: 'Inter', sans-serif;
    }

    .fc-event {
        cursor: pointer;
        border-radius: 4px;
        padding: 2px 4px;
    }

    .fc-daygrid-event {
        white-space: normal;
    }

    .calendar-legend {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        padding: 16px;
        background: var(--bg-tertiary);
        border-radius: var(--radius-sm);
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 3px;
    }

    /* Event detail modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        padding: 24px;
        border-radius: var(--radius-md);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 20px;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 18px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 0;
        line-height: 1;
    }

    .modal-body {
        font-size: 14px;
    }

    .detail-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-light);
    }

    .detail-label {
        font-weight: 600;
        min-width: 120px;
        color: var(--text-secondary);
    }

    .detail-value {
        flex: 1;
    }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Neofox</h2>
                <div class="user-role"><?php echo hasRole('admin') ? 'Admin' : (hasRole('manager') ? 'Manager' : 'Employee'); ?> Panel</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="calendar.php" class="active">
                    <span class="icon">📅</span>
                    Calendar
                </a>
                <?php if (hasRole('admin') || hasRole('manager')): ?>
                <a href="analytics.php">
                    <span class="icon">📈</span>
                    Analytics
                </a>
                <a href="chat.php">
                    <span class="icon">💬</span>
                    Team Chat
                </a>
                <a href="projects.php">
                    <span class="icon">📁</span>
                    Projects
                </a>
                <?php endif; ?>
                <?php if (hasRole('employee')): ?>
                <a href="../employee/tasks.php">
                    <span class="icon">✓</span>
                    My Tasks
                </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo e($currentUser['full_name']); ?></h4>
                        <p><?php echo e($currentUser['job_title'] ?? 'User'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>📅 Calendar</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <div class="calendar-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #3b82f6;"></div>
                        <span>Tasks (Default)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f59e0b;"></div>
                        <span>High Priority</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #ef4444;"></div>
                        <span>Urgent</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #10b981;"></div>
                        <span>Completed</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #8b5cf6;"></div>
                        <span>Milestones</span>
                    </div>
                </div>

                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Event Detail Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Event Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Details will be populated here -->
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            events: function(info, successCallback, failureCallback) {
                fetch(`../api/calendar.php?start=${info.startStr}&end=${info.endStr}`)
                    .then(response => response.json())
                    .then(data => {
                        successCallback(data);
                    })
                    .catch(error => {
                        console.error('Error loading events:', error);
                        failureCallback(error);
                    });
            },
            eventClick: function(info) {
                showEventDetails(info.event);
            },
            editable: true,
            eventDrop: function(info) {
                // Update task due date when dragged
                if (info.event.extendedProps.type === 'task') {
                    updateTaskDate(info.event.extendedProps.taskId, info.event.startStr);
                }
            },
            height: 'auto',
            navLinks: true,
            dayMaxEvents: true
        });

        calendar.render();

        // Show event details
        window.showEventDetails = function(event) {
            const props = event.extendedProps;
            const modal = document.getElementById('eventModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');

            title.textContent = event.title;

            let html = '';

            if (props.type === 'task') {
                html = `
                    <div class="detail-row">
                        <div class="detail-label">Type</div>
                        <div class="detail-value">Task</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Project</div>
                        <div class="detail-value">${props.project || 'N/A'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Due Date</div>
                        <div class="detail-value">${new Date(event.start).toLocaleDateString()}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="badge ${getStatusClass(props.status)}">${props.status}</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Priority</div>
                        <div class="detail-value">
                            <span class="badge ${getPriorityClass(props.priority)}">${props.priority}</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Assigned To</div>
                        <div class="detail-value">${props.assignedTo || 'Unassigned'}</div>
                    </div>
                `;
            } else if (props.type === 'milestone') {
                html = `
                    <div class="detail-row">
                        <div class="detail-label">Type</div>
                        <div class="detail-value">Milestone</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Project</div>
                        <div class="detail-value">${props.project || 'N/A'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Due Date</div>
                        <div class="detail-value">${new Date(event.start).toLocaleDateString()}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            ${props.completed ? '✅ Completed' : '⏳ Pending'}
                        </div>
                    </div>
                `;
            }

            body.innerHTML = html;
            modal.classList.add('active');
        };

        // Close modal
        window.closeModal = function() {
            document.getElementById('eventModal').classList.remove('active');
        };

        // Update task date
        window.updateTaskDate = function(taskId, newDate) {
            fetch('../api/calendar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    task_id: taskId,
                    new_date: newDate.split('T')[0]
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Task date updated');
                } else {
                    alert('Failed to update task date');
                    calendar.refetchEvents();
                }
            })
            .catch(error => {
                alert('Error updating task date');
                calendar.refetchEvents();
            });
        };

        // Helper functions
        function getStatusClass(status) {
            const classes = {
                'todo': 'status-todo',
                'in_progress': 'status-progress',
                'review': 'status-review',
                'completed': 'status-completed',
                'blocked': 'status-blocked'
            };
            return classes[status] || 'status-default';
        }

        function getPriorityClass(priority) {
            const classes = {
                'low': 'priority-low',
                'medium': 'priority-medium',
                'high': 'priority-high',
                'urgent': 'priority-urgent'
            };
            return classes[priority] || 'priority-medium';
        }

        // Close modal when clicking outside
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    });
    </script>
</body>
</html>
