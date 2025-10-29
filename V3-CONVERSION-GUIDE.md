# Foxhole V3 - Complete Conversion Guide

This guide provides step-by-step instructions for converting all remaining pages to V3.

## ✅ What's Already Done

### Foundation (100%)
- ✅ V3 CSS Design System (`assets/css/vien-v3.css`)
- ✅ V2 Complete Backup (`backup-v2/`)
- ✅ Documentation (`V3-REDESIGN.md`)

### Sidebars (100%)
- ✅ `includes/v3-admin-sidebar.php`
- ✅ `includes/v3-manager-sidebar.php`
- ✅ `includes/v3-employee-sidebar.php`
- ✅ `includes/v3-header.php`

### Templates (100%)
- ✅ `includes/v3-page-template.php` - Base template for conversions

### Converted Pages (2)
- ✅ `login.php` - V3 auth page
- ✅ `admin/index.php` - V3 dashboard

## 📋 Pages To Convert

### Admin Portal (9 pages)

#### 1. admin/projects.php
**Current:** V2 with `ultra-premium.css` and `admin-sidebar.php`
**Convert to:**
```php
// Replace CSS
<link rel="stylesheet" href="../assets/css/vien-v3.css">

// Replace sidebar
<?php include '../includes/v3-admin-sidebar.php'; ?>

// Add V3 header
<?php include '../includes/v3-header.php'; ?>

// Use V3 classes:
- .dashboard-card instead of .stat-card
- .badge badge-success instead of .status-completed
- .btn btn-primary instead of old button classes
- .data-table-container and .data-table for tables
```

#### 2. admin/team.php
- Same conversion pattern as projects.php
- Update card styling to V3 dashboard cards
- Update table to data-table format
- Add V3 badges for user status

#### 3. admin/users.php
- Convert form inputs to V3 style (form-control class)
- Update buttons to V3 button styles
- Use V3 modal if applicable
- Add V3 validation styling

#### 4. admin/analytics.php
- Update chart containers to V3 card style
- Use V3 dashboard cards for stats
- Update color schemes to match V3 palette

#### 5. admin/advanced-analytics.php
- Same as analytics.php
- Update all charts to use V3 colors

#### 6. admin/budget.php
- Convert tables to V3 data-table
- Update currency display with V3 badges
- Use V3 progress bars

#### 7. admin/email-config.php
- Update form styling to V3
- Use V3 switches (custom-switch class)
- Update buttons to V3 gradient style

#### 8. admin/email-test.php
- Update to V3 card style
- Use V3 buttons
- Update alert styling

#### 9. admin/gamification.php
- Update leaderboard table to V3 style
- Use V3 badges for ranks
- Update trophy icons to V3 gradient style

### Manager Portal (7 pages)

#### 1. manager/index.php
**Template:** Copy from `admin/index.php`
**Changes:**
- Replace sidebar: `<?php include '../includes/v3-manager-sidebar.php'; ?>`
- Update queries to filter by manager's projects
- Keep same V3 card and table styling

#### 2. manager/projects.php
- Copy structure from admin/projects.php
- Filter projects by assigned_manager
- Use v3-manager-sidebar.php

#### 3. manager/tasks.php
- Convert to V3 data-table
- Add V3 task status badges
- Use v3-manager-sidebar.php

#### 4. manager/team.php
- Convert to V3 format
- Use V3 user cards or table
- Use v3-manager-sidebar.php

#### 5. manager/create-project.php
- Convert form to V3 style
- Use V3 form-control class
- Add V3 multi-select styling

#### 6. manager/create-task.php
- Convert form to V3
- Update dependency selector styling
- Use V3 buttons

#### 7. manager/manage-users.php
- Convert form to V3 style
- Use V3 validation feedback
- Use v3-manager-sidebar.php

### Employee Portal (5 pages)

#### 1. employee/index.php
**Template:** Copy from `admin/index.php`
**Changes:**
- Replace sidebar: `<?php include '../includes/v3-employee-sidebar.php'; ?>`
- Show only employee's assigned tasks
- Use V3 dashboard cards
- Show personal stats

#### 2. employee/tasks.php
- Convert to V3 data-table
- Add task status badges
- Use v3-employee-sidebar.php
- Add quick action buttons

#### 3. employee/create-task.php
- Convert form to V3 style
- Use V3 form controls
- Use v3-employee-sidebar.php

#### 4. employee/my-stats.php
- Use V3 dashboard cards
- Update charts to V3 colors
- Use V3 badges and progress bars

#### 5. employee/time-logs.php
- Convert to V3 data-table
- Add time duration badges
- Use V3 date picker styling

## 🔄 Conversion Steps (For Each Page)

### Step 1: Backup Original
```php
// No need - already backed up in backup-v2/
```

### Step 2: Update DOCTYPE and Head
```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
</head>
```

### Step 3: Update Body Structure
```php
<body>
    <div class="app-container">
        <!-- Choose appropriate sidebar -->
        <?php include '../includes/v3-admin-sidebar.php'; ?>
        <!-- OR v3-manager-sidebar.php OR v3-employee-sidebar.php -->

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page content here -->
            </div>
        </div>
    </div>
</body>
```

### Step 4: Convert Components

#### Dashboard Cards
```php
<!-- V2 -->
<div class="stat-card blue">
    <div class="stat-value">123</div>
    <div class="stat-label">Label</div>
</div>

<!-- V3 -->
<div class="dashboard-card">
    <div class="card-icon success">
        <i class="fas fa-icon"></i>
    </div>
    <div class="card-value">123</div>
    <div class="card-label">Label</div>
    <div class="card-trend up">12%</div>
</div>
```

#### Data Tables
```php
<!-- V3 Table Structure -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 style="margin: 0;">Table Title</h3>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                Description
            </p>
        </div>
        <a href="#" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add New
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container" style="border: none; box-shadow: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="sortable">Column</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- rows -->
                </tbody>
            </table>
        </div>
    </div>
</div>
```

#### Badges
```php
<!-- V3 Badges -->
<span class="badge badge-success">Success</span>
<span class="badge badge-warning">Warning</span>
<span class="badge badge-danger">Danger</span>
<span class="badge badge-info">Info</span>
<span class="badge badge-primary">Primary</span>
```

#### Buttons
```php
<!-- V3 Buttons -->
<button class="btn btn-primary">Primary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-danger">Danger</button>
<button class="btn btn-outline">Outline</button>
<button class="btn btn-primary btn-sm">Small</button>
<button class="btn btn-primary btn-icon"><i class="fas fa-plus"></i></button>
```

#### Forms
```php
<!-- V3 Form -->
<div class="form-group">
    <label>Field Label <span class="required">*</span></label>
    <input type="text" class="form-control" placeholder="Placeholder">
    <small class="form-text">Help text</small>
    <div class="invalid-feedback">Error message</div>
</div>
```

## 🎨 V3 Color Variables

Use these throughout converted pages:

```css
/* Colors */
--primary: #145388
--success: #17b06b
--warning: #f8b739
--danger: #ec4561
--info: #2ea1f8

/* Text */
--text-primary: #424767
--text-secondary: #8f95b2
--heading-color: #313754

/* Backgrounds */
--body-bg: #f8f8f8
--card-bg: #ffffff
```

## 🧪 Testing Checklist

After converting each page:

- [ ] Page loads without errors
- [ ] Sidebar highlights correct menu item
- [ ] Header displays correctly
- [ ] All buttons work
- [ ] Tables display properly
- [ ] Forms submit correctly
- [ ] Responsive on mobile (test XXS, XS, SM breakpoints)
- [ ] Hover effects work
- [ ] Icons display
- [ ] Colors match V3 palette

## 🚀 Quick Start

To convert a page quickly:

1. Copy `includes/v3-page-template.php`
2. Paste PHP logic from original page
3. Update role check
4. Update sidebar include
5. Replace HTML content with V3 structure
6. Test the page
7. Commit

## 📝 Commit Messages

Use descriptive commit messages:

```
Convert admin/projects.php to V3 design
Convert manager portal to V3 (7 pages)
Convert employee portal to V3 (5 pages)
Final V3 conversion - all pages complete
```

## 🎯 Conversion Priority

### High Priority (Convert First)
1. admin/projects.php - Most used admin page
2. employee/tasks.php - Most used employee page
3. manager/projects.php - Most used manager page

### Medium Priority
4. admin/team.php
5. manager/index.php
6. employee/index.php

### Low Priority (Convert Last)
7. All other pages

## 💡 Tips

1. **Copy, don't start from scratch** - Use the template
2. **Test as you go** - Don't convert 10 pages then test
3. **Commit frequently** - After each page or small batch
4. **Use Find & Replace** - For common conversions (class names)
5. **Check mobile** - V3 is mobile-first
6. **Preserve functionality** - Don't change PHP logic, just styling

## 🔗 Resources

- V3 CSS: `assets/css/vien-v3.css`
- Template: `includes/v3-page-template.php`
- Documentation: `V3-REDESIGN.md`
- Backup: `backup-v2/` (for reference)

## ✅ Completion Checklist

### Admin Portal
- [ ] admin/projects.php
- [ ] admin/team.php
- [ ] admin/users.php
- [ ] admin/analytics.php
- [ ] admin/advanced-analytics.php
- [ ] admin/budget.php
- [ ] admin/email-config.php
- [ ] admin/email-test.php
- [ ] admin/gamification.php

### Manager Portal
- [ ] manager/index.php
- [ ] manager/projects.php
- [ ] manager/tasks.php
- [ ] manager/team.php
- [ ] manager/create-project.php
- [ ] manager/create-task.php
- [ ] manager/manage-users.php

### Employee Portal
- [ ] employee/index.php
- [ ] employee/tasks.php
- [ ] employee/create-task.php
- [ ] employee/my-stats.php
- [ ] employee/time-logs.php

---

**Total Pages:** 21 remaining
**Estimated Time:** 2-4 hours (with template)
**Difficulty:** Easy (with template and guide)

Good luck with the conversion! 🚀
