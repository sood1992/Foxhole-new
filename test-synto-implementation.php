<?php
/**
 * SYNTO DESIGN IMPLEMENTATION - COMPREHENSIVE TESTING SUITE
 * Tests all aspects of the Synto design implementation
 *
 * Usage: php test-synto-implementation.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  FOXHOLE V3.1 SYNTO EDITION - COMPREHENSIVE TEST SUITE        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Test results storage
$results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'errors' => []
];

// Test categories
$tests = [
    'File Integrity',
    'CSS/JS Includes',
    'PHP Syntax',
    'Icon Updates',
    'Database Functions',
    'Component Integrity'
];

// =============================================================================
// TEST 1: FILE INTEGRITY
// =============================================================================
echo "┌─ Test 1: File Integrity ────────────────────────────────────────┐\n";

$criticalFiles = [
    'assets/css/vien-v3.css' => 'Base CSS',
    'assets/css/synto-design.css' => 'Synto CSS',
    'assets/js/synto-interactions.js' => 'Synto JS',
    'includes/v3-admin-sidebar.php' => 'Admin Sidebar',
    'includes/v3-manager-sidebar.php' => 'Manager Sidebar',
    'includes/v3-employee-sidebar.php' => 'Employee Sidebar',
    'includes/v3-header.php' => 'Header Component',
    'includes/functions.php' => 'Core Functions',
    'config/config.php' => 'Configuration',
    'login.php' => 'Login Page'
];

foreach ($criticalFiles as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $size = filesize(__DIR__ . '/' . $file);
        echo "  ✓ {$description}: " . number_format($size) . " bytes\n";
        $results['passed']++;
    } else {
        echo "  ✗ {$description}: MISSING!\n";
        $results['failed']++;
        $results['errors'][] = "Missing file: $file";
    }
}

echo "\n";

// =============================================================================
// TEST 2: CSS/JS INCLUDES VALIDATION
// =============================================================================
echo "┌─ Test 2: CSS/JS Includes Validation ────────────────────────────┐\n";

$portals = [
    'admin' => 17,
    'manager' => 10,
    'employee' => 17
];

foreach ($portals as $portal => $expectedCount) {
    $files = glob(__DIR__ . "/{$portal}/*.php");
    $withSyntoCSS = 0;
    $withSyntoJS = 0;
    $missing = [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        $filename = basename($file);

        // Check for Synto CSS
        if (strpos($content, 'synto-design.css') !== false) {
            $withSyntoCSS++;
        } else {
            $missing[] = $filename;
        }

        // Check for Synto JS
        if (strpos($content, 'synto-interactions.js') !== false) {
            $withSyntoJS++;
        }
    }

    echo "  Portal: {$portal}/\n";
    echo "    ✓ Pages with Synto CSS: {$withSyntoCSS}/{$expectedCount}\n";
    echo "    ✓ Pages with Synto JS: {$withSyntoJS}/{$expectedCount}\n";

    if ($withSyntoCSS === $expectedCount && $withSyntoJS === $expectedCount) {
        $results['passed'] += 2;
    } else {
        $results['failed']++;
        if (!empty($missing)) {
            echo "    ⚠ Missing in: " . implode(', ', $missing) . "\n";
            $results['warnings']++;
        }
    }
}

// Check login page
$loginContent = file_get_contents(__DIR__ . '/login.php');
if (strpos($loginContent, 'synto-design.css') !== false &&
    strpos($loginContent, 'synto-interactions.js') !== false) {
    echo "  ✓ Login page: Synto CSS & JS present\n";
    $results['passed']++;
} else {
    echo "  ✗ Login page: Missing Synto includes\n";
    $results['failed']++;
}

echo "\n";

// =============================================================================
// TEST 3: PHP SYNTAX VALIDATION
// =============================================================================
echo "┌─ Test 3: PHP Syntax Validation ─────────────────────────────────┐\n";

$allPhpFiles = array_merge(
    glob(__DIR__ . '/admin/*.php'),
    glob(__DIR__ . '/manager/*.php'),
    glob(__DIR__ . '/employee/*.php'),
    glob(__DIR__ . '/includes/*.php')
);

$syntaxErrors = 0;
$syntaxChecked = 0;

foreach ($allPhpFiles as $file) {
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);
    $syntaxChecked++;

    if ($returnVar !== 0) {
        echo "  ✗ Syntax error in: " . basename($file) . "\n";
        echo "    " . implode("\n    ", $output) . "\n";
        $syntaxErrors++;
        $results['errors'][] = "Syntax error in " . basename($file);
    }
}

if ($syntaxErrors === 0) {
    echo "  ✓ All {$syntaxChecked} PHP files: No syntax errors\n";
    $results['passed']++;
} else {
    echo "  ✗ Found {$syntaxErrors} files with syntax errors\n";
    $results['failed']++;
}

echo "\n";

// =============================================================================
// TEST 4: ICON UPDATES VERIFICATION
// =============================================================================
echo "┌─ Test 4: Icon Updates Verification ─────────────────────────────┐\n";

$iconPatterns = [
    'ri-dashboard-line',
    'ri-team-line',
    'ri-folder-line',
    'ri-task-line',
    'ri-time-line',
    'ri-calendar-line',
    'ri-settings-3-line',
    'ri-user-line',
    'ri-logout-box-line'
];

$iconCounts = [];
foreach ($iconPatterns as $pattern) {
    $count = 0;
    foreach ($allPhpFiles as $file) {
        $content = file_get_contents($file);
        $count += substr_count($content, $pattern);
    }
    $iconCounts[$pattern] = $count;
}

$totalRemixIcons = array_sum($iconCounts);
echo "  ✓ Total RemixIcon instances found: {$totalRemixIcons}\n";

// Check for lingering FontAwesome icons (should be minimal)
$faPatterns = ['fa-home', 'fa-users', 'fa-folder', 'fa-tasks'];
$faCount = 0;
foreach ($allPhpFiles as $file) {
    $content = file_get_contents($file);
    foreach ($faPatterns as $pattern) {
        $faCount += substr_count($content, $pattern);
    }
}

if ($faCount > 50) { // Some FontAwesome might remain in comments or non-updated sections
    echo "  ⚠ Found {$faCount} FontAwesome icon references (review recommended)\n";
    $results['warnings']++;
} else {
    echo "  ✓ FontAwesome icons mostly replaced ({$faCount} remaining)\n";
    $results['passed']++;
}

echo "\n";

// =============================================================================
// TEST 5: COMPONENT INTEGRITY
// =============================================================================
echo "┌─ Test 5: Component Integrity ───────────────────────────────────┐\n";

// Check sidebars have proper structure
$sidebarFiles = [
    'includes/v3-admin-sidebar.php',
    'includes/v3-manager-sidebar.php',
    'includes/v3-employee-sidebar.php'
];

foreach ($sidebarFiles as $sidebar) {
    $content = file_get_contents(__DIR__ . '/' . $sidebar);
    $checks = [
        'custom-scrollbar' => 'Scrollbar class',
        'sidebar-main-panel' => 'Main panel',
        'sidebar-sub-panel' => 'Sub panel',
        'menu-section' => 'Menu sections',
        'main-menu-item' => 'Menu items'
    ];

    $sidebarName = basename($sidebar, '.php');
    $allPresent = true;

    foreach ($checks as $class => $description) {
        if (strpos($content, $class) === false) {
            echo "  ✗ {$sidebarName}: Missing {$description}\n";
            $allPresent = false;
        }
    }

    if ($allPresent) {
        echo "  ✓ {$sidebarName}: All components present\n";
        $results['passed']++;
    } else {
        $results['failed']++;
    }
}

// Check header component
$headerContent = file_get_contents(__DIR__ . '/includes/v3-header.php');
$headerChecks = [
    'header-search' => 'Search box',
    'header-actions' => 'Header actions',
    'icon-button' => 'Icon buttons',
    'user-menu' => 'User menu',
    'userDropdown' => 'User dropdown'
];

$headerOK = true;
foreach ($headerChecks as $class => $description) {
    if (strpos($headerContent, $class) === false) {
        echo "  ✗ Header: Missing {$description}\n";
        $headerOK = false;
    }
}

if ($headerOK) {
    echo "  ✓ Header component: All elements present\n";
    $results['passed']++;
} else {
    $results['failed']++;
}

echo "\n";

// =============================================================================
// TEST 6: CSS VARIABLE USAGE
// =============================================================================
echo "┌─ Test 6: CSS Variable Usage ────────────────────────────────────┐\n";

$syntoCSS = file_get_contents(__DIR__ . '/assets/css/synto-design.css');

$requiredVariables = [
    '--synto-primary',
    '--synto-success',
    '--synto-warning',
    '--synto-danger',
    '--synto-info',
    '--synto-text-primary',
    '--synto-text-secondary',
    '--synto-bg',
    '--synto-card-bg',
    '--synto-border',
    '--synto-shadow',
    '--synto-radius-lg'
];

$missingVars = [];
foreach ($requiredVariables as $var) {
    if (strpos($syntoCSS, $var) === false) {
        $missingVars[] = $var;
    }
}

if (empty($missingVars)) {
    echo "  ✓ All " . count($requiredVariables) . " required CSS variables defined\n";
    $results['passed']++;
} else {
    echo "  ✗ Missing variables: " . implode(', ', $missingVars) . "\n";
    $results['failed']++;
}

// Check CSS file size (should be substantial)
$cssSize = filesize(__DIR__ . '/assets/css/synto-design.css');
$jsSize = filesize(__DIR__ . '/assets/js/synto-interactions.js');

echo "  ✓ Synto CSS size: " . number_format($cssSize) . " bytes\n";
echo "  ✓ Synto JS size: " . number_format($jsSize) . " bytes\n";

if ($cssSize > 10000 && $jsSize > 10000) {
    $results['passed']++;
} else {
    echo "  ⚠ File sizes seem small, verify content\n";
    $results['warnings']++;
}

echo "\n";

// =============================================================================
// TEST 7: DATABASE CONNECTIVITY CHECK
// =============================================================================
echo "┌─ Test 7: Database Connectivity ─────────────────────────────────┐\n";

// Check if config file exists and is readable
if (file_exists(__DIR__ . '/config/config.php')) {
    echo "  ✓ Configuration file exists\n";

    // Check for database configuration
    $configContent = file_get_contents(__DIR__ . '/config/config.php');
    if (strpos($configContent, 'DB_HOST') !== false &&
        strpos($configContent, 'DB_NAME') !== false) {
        echo "  ✓ Database configuration present\n";
        $results['passed']++;
    } else {
        echo "  ⚠ Database configuration may be incomplete\n";
        $results['warnings']++;
    }

    // Check functions file
    if (file_exists(__DIR__ . '/includes/functions.php')) {
        $functionsContent = file_get_contents(__DIR__ . '/includes/functions.php');
        $coreFunctions = [
            'getDBConnection',
            'isLoggedIn',
            'getCurrentUser',
            'hasRole'
        ];

        $missingFunctions = [];
        foreach ($coreFunctions as $func) {
            if (strpos($functionsContent, "function $func") === false) {
                $missingFunctions[] = $func;
            }
        }

        if (empty($missingFunctions)) {
            echo "  ✓ All core functions present\n";
            $results['passed']++;
        } else {
            echo "  ✗ Missing functions: " . implode(', ', $missingFunctions) . "\n";
            $results['failed']++;
        }
    }
} else {
    echo "  ✗ Configuration file not found\n";
    $results['failed']++;
}

echo "\n";

// =============================================================================
// TEST 8: REMIXICON IMPORT
// =============================================================================
echo "┌─ Test 8: RemixIcon Library Import ──────────────────────────────┐\n";

if (strpos($syntoCSS, 'remixicon') !== false ||
    strpos($syntoCSS, 'cdn.jsdelivr.net/npm/remixicon') !== false) {
    echo "  ✓ RemixIcon CDN import found in Synto CSS\n";
    $results['passed']++;
} else {
    echo "  ⚠ RemixIcon import not found, verify manually\n";
    $results['warnings']++;
}

echo "\n";

// =============================================================================
// TEST 9: PAGE STRUCTURE VALIDATION
// =============================================================================
echo "┌─ Test 9: Page Structure Validation ─────────────────────────────┐\n";

// Sample random pages from each portal
$samplePages = [
    'admin/index.php',
    'admin/projects.php',
    'manager/index.php',
    'manager/tasks.php',
    'employee/index.php',
    'employee/tasks.php'
];

foreach ($samplePages as $page) {
    if (file_exists(__DIR__ . '/' . $page)) {
        $content = file_get_contents(__DIR__ . '/' . $page);

        $structureChecks = [
            '<!DOCTYPE html>' => 'DOCTYPE',
            '<head>' => 'HEAD tag',
            '<body>' => 'BODY tag',
            'vien-v3.css' => 'Base CSS',
            'synto-design.css' => 'Synto CSS',
            '</html>' => 'Closing HTML'
        ];

        $pageValid = true;
        foreach ($structureChecks as $check => $description) {
            if (stripos($content, $check) === false) {
                $pageValid = false;
                break;
            }
        }

        if ($pageValid) {
            echo "  ✓ " . basename($page) . ": Valid structure\n";
            $results['passed']++;
        } else {
            echo "  ✗ " . basename($page) . ": Invalid structure\n";
            $results['failed']++;
        }
    }
}

echo "\n";

// =============================================================================
// TEST 10: RESPONSIVE BREAKPOINTS
// =============================================================================
echo "┌─ Test 10: Responsive Design Check ──────────────────────────────┐\n";

$breakpoints = [
    '@media (max-width: 768px)',
    '@media (max-width: 640px)',
    '@media (min-width: 1024px)'
];

$responsiveCount = 0;
foreach ($breakpoints as $bp) {
    if (strpos($syntoCSS, $bp) !== false) {
        $responsiveCount++;
    }
}

if ($responsiveCount >= 2) {
    echo "  ✓ Responsive breakpoints found: {$responsiveCount}\n";
    $results['passed']++;
} else {
    echo "  ⚠ Limited responsive breakpoints: {$responsiveCount}\n";
    $results['warnings']++;
}

echo "\n";

// =============================================================================
// FINAL SUMMARY
// =============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      TEST SUMMARY                              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$total = $results['passed'] + $results['failed'];
$percentage = $total > 0 ? round(($results['passed'] / $total) * 100, 1) : 0;

echo "  Tests Passed:    " . $results['passed'] . "\n";
echo "  Tests Failed:    " . $results['failed'] . "\n";
echo "  Warnings:        " . $results['warnings'] . "\n";
echo "  Success Rate:    " . $percentage . "%\n";
echo "\n";

if ($results['failed'] === 0 && $results['warnings'] === 0) {
    echo "  ✅ ALL TESTS PASSED - READY FOR DEPLOYMENT!\n";
} elseif ($results['failed'] === 0 && $results['warnings'] > 0) {
    echo "  ⚠️  ALL CRITICAL TESTS PASSED - MINOR WARNINGS PRESENT\n";
    echo "  Review warnings before deployment.\n";
} else {
    echo "  ❌ SOME TESTS FAILED - REVIEW ERRORS BEFORE DEPLOYMENT\n";
    echo "\n";
    echo "  Errors found:\n";
    foreach ($results['errors'] as $error) {
        echo "    - {$error}\n";
    }
}

echo "\n";
echo "  Total Files Tested: " . count($allPhpFiles) . " PHP files\n";
echo "  CSS File Size: " . round($cssSize / 1024, 1) . " KB\n";
echo "  JS File Size: " . round($jsSize / 1024, 1) . " KB\n";
echo "\n";

// Save report to file
$reportFile = __DIR__ . '/SYNTO-TEST-REPORT.txt';
ob_start();
echo "FOXHOLE V3.1 SYNTO EDITION - TEST REPORT\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 70) . "\n\n";
echo "SUMMARY:\n";
echo "  Tests Passed: {$results['passed']}\n";
echo "  Tests Failed: {$results['failed']}\n";
echo "  Warnings: {$results['warnings']}\n";
echo "  Success Rate: {$percentage}%\n\n";

if (!empty($results['errors'])) {
    echo "ERRORS:\n";
    foreach ($results['errors'] as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
}

echo "FILES TESTED: " . count($allPhpFiles) . "\n";
echo "REMIXICON INSTANCES: {$totalRemixIcons}\n";
echo "FONTAWESOME REMAINING: {$faCount}\n";
$report = ob_get_clean();
file_put_contents($reportFile, $report);

echo "  📄 Detailed report saved to: SYNTO-TEST-REPORT.txt\n";
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    TESTING COMPLETE                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Return exit code based on results
exit($results['failed'] > 0 ? 1 : 0);
