<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
if (!isLoggedIn() || !hasRole('employee')) redirect('../login.php');

$db = getDBConnection();
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Morning Ritual - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .ritual-item {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .ritual-item.completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-color: #10b981;
        }
        .ritual-checkbox {
            width: 30px;
            height: 30px;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.3s;
        }
        .ritual-item.completed .ritual-checkbox {
            background: white;
            border-color: white;
            color: #10b981;
        }
        .ritual-checkbox:hover {
            transform: scale(1.1);
        }
        .ritual-name {
            flex: 1;
            font-size: 16px;
        }
        .ritual-actions {
            display: flex;
            gap: 10px;
        }
        .ritual-delete {
            color: #ef4444;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .ritual-delete:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        .completion-banner {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
            display: none;
        }
        .completion-banner.show {
            display: block;
            animation: slideDown 0.5s ease-out;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .add-ritual-form {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }
        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        .progress-ring-circle {
            stroke: white;
            fill: transparent;
            stroke-width: 8;
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 0.5s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .progress-text {
            font-size: 32px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-employee-sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>
            <div class="content-wrapper">
                <h1><i class="fas fa-sunrise"></i> Morning Ritual</h1>
                <p style="color:var(--text-secondary);margin-bottom:30px;">
                    Start your day right with your personalized morning routine
                </p>

                <!-- Completion Banner -->
                <div id="completionBanner" class="completion-banner">
                    <svg class="progress-ring">
                        <circle class="progress-ring-circle" cx="60" cy="60" r="45"></circle>
                    </svg>
                    <h2 style="margin-top:20px;">🎉 All rituals completed!</h2>
                    <p>Great start to your day! You're ready to conquer your tasks.</p>
                </div>

                <!-- Today's Progress -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Today's Progress</h3>
                    </div>
                    <div class="card-body">
                        <div style="background:#e5e7eb;height:30px;border-radius:15px;overflow:hidden;">
                            <div id="progressBar" style="background:linear-gradient(90deg,#10b981,#059669);height:100%;width:0%;transition:width 0.5s;"></div>
                        </div>
                        <p id="progressText" style="text-align:center;margin-top:10px;font-weight:bold;">0 of 0 completed</p>
                    </div>
                </div>

                <!-- Ritual List -->
                <div class="dashboard-card" style="margin-top:20px;">
                    <div class="card-header">
                        <h3>Your Morning Rituals</h3>
                    </div>
                    <div class="card-body">
                        <div id="ritualList">
                            <p style="text-align:center;color:var(--text-secondary);padding:40px;">
                                <i class="fas fa-sun" style="font-size:48px;opacity:0.3;"></i><br><br>
                                No rituals yet. Add your first morning ritual below!
                            </p>
                        </div>

                        <div class="add-ritual-form">
                            <input type="text" id="newRitualName" class="form-control" placeholder="Add a new ritual (e.g., Meditate for 10 minutes)" style="flex:1;">
                            <button onclick="addRitual()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Suggested Rituals -->
                <div class="dashboard-card" style="margin-top:20px;">
                    <div class="card-header">
                        <h3>Suggested Rituals</h3>
                    </div>
                    <div class="card-body">
                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                            <button onclick="quickAddRitual('Drink a glass of water')" class="btn" style="background:var(--bg-secondary);">
                                💧 Drink water
                            </button>
                            <button onclick="quickAddRitual('Meditate for 10 minutes')" class="btn" style="background:var(--bg-secondary);">
                                🧘 Meditate
                            </button>
                            <button onclick="quickAddRitual('Exercise for 20 minutes')" class="btn" style="background:var(--bg-secondary);">
                                🏃 Exercise
                            </button>
                            <button onclick="quickAddRitual('Review my goals')" class="btn" style="background:var(--bg-secondary);">
                                🎯 Review goals
                            </button>
                            <button onclick="quickAddRitual('Plan my day')" class="btn" style="background:var(--bg-secondary);">
                                📝 Plan day
                            </button>
                            <button onclick="quickAddRitual('Read for 15 minutes')" class="btn" style="background:var(--bg-secondary);">
                                📚 Read
                            </button>
                            <button onclick="quickAddRitual('Gratitude journaling')" class="btn" style="background:var(--bg-secondary);">
                                ✨ Gratitude
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let rituals = [];

        function loadRituals() {
            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_morning_rituals' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    rituals = data.rituals;
                    renderRituals();
                }
            });
        }

        function renderRituals() {
            const list = document.getElementById('ritualList');

            if (rituals.length === 0) {
                list.innerHTML = `
                    <p style="text-align:center;color:var(--text-secondary);padding:40px;">
                        <i class="fas fa-sun" style="font-size:48px;opacity:0.3;"></i><br><br>
                        No rituals yet. Add your first morning ritual below!
                    </p>
                `;
                updateProgress();
                return;
            }

            list.innerHTML = rituals.map(ritual => `
                <div class="ritual-item ${ritual.completed_today > 0 ? 'completed' : ''}">
                    <div class="ritual-checkbox" onclick="toggleRitual(${ritual.id}, ${ritual.completed_today > 0 ? 1 : 0})">
                        ${ritual.completed_today > 0 ? '<i class="fas fa-check"></i>' : ''}
                    </div>
                    <div class="ritual-name">${ritual.ritual_name}</div>
                    <div class="ritual-actions">
                        <span class="ritual-delete" onclick="deleteRitual(${ritual.id})">
                            <i class="fas fa-trash"></i>
                        </span>
                    </div>
                </div>
            `).join('');

            updateProgress();
        }

        function updateProgress() {
            const total = rituals.length;
            const completed = rituals.filter(r => r.completed_today > 0).length;
            const percentage = total > 0 ? (completed / total) * 100 : 0;

            document.getElementById('progressBar').style.width = percentage + '%';
            document.getElementById('progressText').textContent = `${completed} of ${total} completed`;

            // Show completion banner if all done
            if (total > 0 && completed === total) {
                document.getElementById('completionBanner').classList.add('show');
            } else {
                document.getElementById('completionBanner').classList.remove('show');
            }
        }

        function toggleRitual(ritualId, isCompleted) {
            const action = isCompleted ? 'uncomplete_morning_ritual' : 'complete_morning_ritual';

            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    ritual_id: ritualId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadRituals();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function addRitual() {
            const name = document.getElementById('newRitualName').value.trim();
            if (!name) {
                alert('Please enter a ritual name');
                return;
            }

            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_morning_ritual',
                    ritual_name: name,
                    ritual_order: rituals.length
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('newRitualName').value = '';
                    loadRituals();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function quickAddRitual(name) {
            document.getElementById('newRitualName').value = name;
            addRitual();
        }

        function deleteRitual(ritualId) {
            if (!confirm('Delete this ritual?')) return;

            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_morning_ritual',
                    ritual_id: ritualId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadRituals();
                }
            });
        }

        // Allow Enter key to add ritual
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('newRitualName').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    addRitual();
                }
            });
        });

        // Initialize
        loadRituals();
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
