<?php
/**
 * V3 Page Template - Use this as a base for converting pages
 *
 * USAGE:
 * 1. Copy this template
 * 2. Update the role check (admin/manager/employee)
 * 3. Add your PHP logic at the top
 * 4. Replace the content section with your page content
 * 5. Update the page title
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// CHANGE THIS: Update role check
if (!isLoggedIn() || !hasRole('admin')) { // Change 'admin' to 'manager' or 'employee'
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// YOUR PHP LOGIC HERE
// Add database queries, data processing, etc.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
</head>
<body>
    <div class="app-container">
        <!-- CHANGE THIS: Update sidebar include -->
        <?php include '../includes/v3-admin-sidebar.php'; ?>
        <!-- Options: v3-admin-sidebar.php, v3-manager-sidebar.php, v3-employee-sidebar.php -->

        <!-- Main Content -->
        <div class="main-content">
            <!-- V3 Header -->
            <?php include '../includes/v3-header.php'; ?>

            <!-- Page Content -->
            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;">Page Title Here</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        Page description goes here
                    </p>
                </div>

                <!-- YOUR CONTENT HERE -->
                <!-- Example: Dashboard Cards -->
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="card-value">123</div>
                            <div class="card-label">Stat Label</div>
                            <div class="card-trend up">12%</div>
                        </div>
                    </div>
                </div>

                <!-- Example: Data Table -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">Table Title</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Table description
                            </p>
                        </div>
                        <a href="#" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Action Button
                        </a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container" style="border: none; box-shadow: none;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="sortable">Column 1</th>
                                        <th class="sortable">Column 2</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Data</td>
                                        <td><span class="badge badge-success">Status</span></td>
                                        <td>
                                            <a href="#" class="btn btn-outline btn-sm btn-icon">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
    // YOUR JAVASCRIPT HERE
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded');
    });
    </script>
</body>
</html>
