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
    <title>Calendar V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="../assets/js/calendar-events.js"></script>
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
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h1 style="margin-bottom: 8px;">Calendar</h1>
                        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                            View and manage tasks, shoots, edits, and reviews
                        </p>
                    </div>
                    <button class="btn btn-primary" onclick="openAddEventModal()">
                        <i class="fas fa-plus"></i> Add Event
                    </button>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-nav" style="margin-bottom: 30px;">
                    <a href="#" class="tab-link" data-view="dayGridMonth">
                        <i class="fas fa-calendar"></i> Month
                    </a>
                    <a href="#" class="tab-link" data-view="timeGridWeek">
                        <i class="fas fa-calendar-week"></i> Week
                    </a>
                    <a href="#" class="tab-link" data-view="timeGridDay">
                        <i class="fas fa-calendar-day"></i> Day
                    </a>
                    <a href="#" class="tab-link" data-view="listWeek">
                        <i class="fas fa-list"></i> Agenda
                    </a>
                </div>

                <div class="calendar-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #FF6B6B;"></div>
                        <span>🎥 Shoot</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #4ECDC4;"></div>
                        <span>✂️ Edit Session</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #FFE66D;"></div>
                        <span>👁️ Client Review</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #95E1D3;"></div>
                        <span>👥 Meeting</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #F38181;"></div>
                        <span>⏰ Deadline</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #AA96DA;"></div>
                        <span>📦 Delivery</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #3b82f6;"></div>
                        <span>📋 Tasks</span>
                    </div>
                </div>

                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
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
    let calendarInstance; // Store calendar instance globally

    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');

        // Determine initial view from URL hash
        let initialView = 'dayGridMonth';
        const hash = window.location.hash.slice(1);
        if (hash === 'today' || hash === 'day') {
            initialView = 'timeGridDay';
        } else if (hash === 'week') {
            initialView = 'timeGridWeek';
        } else if (hash === 'month') {
            initialView = 'dayGridMonth';
        }

        calendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: initialView,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: '' // Remove toolbar buttons, use tabs instead
            },
            events: function(info, successCallback, failureCallback) {
                // Load both task events and calendar events
                Promise.all([
                    fetch(`../api/calendar.php?start=${info.startStr}&end=${info.endStr}`).then(r => r.json()),
                    fetch(`../api/calendar-events.php?start=${info.startStr}&end=${info.endStr}`).then(r => r.json())
                ])
                .then(([tasksData, eventsData]) => {
                    const allEvents = [
                        ...(Array.isArray(tasksData) ? tasksData : []),
                        ...(eventsData.success && eventsData.events ? eventsData.events : [])
                    ];
                    successCallback(allEvents);
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
            dayMaxEvents: true,
            viewDidMount: function(info) {
                // Update active tab when view changes
                updateActiveTab(info.view.type);
            }
        });

        // Update active tab indicator
        function updateActiveTab(viewType) {
            const tabLinks = document.querySelectorAll('.tab-link[data-view]');
            tabLinks.forEach(link => link.classList.remove('active'));
            const activeLink = document.querySelector(`.tab-link[data-view="${viewType}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
        }

        // Tab navigation handlers - set up before render
        const tabLinks = document.querySelectorAll('.tab-link[data-view]');
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const viewName = this.getAttribute('data-view');
                console.log('Switching to view:', viewName, 'Calendar instance:', calendarInstance);
                if (calendarInstance) {
                    try {
                        calendarInstance.changeView(viewName);
                        updateActiveTab(viewName);
                        console.log('View changed successfully to:', viewName);
                    } catch (error) {
                        console.error('Error changing view:', error);
                    }
                } else {
                    console.error('Calendar instance not initialized');
                }
            });
        });

        // Render calendar and set initial active tab
        calendarInstance.render();
        updateActiveTab(initialView);
        console.log('Calendar initialized with view:', initialView);

        // Initialize calendar events enhancement
        if (typeof initializeCalendarEvents === 'function') {
            initializeCalendarEvents(calendarInstance, {
                userRole: '<?php echo $_SESSION['role']; ?>'
            });
        }

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
                            ${props.completed ? '<i class="fas fa-check-circle"></i> Completed' : '<i class="fas fa-clock"></i> Pending'}
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
    <script src="../assets/js/theme.js"></script>
</body>
</html>
