<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$reviewType = $_GET['type'] ?? 'weekly';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($reviewType); ?> Review - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .review-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .review-header {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            color: white;
            margin-bottom: 32px;
        }

        .review-header h1 {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .review-header p {
            font-size: 18px;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: var(--radius-lg);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .review-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 32px;
            margin-bottom: 24px;
        }

        .review-section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .review-section-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .review-section-icon {
            font-size: 24px;
            margin-right: 12px;
        }

        .mood-selector {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 24px 0;
        }

        .mood-btn {
            width: 60px;
            height: 60px;
            border: 3px solid var(--border-color);
            background: var(--bg-tertiary);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mood-btn:hover {
            transform: scale(1.1);
        }

        .mood-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            transform: scale(1.15);
        }

        .rating-slider {
            width: 100%;
            margin: 20px 0;
        }

        .rating-value {
            text-align: center;
            font-size: 48px;
            font-weight: 700;
            color: var(--primary);
            margin: 20px 0;
        }

        .history-item {
            padding: 20px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .history-item:hover {
            transform: translateX(4px);
            background: var(--bg-secondary);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .history-period {
            font-size: 18px;
            font-weight: 600;
        }

        .history-mood {
            font-size: 24px;
        }

        .history-preview {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="review-container">
                    <!-- Review Type Selector -->
                    <div style="display: flex; gap: 12px; justify-content: center; margin-bottom: 32px;">
                        <a href="?type=weekly" class="btn <?php echo $reviewType === 'weekly' ? 'btn-primary' : 'btn-secondary'; ?> btn-lg">
                            <i class="fas fa-calendar-week"></i> Weekly Review
                        </a>
                        <a href="?type=monthly" class="btn <?php echo $reviewType === 'monthly' ? 'btn-primary' : 'btn-secondary'; ?> btn-lg">
                            <i class="fas fa-calendar-alt"></i> Monthly Review
                        </a>
                    </div>

                    <!-- Review Header -->
                    <div class="review-header">
                        <h1 id="reviewTitle">📝 Weekly Review</h1>
                        <p id="reviewSubtitle">Reflect on your accomplishments and plan ahead</p>
                        <p id="reviewPeriod" style="margin-top: 16px; font-size: 16px;"></p>
                    </div>

                    <!-- Period Stats -->
                    <div id="statsGrid" class="stats-grid">
                        <!-- Stats will be loaded here -->
                    </div>

                    <!-- Review Form -->
                    <div class="review-section">
                        <div class="review-section-title">
                            <span class="review-section-icon">😊</span>
                            How was your <?php echo $reviewType === 'weekly' ? 'week' : 'month'; ?>?
                        </div>
                        <div class="review-section-subtitle">
                            Select your overall mood
                        </div>
                        <div class="mood-selector">
                            <button class="mood-btn" data-mood="great" title="Great">🤩</button>
                            <button class="mood-btn" data-mood="good" title="Good">😊</button>
                            <button class="mood-btn" data-mood="okay" title="Okay">😐</button>
                            <button class="mood-btn" data-mood="challenging" title="Challenging">😰</button>
                            <button class="mood-btn" data-mood="difficult" title="Difficult">😓</button>
                        </div>
                    </div>

                    <div class="review-section">
                        <div class="review-section-title">
                            <span class="review-section-icon">📊</span>
                            Productivity Rating
                        </div>
                        <div class="review-section-subtitle">
                            Rate your productivity from 1-10
                        </div>
                        <div class="rating-value" id="ratingValue">5</div>
                        <input type="range" class="rating-slider" id="productivityRating"
                               min="1" max="10" value="5">
                    </div>

                    <div class="review-section">
                        <div class="review-section-title">
                            <span class="review-section-icon">🎉</span>
                            What did you accomplish?
                        </div>
                        <div class="review-section-subtitle">
                            List your key achievements and wins
                        </div>
                        <textarea id="accomplishments" class="form-control" rows="5"
                                  placeholder="• Completed the user authentication feature&#10;• Fixed 15 bugs&#10;• Launched the new landing page"></textarea>
                    </div>

                    <div class="review-section">
                        <div class="review-section-title">
                            <span class="review-section-icon">⚠️</span>
                            What challenges did you face?
                        </div>
                        <div class="review-section-subtitle">
                            Reflect on obstacles and difficulties
                        </div>
                        <textarea id="challenges" class="form-control" rows="4"
                                  placeholder="• API integration took longer than expected&#10;• Communication delays with team"></textarea>
                    </div>

                    <div class="review-section">
                        <div class="review-section-title">
                            <span class="review-section-icon">💡</span>
                            What did you learn?
                        </div>
                        <div class="review-section-subtitle">
                            Key lessons and insights
                        </div>
                        <textarea id="lessonsLearned" class="form-control" rows="4"
                                  placeholder="• Learned a new testing framework&#10;• Improved time estimation skills"></textarea>
                    </div>

                    <div class="review-section">
                        <div class="review-section-title">
                            <span class="review-section-icon">🎯</span>
                            Goals for next <?php echo $reviewType === 'weekly' ? 'week' : 'month'; ?>
                        </div>
                        <div class="review-section-subtitle">
                            What do you want to achieve?
                        </div>
                        <textarea id="goalsNextPeriod" class="form-control" rows="4"
                                  placeholder="• Complete the payment integration&#10;• Improve code review response time&#10;• Learn Docker basics"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; margin-bottom: 48px;">
                        <button onclick="saveReview()" class="btn btn-success btn-lg" style="flex: 1;">
                            <i class="fas fa-save"></i> Save Review
                        </button>
                        <button onclick="loadHistory()" class="btn btn-secondary btn-lg">
                            <i class="fas fa-history"></i> View History
                        </button>
                    </div>

                    <!-- Review History -->
                    <div id="reviewHistory" style="display: none;">
                        <h2 style="margin-bottom: 24px;">📚 Review History</h2>
                        <div id="historyList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        const reviewType = '<?php echo $reviewType; ?>';
        let currentReview = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadReviewData();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Mood selector
            document.querySelectorAll('.mood-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // Rating slider
            document.getElementById('productivityRating').addEventListener('input', function() {
                document.getElementById('ratingValue').textContent = this.value;
            });
        }

        async function loadReviewData() {
            try {
                const response = await fetch(`../api/reviews.php?action=get&type=${reviewType}`);
                const data = await response.json();

                if (data.success) {
                    displayStats(data.stats);
                    displayReviewPeriod(data.review_period, reviewType);

                    if (data.review) {
                        loadExistingReview(data.review);
                    }
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function displayStats(stats) {
            const grid = document.getElementById('statsGrid');
            grid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${stats.tasks_completed}</div>
                    <div class="stat-label">Tasks Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.time_tracked_hours}h</div>
                    <div class="stat-label">Hours Tracked</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.urgent_completed}</div>
                    <div class="stat-label">Urgent Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.high_completed}</div>
                    <div class="stat-label">High Priority</div>
                </div>
            `;
        }

        function displayReviewPeriod(period, type) {
            document.getElementById('reviewPeriod').textContent =
                `Period: ${formatPeriod(period, type)}`;
        }

        function formatPeriod(period, type) {
            if (type === 'weekly') {
                const parts = period.split('-W');
                return `Week ${parts[1]}, ${parts[0]}`;
            } else {
                const date = new Date(period + '-01');
                return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            }
        }

        function loadExistingReview(review) {
            currentReview = review;

            document.getElementById('accomplishments').value = review.accomplishments || '';
            document.getElementById('challenges').value = review.challenges || '';
            document.getElementById('lessonsLearned').value = review.lessons_learned || '';
            document.getElementById('goalsNextPeriod').value = review.goals_next_period || '';

            // Set mood
            const moodBtn = document.querySelector(`.mood-btn[data-mood="${review.mood}"]`);
            if (moodBtn) {
                moodBtn.click();
            }

            // Set rating
            document.getElementById('productivityRating').value = review.productivity_rating;
            document.getElementById('ratingValue').textContent = review.productivity_rating;
        }

        async function saveReview() {
            const activeMoodBtn = document.querySelector('.mood-btn.active');
            const mood = activeMoodBtn ? activeMoodBtn.dataset.mood : 'good';

            const reviewData = {
                action: 'save',
                review_type: reviewType,
                accomplishments: document.getElementById('accomplishments').value,
                challenges: document.getElementById('challenges').value,
                lessons_learned: document.getElementById('lessonsLearned').value,
                goals_next_period: document.getElementById('goalsNextPeriod').value,
                mood: mood,
                productivity_rating: parseInt(document.getElementById('productivityRating').value)
            };

            try {
                const response = await fetch('../api/reviews.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(reviewData)
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ ' + data.message);
                } else {
                    alert('❌ ' + (data.message || 'Failed to save review'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Failed to save review');
            }
        }

        async function loadHistory() {
            try {
                const response = await fetch(`../api/reviews.php?action=list&type=${reviewType}&limit=20`);
                const data = await response.json();

                if (data.success) {
                    displayHistory(data.reviews);
                    document.getElementById('reviewHistory').style.display = 'block';
                    document.getElementById('reviewHistory').scrollIntoView({ behavior: 'smooth' });
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function displayHistory(reviews) {
            const list = document.getElementById('historyList');

            if (reviews.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 40px;">No review history yet</p>';
                return;
            }

            list.innerHTML = reviews.map(review => {
                const moodEmoji = {
                    'great': '🤩',
                    'good': '😊',
                    'okay': '😐',
                    'challenging': '😰',
                    'difficult': '😓'
                };

                return `
                    <div class="history-item" onclick='loadHistoryReview(${JSON.stringify(review)})'>
                        <div class="history-header">
                            <div class="history-period">${formatPeriod(review.review_period, review.review_type)}</div>
                            <div>
                                <span class="history-mood">${moodEmoji[review.mood]}</span>
                                <span style="margin-left: 12px; color: var(--primary); font-weight: 600;">
                                    ${review.productivity_rating}/10
                                </span>
                            </div>
                        </div>
                        <div class="history-preview">
                            ${review.accomplishments ? review.accomplishments.substring(0, 150) + '...' : 'No notes'}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function loadHistoryReview(review) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => {
                loadExistingReview(review);
            }, 500);
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
