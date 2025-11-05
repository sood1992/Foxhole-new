<?php
/**
 * SYNTO DESIGN AUTO-UPDATER
 * Automatically adds Synto CSS and JS includes to all Foxhole pages
 *
 * Usage: php update-synto-design.php
 */

echo "=== SYNTO DESIGN AUTO-UPDATER ===\n\n";

// Directories to process
$directories = [
    'admin' => '../',
    'manager' => '../',
    'employee' => '../'
];

// Files to skip (already updated or don't need updates)
$skipFiles = [
    'admin/index.php', // Already updated
];

// Counters
$totalFiles = 0;
$updatedFiles = 0;
$skippedFiles = 0;
$errors = 0;

foreach ($directories as $dir => $relativePath) {
    echo "Processing directory: $dir/\n";
    echo str_repeat("-", 50) . "\n";

    $files = glob(__DIR__ . "/$dir/*.php");

    foreach ($files as $file) {
        $totalFiles++;
        $relativFile = str_replace(__DIR__ . '/', '', $file);

        // Check if file should be skipped
        if (in_array($relativFile, $skipFiles)) {
            echo "  [SKIP] $relativFile (already updated)\n";
            $skippedFiles++;
            continue;
        }

        // Read file content
        $content = file_get_contents($file);

        if ($content === false) {
            echo "  [ERROR] Could not read $relativFile\n";
            $errors++;
            continue;
        }

        $modified = false;

        // Check if synto-design.css is already included
        if (strpos($content, 'synto-design.css') === false) {
            // Find vien-v3.css and add synto after it
            $vienPattern = '/(<link[^>]*vien-v3\.css[^>]*>)/';
            if (preg_match($vienPattern, $content)) {
                $syntoCSS = "\n\n    <!-- Synto Dashboard Template Design -->\n    <link rel=\"stylesheet\" href=\"{$relativePath}assets/css/synto-design.css\">";
                $content = preg_replace($vienPattern, '$1' . $syntoCSS, $content);
                $modified = true;
            }
        }

        // Check if synto-interactions.js is already included
        if (strpos($content, 'synto-interactions.js') === false) {
            // Find closing </body> tag and add script before it
            $bodyPattern = '/(<\/body>)/i';
            if (preg_match($bodyPattern, $content)) {
                $syntoJS = "\n    <!-- Synto Dashboard Interactions -->\n    <script src=\"{$relativePath}assets/js/synto-interactions.js\"></script>\n";
                $content = preg_replace($bodyPattern, $syntoJS . '$1', $content);
                $modified = true;
            }
        }

        // Update common FontAwesome icons to RemixIcon
        $iconReplacements = [
            // Dashboard/Home
            'fa-home' => 'ri-dashboard-line',
            'fa-tachometer-alt' => 'ri-speed-line',

            // Users/Team
            'fa-users' => 'ri-team-line',
            'fa-user' => 'ri-user-line',
            'fa-user-plus' => 'ri-user-add-line',
            'fa-user-tie' => 'ri-shield-user-line',
            'fa-user-circle' => 'ri-user-settings-line',

            // Projects/Folders
            'fa-folder' => 'ri-folder-line',
            'fa-folder-open' => 'ri-folder-open-line',
            'fa-folder-plus' => 'ri-folder-add-line',

            // Tasks
            'fa-tasks' => 'ri-task-line',
            'fa-list' => 'ri-list-check',
            'fa-list-check' => 'ri-list-check-2',
            'fa-check-circle' => 'ri-checkbox-circle-line',
            'fa-check' => 'ri-check-line',
            'fa-circle' => 'ri-checkbox-blank-circle-line',

            // Time/Calendar
            'fa-clock' => 'ri-time-line',
            'fa-calendar' => 'ri-calendar-line',
            'fa-calendar-alt' => 'ri-calendar-2-line',
            'fa-calendar-day' => 'ri-calendar-check-line',
            'fa-calendar-week' => 'ri-calendar-event-line',

            // Charts/Analytics
            'fa-chart-bar' => 'ri-bar-chart-box-line',
            'fa-chart-line' => 'ri-line-chart-line',
            'fa-chart-pie' => 'ri-pie-chart-line',

            // Actions
            'fa-plus' => 'ri-add-line',
            'fa-edit' => 'ri-edit-line',
            'fa-trash' => 'ri-delete-bin-line',
            'fa-eye' => 'ri-eye-line',
            'fa-download' => 'ri-download-line',
            'fa-upload' => 'ri-upload-line',
            'fa-search' => 'ri-search-line',
            'fa-filter' => 'ri-filter-line',

            // Messages/Chat
            'fa-envelope' => 'ri-mail-line',
            'fa-comments' => 'ri-chat-3-line',
            'fa-comment' => 'ri-message-3-line',
            'fa-bell' => 'ri-notification-3-line',

            // Settings
            'fa-cog' => 'ri-settings-3-line',
            'fa-wrench' => 'ri-tools-line',

            // Files
            'fa-file' => 'ri-file-line',
            'fa-file-import' => 'ri-file-upload-line',
            'fa-file-export' => 'ri-file-download-line',

            // Other
            'fa-spinner' => 'ri-loader-2-line',
            'fa-trophy' => 'ri-trophy-line',
            'fa-award' => 'ri-medal-line',
            'fa-star' => 'ri-star-line',
            'fa-crown' => 'ri-vip-crown-line',
            'fa-sign-out-alt' => 'ri-logout-box-line',
            'fa-ban' => 'ri-error-warning-line',
        ];

        foreach ($iconReplacements as $oldIcon => $newIcon) {
            if (strpos($content, $oldIcon) !== false) {
                $content = str_replace($oldIcon, $newIcon, $content);
                $modified = true;
            }
        }

        // Save file if modified
        if ($modified) {
            if (file_put_contents($file, $content) !== false) {
                echo "  [UPDATE] $relativFile\n";
                $updatedFiles++;
            } else {
                echo "  [ERROR] Could not write to $relativFile\n";
                $errors++;
            }
        } else {
            echo "  [OK] $relativFile (no changes needed)\n";
            $skippedFiles++;
        }
    }

    echo "\n";
}

// Summary
echo str_repeat("=", 50) . "\n";
echo "SUMMARY:\n";
echo "  Total files processed: $totalFiles\n";
echo "  Files updated: $updatedFiles\n";
echo "  Files skipped: $skippedFiles\n";
echo "  Errors: $errors\n";
echo str_repeat("=", 50) . "\n";

if ($updatedFiles > 0) {
    echo "\n✅ Successfully updated $updatedFiles files with Synto design!\n";
    echo "\nNext steps:\n";
    echo "  1. Test the updated pages\n";
    echo "  2. Commit the changes: git add . && git commit -m 'Apply Synto design to all pages'\n";
    echo "  3. Push to remote: git push\n";
} else {
    echo "\nℹ️  No files needed updating.\n";
}

echo "\n";
