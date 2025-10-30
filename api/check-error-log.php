<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Test writing to error log
error_log("TEST LOG ENTRY - Finding error log location");

$info = [
    'error_log_setting' => ini_get('error_log'),
    'log_errors_enabled' => ini_get('log_errors'),
    'display_errors' => ini_get('display_errors'),
    'possible_log_locations' => [
        'Current directory' => __DIR__ . '/error_log',
        'Parent directory' => dirname(__DIR__) . '/error_log',
        'Document root' => $_SERVER['DOCUMENT_ROOT'] . '/error_log',
        'Home directory' => '/home/' . get_current_user() . '/error_log',
    ],
    'existing_log_files' => []
];

// Check which log files actually exist
foreach ($info['possible_log_locations'] as $name => $path) {
    if (file_exists($path)) {
        $info['existing_log_files'][$name] = [
            'path' => $path,
            'size' => filesize($path),
            'last_modified' => date('Y-m-d H:i:s', filemtime($path)),
            'readable' => is_readable($path)
        ];
    }
}

// Check parent directories for error_log files
$searchDirs = [
    __DIR__,
    dirname(__DIR__),
    $_SERVER['DOCUMENT_ROOT'],
];

foreach ($searchDirs as $dir) {
    $files = @glob($dir . '/error_log*');
    if ($files) {
        foreach ($files as $file) {
            $info['found_log_files'][] = [
                'path' => $file,
                'size' => filesize($file),
                'last_modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
    }
}

echo json_encode($info, JSON_PRETTY_PRINT);
