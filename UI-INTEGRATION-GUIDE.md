# 🎨 UI Integration Guide - Agency Features

## Quick Start

All backend APIs are complete and working. Now just add the UI components to your pages!

---

## 1️⃣ CLIENT FEEDBACK - Add to Project Detail Page

### File: `admin/project-detail.php` or `manager/project-detail.php`

**Add this line after the "Team Members" section (around line 290):**

```php
<?php include '../includes/client-feedback-widget.php'; ?>
```

**That's it!** The widget includes everything:
- Add feedback form (for managers)
- Feedback list display
- Response system
- Status management
- All JavaScript included

---

## 2️⃣ BUDGET DASHBOARD - Add to Project Detail Page

### File: `admin/project-detail.php` or `manager/project-detail.php`

**Add this line after the "Client Feedback" section:**

```php
<?php include '../includes/budget-dashboard-widget.php'; ?>
```

**Features included:**
- Budget summary cards
- Progress bars with color coding
- Expense tracking
- Add/edit budget forms
- All JavaScript included

---

## 3️⃣ CALENDAR ENHANCEMENTS - Add to Calendar Pages

### Files: `admin/calendar.php`, `manager/calendar.php`, `employee/calendar.php`

**Step 1: Add JavaScript file in `<head>` section:**

```html
<script src="../assets/js/calendar-events.js"></script>
```

**Step 2: After calendar renders, initialize events:**

```javascript
// Find where your calendar is initialized (look for: calendarInstance.render())
calendarInstance.render();

// ADD THIS RIGHT AFTER:
initializeCalendarEvents(calendarInstance);

// Add color legend
addCalendarLegend('.content-wrapper'); // or your container selector
```

**Step 3: Add "+ Add Event" button to your calendar header:**

```html
<button class="btn btn-primary" onclick="openAddEventModal()">
    <i class="fas fa-plus"></i> Add Event
</button>
```

---

## 📱 EMPLOYEE DASHBOARD - Show Assigned Feedback

### File: `employee/index.php`

**Add after the stats cards (around line 120):**

```php
<!-- My Feedback Widget -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3><i class="fas fa-comments"></i> Client Feedback for Me</h3>
    </div>
    <div class="card-body">
        <div id="my-feedback-widget"></div>
    </div>
</div>

<script>
// Load feedback assigned to current user
fetch('/api/client-feedback.php')
    .then(r => r.json())
    .then(data => {
        if (data.success && data.feedbacks.length > 0) {
            const container = document.getElementById('my-feedback-widget');
            container.innerHTML = data.feedbacks.map(f => `
                <div style="padding: 16px; background: var(--bg-secondary); border-radius: 8px; margin-bottom: 12px; border-left: 3px solid var(--${f.priority === 'urgent' ? 'danger' : 'warning'});">
                    <div style="font-weight: 600; margin-bottom: 8px;">
                        ${f.feedback_title}
                        <span class="badge badge-${f.priority}">${f.priority}</span>
                    </div>
                    <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">
                        Project: ${f.project_name}
                    </div>
                    <div style="font-size: 14px; color: var(--text-primary); margin-bottom: 12px;">
                        ${f.feedback_text.substring(0, 150)}...
                    </div>
                    <a href="../admin/project-detail.php?id=${f.project_id}#feedback" class="btn btn-sm btn-primary">
                        View & Respond
                    </a>
                </div>
            `).join('');
        } else {
            document.getElementById('my-feedback-widget').innerHTML = `
                <p style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    No pending feedback
                </p>
            `;
        }
    });
</script>
```

---

## 🚀 FULL EXAMPLE - Enhanced Project Detail Page

Here's a complete example of `admin/project-detail.php` with all features:

```php
<?php
// ... existing PHP code ...
?>
<!DOCTYPE html>
<html>
<head>
    <!-- ... existing head content ... -->
    <script src="../assets/js/calendar-events.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Existing project header, stats cards, etc. -->

                <!-- Team Members (existing) -->
                <?php if (!empty($teamMembers)): ?>
                    <!-- ... existing team members section ... -->
                <?php endif; ?>

                <!-- 🆕 CLIENT FEEDBACK WIDGET -->
                <?php include '../includes/client-feedback-widget.php'; ?>

                <!-- 🆕 BUDGET DASHBOARD WIDGET -->
                <?php include '../includes/budget-dashboard-widget.php'; ?>

                <!-- All Tasks (existing) -->
                <!-- ... existing tasks section ... -->
            </div>
        </div>
    </div>
</body>
</html>
```

---

## 🎨 STYLING NOTES

All widgets use CSS variables from `vien-v3.css`:
- `var(--card-bg)` - Card backgrounds
- `var(--border-light)` - Borders
- `var(--primary)` - Primary color
- `var(--success)` - Green
- `var(--warning)` - Orange
- `var(--danger)` - Red

Modal styles are included in the widget files.

---

## 🧪 TESTING

### Test Client Feedback:
1. Go to a project detail page
2. Click "Add Feedback"
3. Fill form and assign to employee
4. Login as employee
5. See feedback in their dashboard
6. Click "Respond"

### Test Budget:
1. Go to project detail page
2. Click "Edit Budget"
3. Set total budget (e.g., $5,000)
4. Click "Add Expense"
5. Add an expense (e.g., Equipment $500)
6. See budget update automatically

### Test Calendar:
1. Go to any calendar page
2. Click "+ Add Event"
3. Select "Shoot" as event type
4. Fill details
5. See red event appear on calendar
6. Click event to view details

---

## 📞 TROUBLESHOOTING

**Q: Widgets not loading?**
- Check browser console for errors
- Verify database tables created (run `add-agency-features.sql`)
- Ensure `$projectId` variable exists in page

**Q: Modals not showing?**
- Check if modal CSS is loaded
- Look for JavaScript errors in console
- Ensure .modal class styles are in vien-v3.css

**Q: API returning errors?**
- Verify user is logged in
- Check role permissions
- Look at API endpoint response in Network tab

---

## 🎯 WHAT YOU GET

### For Project Managers:
- ✅ Add client feedback directly in project
- ✅ Assign feedback to team members with priority
- ✅ Track budget vs actual spending
- ✅ Add expenses with categories
- ✅ Schedule shoots, edits, reviews on calendar

### For Employees:
- ✅ See assigned feedback in dashboard
- ✅ Respond to feedback with updates
- ✅ View upcoming shoots/edits in calendar
- ✅ See all team events

### For Admins:
- ✅ Complete visibility into all feedback
- ✅ Monitor project budgets
- ✅ See team schedule at a glance
- ✅ Track expenses by category

---

## 🔥 PRO TIPS

1. **Feedback Priority**: Use "Urgent" sparingly - it's for critical issues only
2. **Budget Categories**: Match your expense categories to budget breakdown
3. **Calendar Colors**: Everyone learns the color system quickly
4. **Team Assignment**: Assign specific people to calendar events
5. **Equipment Lists**: Be detailed - helps team prepare

---

## ✅ NEXT ACTIONS

1. **Run Database Script**: `add-agency-features.sql` (if not done)
2. **Add Feedback Widget**: To admin/project-detail.php
3. **Add Budget Widget**: To admin/project-detail.php
4. **Enhance Calendars**: Add calendar-events.js to all calendar pages
5. **Test Everything**: Create feedback, add expenses, schedule events
6. **Train Team**: Show them the new features!

---

**All done! You now have a complete creative agency management system! 🎉**
