# Master Dashboard - New Sections Implementation Guide

All data queries have been added to `admin/master-dashboard.php`.
Now you need to add the HTML sections below to display the data.

---

## 📍 **WHERE TO ADD** - Insert after line 840 (after the existing 6 metric cards)

---

## 1️⃣ FINANCIAL OVERVIEW

Insert after existing metrics row (line 840):

```php
<!-- Financial Overview -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-dollar-sign"></i> Financial Overview</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div class="card-value">$<?php echo number_format($financial['total_revenue'], 0); ?></div>
                            <div class="card-label">Total Revenue</div>
                            <div class="card-trend">Active Projects</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon danger">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="card-value">$<?php echo number_format($financial['total_spent'], 0); ?></div>
                            <div class="card-label">Total Spent</div>
                            <div class="card-trend">Labor + Expenses</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon <?php echo $financial['profit_margin'] > 20 ? 'success' : ($financial['profit_margin'] > 10 ? 'warning' : 'danger'); ?>">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="card-value"><?php echo $financial['profit_margin']; ?>%</div>
                            <div class="card-label">Profit Margin</div>
                            <div class="card-trend">Overall</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon <?php echo $financial['over_budget_count'] > 0 ? 'danger' : 'success'; ?>">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="card-value"><?php echo $financial['over_budget_count']; ?></div>
                            <div class="card-label">Over Budget</div>
                            <div class="card-trend">Projects</div>
                        </div>
                    </div>
                </div>

                <?php if ($financial['top_project']): ?>
                <div style="margin-top: 20px; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 5px;">
                        <i class="fas fa-trophy"></i> Most Profitable This Month
                    </div>
                    <div style="font-size: 16px; font-weight: 600; color: var(--success);">
                        <?php echo e($financial['top_project']['project_name']); ?> -
                        $<?php echo number_format($financial['top_project']['profit'], 0); ?> profit
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
```

---

## 2️⃣ CLIENT HAPPINESS METRICS

```php
<!-- Client Happiness -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-smile"></i> Client Happiness Metrics</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon <?php echo $clientMetrics['approval_rate'] >= 80 ? 'success' : 'warning'; ?>">
                                <i class="fas fa-thumbs-up"></i>
                            </div>
                            <div class="card-value"><?php echo $clientMetrics['approval_rate']; ?>%</div>
                            <div class="card-label">Approval Rate</div>
                            <div class="card-trend">Last 30 Days</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas fa-redo"></i>
                            </div>
                            <div class="card-value"><?php echo $clientMetrics['avg_revisions']; ?></div>
                            <div class="card-label">Avg Revisions</div>
                            <div class="card-trend">Per Project</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="card-value"><?php echo $clientMetrics['pending_feedback']; ?></div>
                            <div class="card-label">Pending Feedback</div>
                            <div class="card-trend">Awaiting Response</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="card-value"><?php echo $clientMetrics['perfect_projects']; ?></div>
                            <div class="card-label">Perfect Deliveries</div>
                            <div class="card-trend">0 Revisions</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 3️⃣ PROJECT TYPE BREAKDOWN

```php
<!-- Project Types -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Project Type Breakdown</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($projectTypes)): ?>
                    <?php
                    $icons = [
                        'Video Editing' => ['icon' => 'fa-film', 'color' => '#FF6B6B'],
                        'Photography' => ['icon' => 'fa-camera', 'color' => '#4ECDC4'],
                        'Videography' => ['icon' => 'fa-video', 'color' => '#FFE66D'],
                        'Creative Strategy' => ['icon' => 'fa-lightbulb', 'color' => '#95E1D3'],
                        'Other' => ['icon' => 'fa-folder', 'color' => '#CCCCCC']
                    ];
                    ?>
                    <?php foreach ($projectTypes as $type): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; border-radius: 8px; background: <?php echo $icons[$type['project_type']]['color'] ?? '#CCCCCC'; ?>20; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas <?php echo $icons[$type['project_type']]['icon'] ?? 'fa-folder'; ?>" style="color: <?php echo $icons[$type['project_type']]['color'] ?? '#CCCCCC'; ?>; font-size: 18px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--heading-color);"><?php echo $type['project_type']; ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">Active Projects</div>
                                </div>
                            </div>
                            <div style="font-size: 24px; font-weight: 700; color: <?php echo $icons[$type['project_type']]['color'] ?? '#CCCCCC'; ?>;">
                                <?php echo $type['count']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 20px;">No active projects</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Team Capacity -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users-cog"></i> Team Capacity</h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-weight: 600;">Team Utilization</span>
                        <span style="font-weight: 700; color: var(--primary);"><?php echo $teamCapacity['utilization']; ?>%</span>
                    </div>
                    <div class="progress-bar-container" style="height: 12px;">
                        <div class="progress-bar <?php echo $teamCapacity['utilization'] > 90 ? 'danger' : ($teamCapacity['utilization'] > 75 ? 'warning' : ''); ?>"
                             style="width: <?php echo min($teamCapacity['utilization'], 100); ?>%;"></div>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                        Average <?php echo $teamCapacity['avg_hours']; ?>h per person this week (40h = 100%)
                    </div>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div style="text-align: center; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: 700; color: var(--success);"><?php echo $teamCapacity['available']; ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">Available</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: 700; color: var(--primary);"><?php echo $teamCapacity['total_team']; ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">Total Team</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: 700; color: var(--danger);"><?php echo $teamCapacity['overloaded']; ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">Overloaded</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($overloadedTeam)): ?>
                    <div style="margin-top: 15px; padding: 12px; background: var(--danger)10; border-left: 3px solid var(--danger); border-radius: 4px;">
                        <div style="font-weight: 600; color: var(--danger); margin-bottom: 8px;">
                            <i class="fas fa-exclamation-triangle"></i> Overloaded Team Members
                        </div>
                        <?php foreach ($overloadedTeam as $member): ?>
                            <div style="font-size: 13px; margin-bottom: 4px;">
                                • <?php echo e($member['full_name']); ?>: <strong><?php echo round($member['weekly_hours'], 1); ?>h</strong> this week
                                (<?php echo $member['active_tasks']; ?> active tasks)
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
```

---

## 4️⃣ THIS WEEK'S SHOOTS & SESSIONS

```php
<!-- This Week's Events -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-week"></i> This Week's Shoots & Sessions</h3>
            </div>
            <div class="card-body">
                <!-- Event Count Summary -->
                <div class="row" style="margin-bottom: 20px;">
                    <?php
                    $eventTypeIcons = [
                        'shoot' => ['icon' => 'fa-video', 'label' => 'Shoots', 'color' => '#FF6B6B'],
                        'edit' => ['icon' => 'fa-cut', 'label' => 'Edit Sessions', 'color' => '#4ECDC4'],
                        'review' => ['icon' => 'fa-eye', 'label' => 'Reviews', 'color' => '#FFE66D'],
                        'meeting' => ['icon' => 'fa-users', 'label' => 'Meetings', 'color' => '#95E1D3'],
                        'deadline' => ['icon' => 'fa-flag', 'label' => 'Deadlines', 'color' => '#F38181'],
                        'delivery' => ['icon' => 'fa-truck', 'label' => 'Deliveries', 'color' => '#AA96DA']
                    ];

                    foreach ($eventTypeIcons as $type => $data):
                        $count = $eventCounts[$type] ?? 0;
                        if ($count > 0):
                    ?>
                        <div class="col-lg-2 col-md-4 col-6">
                            <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                                <i class="fas <?php echo $data['icon']; ?>" style="font-size: 24px; color: <?php echo $data['color']; ?>; margin-bottom: 8px;"></i>
                                <div style="font-size: 20px; font-weight: 700;"><?php echo $count; ?></div>
                                <div style="font-size: 11px; color: var(--text-secondary);"><?php echo $data['label']; ?></div>
                            </div>
                        </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>

                <!-- Event List -->
                <?php if (!empty($weekEvents)): ?>
                    <div style="display: grid; gap: 10px;">
                        <?php foreach ($weekEvents as $event): ?>
                            <?php
                            $eventColor = $eventTypeIcons[$event['event_type']]['color'] ?? '#CCCCCC';
                            $eventIcon = $eventTypeIcons[$event['event_type']]['icon'] ?? 'fa-calendar';
                            ?>
                            <div style="display: flex; gap: 15px; padding: 12px; background: var(--bg-secondary); border-left: 4px solid <?php echo $eventColor; ?>; border-radius: 4px;">
                                <div style="flex-shrink: 0;">
                                    <div style="width: 50px; text-align: center;">
                                        <div style="font-size: 20px; font-weight: 700; color: var(--heading-color);">
                                            <?php echo date('d', strtotime($event['start_datetime'])); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-secondary);">
                                            <?php echo date('M', strtotime($event['start_datetime'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--heading-color); margin-bottom: 4px;">
                                        <i class="fas <?php echo $eventIcon; ?>" style="color: <?php echo $eventColor; ?>;"></i>
                                        <?php echo e($event['event_title']); ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-secondary);">
                                        <?php if ($event['project_name']): ?>
                                            📁 <?php echo e($event['project_name']); ?> •
                                        <?php endif; ?>
                                        🕐 <?php echo date('g:i A', strtotime($event['start_datetime'])); ?>
                                        <?php if ($event['location']): ?>
                                            • 📍 <?php echo e($event['location']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($event['equipment_needed']): ?>
                                        <div style="font-size: 12px; color: var(--warning); margin-top: 4px;">
                                            🎬 Equipment: <?php echo e($event['equipment_needed']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                        No events scheduled for this week
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
```

---

## 5️⃣ TOP PERFORMERS

```php
<!-- Top Performers -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-trophy"></i> Top Performers This Month</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4">
                        <?php if ($topPerformers['most_tasks']): ?>
                            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); border-radius: 12px; color: white;">
                                <i class="fas fa-tasks" style="font-size: 32px; margin-bottom: 10px;"></i>
                                <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">Most Tasks Completed</div>
                                <div style="font-size: 24px; font-weight: 700;"><?php echo e($topPerformers['most_tasks']['full_name']); ?></div>
                                <div style="font-size: 32px; font-weight: 900; margin-top: 10px;"><?php echo $topPerformers['most_tasks']['completed_tasks']; ?></div>
                                <div style="font-size: 12px; opacity: 0.8;">tasks completed</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <?php if ($topPerformers['most_hours']): ?>
                            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%); border-radius: 12px; color: white;">
                                <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 10px;"></i>
                                <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">Most Hours Logged</div>
                                <div style="font-size: 24px; font-weight: 700;"><?php echo e($topPerformers['most_hours']['full_name']); ?></div>
                                <div style="font-size: 32px; font-weight: 900; margin-top: 10px;"><?php echo round($topPerformers['most_hours']['hours'], 1); ?>h</div>
                                <div style="font-size: 12px; opacity: 0.8;">this week</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <?php if ($topPerformers['best_delivery']): ?>
                            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%); border-radius: 12px; color: white;">
                                <i class="fas fa-rocket" style="font-size: 32px; margin-bottom: 10px;"></i>
                                <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">Best On-Time Delivery</div>
                                <div style="font-size: 24px; font-weight: 700;"><?php echo e($topPerformers['best_delivery']['full_name']); ?></div>
                                <div style="font-size: 32px; font-weight: 900; margin-top: 10px;"><?php echo $topPerformers['best_delivery']['on_time_rate']; ?>%</div>
                                <div style="font-size: 12px; opacity: 0.8;"><?php echo $topPerformers['best_delivery']['on_time']; ?>/<?php echo $topPerformers['best_delivery']['total_tasks']; ?> on time</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 6️⃣ RISK INDICATORS & PROJECT HEALTH

```php
<!-- Risk Indicators -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Risk Indicators</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($riskyProjects)): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="font-weight: 600; margin-bottom: 10px; color: var(--danger);">
                            <i class="fas fa-fire"></i> Projects At Risk
                        </div>
                        <?php foreach ($riskyProjects as $risk): ?>
                            <?php
                            $budgetPercent = $risk['total_budget'] > 0 ? round(($risk['spent'] / $risk['total_budget']) * 100) : 0;
                            $taskPercent = $risk['total_tasks'] > 0 ? round(($risk['completed_tasks'] / $risk['total_tasks']) * 100) : 0;
                            ?>
                            <div style="padding: 12px; background: var(--danger)10; border-left: 3px solid var(--danger); border-radius: 4px; margin-bottom: 10px;">
                                <div style="font-weight: 600; margin-bottom: 5px;"><?php echo e($risk['project_name']); ?></div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">
                                    <?php if ($risk['client_name']): ?>
                                        Client: <?php echo e($risk['client_name']); ?> •
                                    <?php endif; ?>
                                    <?php echo abs($risk['days_remaining']); ?> days <?php echo $risk['days_remaining'] < 0 ? 'overdue' : 'remaining'; ?>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                                    <div>
                                        <div style="color: var(--text-secondary);">Budget Used</div>
                                        <div style="font-weight: 700; color: <?php echo $budgetPercent > 100 ? 'var(--danger)' : 'var(--warning)'; ?>;">
                                            <?php echo $budgetPercent; ?>%
                                        </div>
                                    </div>
                                    <div>
                                        <div style="color: var(--text-secondary);">Tasks Complete</div>
                                        <div style="font-weight: 700; color: <?php echo $taskPercent < 50 ? 'var(--danger)' : 'var(--warning)'; ?>;">
                                            <?php echo $taskPercent; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($risks['blocked_projects'])): ?>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 10px; color: var(--warning);">
                            <i class="fas fa-hand-paper"></i> Long-Blocked Tasks
                        </div>
                        <?php foreach ($risks['blocked_projects'] as $blocked): ?>
                            <div style="padding: 10px; background: var(--warning)10; border-left: 3px solid var(--warning); border-radius: 4px; margin-bottom: 8px;">
                                <div style="font-weight: 600;"><?php echo e($blocked['project_name']); ?></div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <?php echo $blocked['blocked_count']; ?> task<?php echo $blocked['blocked_count'] > 1 ? 's' : ''; ?> blocked >3 days
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($riskyProjects) && empty($risks['blocked_projects'])): ?>
                    <div style="text-align: center; padding: 40px; color: var(--success);">
                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <div style="font-size: 18px; font-weight: 600;">All Projects Looking Good!</div>
                        <div style="font-size: 14px; margin-top: 5px;">No critical risks detected</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Project Health Score -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-heartbeat"></i> Project Health Overview</h3>
            </div>
            <div class="card-body">
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-4">
                        <div style="text-align: center; padding: 20px; background: var(--success)20; border-radius: 8px;">
                            <div style="font-size: 36px; font-weight: 900; color: var(--success);"><?php echo $healthCount['healthy']; ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                <i class="fas fa-check-circle" style="color: var(--success);"></i> Healthy
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 20px; background: var(--warning)20; border-radius: 8px;">
                            <div style="font-size: 36px; font-weight: 900; color: var(--warning);"><?php echo $healthCount['warning']; ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Warning
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 20px; background: var(--danger)20; border-radius: 8px;">
                            <div style="font-size: 36px; font-weight: 900; color: var(--danger);"><?php echo $healthCount['critical']; ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                <i class="fas fa-times-circle" style="color: var(--danger);"></i> Critical
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Health Details -->
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($healthScores as $project): ?>
                        <?php
                        $healthColors = [
                            'healthy' => 'var(--success)',
                            'warning' => 'var(--warning)',
                            'critical' => 'var(--danger)'
                        ];
                        $healthIcons = [
                            'healthy' => 'fa-check-circle',
                            'warning' => 'fa-exclamation-triangle',
                            'critical' => 'fa-times-circle'
                        ];
                        ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; border-bottom: 1px solid var(--border-light);">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 14px;"><?php echo e($project['project_name']); ?></div>
                                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">
                                    <?php if ($project['blocked_count'] > 0): ?>
                                        🚫 <?php echo $project['blocked_count']; ?> blocked •
                                    <?php endif; ?>
                                    <?php if ($project['overdue_count'] > 0): ?>
                                        ⏰ <?php echo $project['overdue_count']; ?> overdue •
                                    <?php endif; ?>
                                    <?php if ($project['days_remaining'] !== null): ?>
                                        📅 <?php echo abs($project['days_remaining']); ?> days <?php echo $project['days_remaining'] < 0 ? 'late' : 'left'; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas <?php echo $healthIcons[$project['health_status']]; ?>"
                                   style="font-size: 20px; color: <?php echo $healthColors[$project['health_status']]; ?>;"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 7️⃣ PENDING DELIVERABLES

```php
<!-- Pending Deliverables -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-video"></i> Pending Deliverables</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="card-value"><?php echo $deliverables['pending_approval']; ?></div>
                            <div class="card-label">Awaiting Approval</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="card-value"><?php echo $deliverables['by_version']['v1'] ?? 0; ?></div>
                            <div class="card-label">Version 1</div>
                            <div class="card-trend">First Submission</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas fa-redo"></i>
                            </div>
                            <div class="card-value"><?php echo $deliverables['by_version']['v2'] ?? 0; ?></div>
                            <div class="card-label">Version 2</div>
                            <div class="card-trend">1 Revision</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon danger">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <div class="card-value"><?php echo $deliverables['by_version']['v3_plus'] ?? 0; ?></div>
                            <div class="card-label">Version 3+</div>
                            <div class="card-trend">Multiple Revisions</div>
                        </div>
                    </div>
                </div>

                <?php if ($deliverables['longest_pending']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: var(--warning)10; border-left: 3px solid var(--warning); border-radius: 4px;">
                        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 5px;">
                            <i class="fas fa-hourglass-half"></i> Longest Pending Approval
                        </div>
                        <div style="font-weight: 600; font-size: 15px; color: var(--heading-color);">
                            <?php echo e($deliverables['longest_pending']['project_name']); ?> -
                            <?php echo e($deliverables['longest_pending']['feedback_title']); ?>
                        </div>
                        <div style="font-size: 13px; color: var(--warning); margin-top: 5px;">
                            ⏱️ <?php echo $deliverables['longest_pending']['days_pending']; ?> days pending
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
```

---

## 🎯 SUMMARY

**10 NEW SECTIONS ADDED:**
1. ✅ Financial Overview - Revenue, profit, budgets
2. ✅ Client Happiness Metrics - Approvals, revisions, satisfaction
3. ✅ Project Type Breakdown - Video, Photo, Strategy counts
4. ✅ Team Capacity - Utilization, overloaded members
5. ✅ This Week's Shoots & Sessions - Upcoming events
6. ✅ Top Performers - Recognition and motivation
7. ✅ Risk Indicators - At-risk projects
8. ✅ Project Health Score - Visual health status
9. ✅ Pending Deliverables - Client approval tracking
10. ✅ All data queries already added to PHP file

---

## 📋 NEXT STEPS

1. Copy each section above
2. Insert them after line 840 in `master-dashboard.php` (after existing metrics)
3. Save and refresh your Master Dashboard
4. Run the SQL migration first if you haven't already

All queries are ready - just add the HTML sections! 🚀
