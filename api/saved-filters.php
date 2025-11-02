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

// GET requests - retrieve filters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT id, filter_name, filter_config, is_favorite, created_at
            FROM saved_filters
            WHERE user_id = ?
            ORDER BY is_favorite DESC, filter_name ASC
        ");
        $stmt->execute([$currentUser['id']]);
        $filters = $stmt->fetchAll();

        // Decode JSON config for each filter
        foreach ($filters as &$filter) {
            $filter['filter_config'] = json_decode($filter['filter_config'], true);
        }

        echo json_encode([
            'success' => true,
            'filters' => $filters
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST requests - create/update/delete filters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $filterName = trim($input['filter_name'] ?? '');
                $filterConfig = $input['filter_config'] ?? [];
                $isFavorite = intval($input['is_favorite'] ?? 0);

                if (empty($filterName)) {
                    throw new Exception('Filter name is required');
                }

                if (empty($filterConfig)) {
                    throw new Exception('Filter configuration is required');
                }

                $stmt = $db->prepare("
                    INSERT INTO saved_filters (user_id, filter_name, filter_config, is_favorite)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $currentUser['id'],
                    $filterName,
                    json_encode($filterConfig),
                    $isFavorite
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Filter saved successfully',
                    'filter_id' => $db->lastInsertId()
                ]);
                break;

            case 'update':
                $filterId = intval($input['filter_id'] ?? 0);
                $filterName = trim($input['filter_name'] ?? '');
                $filterConfig = $input['filter_config'] ?? [];
                $isFavorite = intval($input['is_favorite'] ?? 0);

                if (!$filterId) {
                    throw new Exception('Filter ID is required');
                }

                if (empty($filterName)) {
                    throw new Exception('Filter name is required');
                }

                // Verify ownership
                $check = $db->prepare("SELECT id FROM saved_filters WHERE id = ? AND user_id = ?");
                $check->execute([$filterId, $currentUser['id']]);
                if (!$check->fetch()) {
                    throw new Exception('Filter not found or access denied');
                }

                $stmt = $db->prepare("
                    UPDATE saved_filters
                    SET filter_name = ?, filter_config = ?, is_favorite = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $filterName,
                    json_encode($filterConfig),
                    $isFavorite,
                    $filterId,
                    $currentUser['id']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Filter updated successfully'
                ]);
                break;

            case 'delete':
                $filterId = intval($input['filter_id'] ?? 0);

                if (!$filterId) {
                    throw new Exception('Filter ID is required');
                }

                // Verify ownership
                $check = $db->prepare("SELECT id FROM saved_filters WHERE id = ? AND user_id = ?");
                $check->execute([$filterId, $currentUser['id']]);
                if (!$check->fetch()) {
                    throw new Exception('Filter not found or access denied');
                }

                $stmt = $db->prepare("DELETE FROM saved_filters WHERE id = ? AND user_id = ?");
                $stmt->execute([$filterId, $currentUser['id']]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Filter deleted successfully'
                ]);
                break;

            case 'toggle_favorite':
                $filterId = intval($input['filter_id'] ?? 0);

                if (!$filterId) {
                    throw new Exception('Filter ID is required');
                }

                // Verify ownership and toggle
                $stmt = $db->prepare("
                    UPDATE saved_filters
                    SET is_favorite = NOT is_favorite, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$filterId, $currentUser['id']]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Filter not found or access denied');
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Filter favorite status updated'
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
