<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

// Find error log
$possibleLogs = [
    $_SERVER['DOCUMENT_ROOT'] . '/error_log',
    dirname(__DIR__) . '/error_log',
    __DIR__ . '/error_log',
    '/home/' . get_current_user() . '/error_log',
    '/home/' . get_current_user() . '/public_html/error_log',
];

$logFile = null;
foreach ($possibleLogs as $path) {
    if (file_exists($path) && is_readable($path)) {
        $logFile = $path;
        break;
    }
}

// Get last N lines
$lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
$filter = $_GET['filter'] ?? '';

$logContent = '';
$logSize = 0;
$logModified = '';

if ($logFile) {
    $logSize = filesize($logFile);
    $logModified = date('Y-m-d H:i:s', filemtime($logFile));

    // Read last N lines
    $file = new SplFileObject($logFile);
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();

    $startLine = max(0, $totalLines - $lines);
    $file->seek($startLine);

    $logLines = [];
    while (!$file->eof()) {
        $line = $file->current();
        if (!empty($filter)) {
            if (stripos($line, $filter) !== false) {
                $logLines[] = $line;
            }
        } else {
            $logLines[] = $line;
        }
        $file->next();
    }

    $logContent = implode('', $logLines);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Logs - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <style>
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
            max-height: 70vh;
            overflow-y: auto;
        }
        .log-viewer .error { color: #f48771; }
        .log-viewer .warning { color: #dcdcaa; }
        .log-viewer .info { color: #4fc1ff; }
        .log-viewer .success { color: #73c991; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;"><i class="fas fa-file-alt"></i> Error Logs</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        View PHP error logs and debugging information
                    </p>
                </div>

                <?php if (!$logFile): ?>
                    <div class="card">
                        <div class="card-body">
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--warning); margin-bottom: 15px;"></i>
                                <h3>Error Log Not Found</h3>
                                <p style="color: var(--text-secondary);">Could not locate error_log file in common locations.</p>
                                <p style="margin-top: 20px;">
                                    <a href="/api/check-error-log.php" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-search"></i> Check Log Location
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Controls -->
                    <div class="card" style="margin-bottom: 20px;">
                        <div class="card-body">
                            <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                                        Filter Logs
                                    </label>
                                    <input type="text" name="filter" value="<?php echo htmlspecialchars($filter); ?>"
                                           placeholder="Search logs..." class="form-control">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                                        Show Last
                                    </label>
                                    <select name="lines" class="form-control">
                                        <option value="50" <?php echo $lines == 50 ? 'selected' : ''; ?>>50 lines</option>
                                        <option value="100" <?php echo $lines == 100 ? 'selected' : ''; ?>>100 lines</option>
                                        <option value="200" <?php echo $lines == 200 ? 'selected' : ''; ?>>200 lines</option>
                                        <option value="500" <?php echo $lines == 500 ? 'selected' : ''; ?>>500 lines</option>
                                        <option value="1000" <?php echo $lines == 1000 ? 'selected' : ''; ?>>1000 lines</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                                <a href="view-logs.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i> Clear Filter
                                </a>
                            </form>
                        </div>
                    </div>

                    <!-- Log Info -->
                    <div class="card" style="margin-bottom: 20px;">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                <div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">Log File</div>
                                    <div style="font-weight: 600; font-family: monospace; font-size: 13px;"><?php echo basename($logFile); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">Full Path</div>
                                    <div style="font-weight: 600; font-family: monospace; font-size: 11px;"><?php echo $logFile; ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">File Size</div>
                                    <div style="font-weight: 600;"><?php echo number_format($logSize / 1024, 2); ?> KB</div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">Last Modified</div>
                                    <div style="font-weight: 600;"><?php echo $logModified; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Log Content -->
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0;">Log Output (Last <?php echo $lines; ?> lines)</h3>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <?php if (empty($logContent)): ?>
                                <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                                    <?php if ($filter): ?>
                                        No log entries match your filter.
                                    <?php else: ?>
                                        Log file is empty.
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="log-viewer">
                                    <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo htmlspecialchars($logContent); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
