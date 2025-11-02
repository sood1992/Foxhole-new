<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// GET requests - retrieve reviews
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    try {
        if ($action === 'list') {
            $reviewType = $_GET['type'] ?? 'all';
            $limit = intval($_GET['limit'] ?? 10);

            $query = "
                SELECT *
                FROM reviews
                WHERE user_id = ?
            ";

            $params = [$currentUser['id']];

            if ($reviewType !== 'all') {
                $query .= " AND review_type = ?";
                $params[] = $reviewType;
            }

            $query .= " ORDER BY review_period DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $reviews = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'reviews' => $reviews
            ]);

        } elseif ($action === 'get') {
            $reviewType = $_GET['type'] ?? 'weekly';
            $reviewPeriod = $_GET['period'] ?? null;

            if (!$reviewPeriod) {
                // Get current period
                if ($reviewType === 'weekly') {
                    $reviewPeriod = date('Y') . '-W' . date('W');
                } else {
                    $reviewPeriod = date('Y-m');
                }
            }

            // Get existing review
            $stmt = $db->prepare("
                SELECT *
                FROM reviews
                WHERE user_id = ? AND review_type = ? AND review_period = ?
            ");
            $stmt->execute([$currentUser['id'], $reviewType, $reviewPeriod]);
            $review = $stmt->fetch();

            // Get period stats
            $stats = getPeriodStats($db, $currentUser['id'], $reviewType, $reviewPeriod);

            echo json_encode([
                'success' => true,
                'review' => $review,
                'stats' => $stats,
                'review_period' => $reviewPeriod,
                'review_type' => $reviewType
            ]);

        } elseif ($action === 'stats') {
            $reviewType = $_GET['type'] ?? 'weekly';
            $reviewPeriod = $_GET['period'] ?? null;

            if (!$reviewPeriod) {
                if ($reviewType === 'weekly') {
                    $reviewPeriod = date('Y') . '-W' . date('W');
                } else {
                    $reviewPeriod = date('Y-m');
                }
            }

            $stats = getPeriodStats($db, $currentUser['id'], $reviewType, $reviewPeriod);

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST requests - create/update reviews
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'save':
                $reviewType = $input['review_type'] ?? 'weekly';
                $reviewPeriod = $input['review_period'] ?? null;
                $accomplishments = trim($input['accomplishments'] ?? '');
                $challenges = trim($input['challenges'] ?? '');
                $lessonsLearned = trim($input['lessons_learned'] ?? '');
                $goalsNextPeriod = trim($input['goals_next_period'] ?? '');
                $mood = $input['mood'] ?? 'good';
                $productivityRating = intval($input['productivity_rating'] ?? 5);

                if (!$reviewPeriod) {
                    if ($reviewType === 'weekly') {
                        $reviewPeriod = date('Y') . '-W' . date('W');
                    } else {
                        $reviewPeriod = date('Y-m');
                    }
                }

                // Check if review exists
                $check = $db->prepare("
                    SELECT id FROM reviews
                    WHERE user_id = ? AND review_type = ? AND review_period = ?
                ");
                $check->execute([$currentUser['id'], $reviewType, $reviewPeriod]);
                $existing = $check->fetch();

                if ($existing) {
                    // Update existing review
                    $stmt = $db->prepare("
                        UPDATE reviews SET
                            accomplishments = ?,
                            challenges = ?,
                            lessons_learned = ?,
                            goals_next_period = ?,
                            mood = ?,
                            productivity_rating = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $accomplishments,
                        $challenges,
                        $lessonsLearned,
                        $goalsNextPeriod,
                        $mood,
                        $productivityRating,
                        $existing['id']
                    ]);
                    $reviewId = $existing['id'];
                    $message = 'Review updated successfully';
                } else {
                    // Create new review
                    $stmt = $db->prepare("
                        INSERT INTO reviews
                        (user_id, review_type, review_period, accomplishments, challenges,
                         lessons_learned, goals_next_period, mood, productivity_rating)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $currentUser['id'],
                        $reviewType,
                        $reviewPeriod,
                        $accomplishments,
                        $challenges,
                        $lessonsLearned,
                        $goalsNextPeriod,
                        $mood,
                        $productivityRating
                    ]);
                    $reviewId = $db->lastInsertId();
                    $message = 'Review created successfully';
                }

                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'review_id' => $reviewId
                ]);
                break;

            case 'delete':
                $reviewId = intval($input['review_id'] ?? 0);

                if (!$reviewId) {
                    throw new Exception('Review ID is required');
                }

                // Verify ownership
                $check = $db->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
                $check->execute([$reviewId, $currentUser['id']]);
                if (!$check->fetch()) {
                    throw new Exception('Review not found or access denied');
                }

                $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
                $stmt->execute([$reviewId]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Review deleted successfully'
                ]);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Helper function to get period stats
function getPeriodStats($db, $userId, $reviewType, $reviewPeriod) {
    // Calculate date range
    if ($reviewType === 'weekly') {
        // Parse YYYY-WXX format
        $parts = explode('-W', $reviewPeriod);
        $year = intval($parts[0]);
        $week = intval($parts[1]);

        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $startDate = $dto->format('Y-m-d');
        $dto->modify('+6 days');
        $endDate = $dto->format('Y-m-d');
    } else {
        // Parse YYYY-MM format
        $startDate = $reviewPeriod . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
    }

    // Tasks completed
    $tasksStmt = $db->prepare("
        SELECT
            COUNT(*) as total_completed,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_completed,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_completed,
            SUM(actual_hours) as total_hours
        FROM tasks
        WHERE assigned_to = ?
            AND status = 'completed'
            AND DATE(completed_date) BETWEEN ? AND ?
    ");
    $tasksStmt->execute([$userId, $startDate, $endDate]);
    $tasks = $tasksStmt->fetch();

    // Time tracked
    $timeStmt = $db->prepare("
        SELECT SUM(duration_minutes) as total_minutes
        FROM time_logs
        WHERE user_id = ?
            AND DATE(start_time) BETWEEN ? AND ?
    ");
    $timeStmt->execute([$userId, $startDate, $endDate]);
    $time = $timeStmt->fetch();

    // Top projects
    $projectsStmt = $db->prepare("
        SELECT
            p.project_name,
            COUNT(DISTINCT t.id) as tasks_completed,
            SUM(t.actual_hours) as hours_spent
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.assigned_to = ?
            AND t.status = 'completed'
            AND DATE(t.completed_date) BETWEEN ? AND ?
        GROUP BY p.id, p.project_name
        ORDER BY tasks_completed DESC
        LIMIT 5
    ");
    $projectsStmt->execute([$userId, $startDate, $endDate]);
    $projects = $projectsStmt->fetchAll();

    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'tasks_completed' => $tasks['total_completed'] ?? 0,
        'urgent_completed' => $tasks['urgent_completed'] ?? 0,
        'high_completed' => $tasks['high_completed'] ?? 0,
        'total_hours' => round($tasks['total_hours'] ?? 0, 1),
        'time_tracked_minutes' => $time['total_minutes'] ?? 0,
        'time_tracked_hours' => round(($time['total_minutes'] ?? 0) / 60, 1),
        'top_projects' => $projects
    ];
}
