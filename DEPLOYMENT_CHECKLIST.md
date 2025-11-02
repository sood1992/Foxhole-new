# Production Deployment Checklist
## Foxhole Project Management System - Go Live

### Pre-Deployment Database Setup

#### 1. Backup Current Database
```bash
# Create backup before any changes
mysqldump -u [username] -p [database_name] > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### 2. Run Database Migrations (IN ORDER)
```bash
# Step 1: Fix any existing table conflicts
mysql -u [username] -p [database_name] < migrations/fix_existing_tables.sql

# Step 2: Run main feature migrations
mysql -u [username] -p [database_name] < migrations/final_production_features.sql
```

**Expected Results:**
- ✅ user_roles table created with 16+ user role records
- ✅ user_points table created with 16+ user point records
- ✅ user_badges table created
- ✅ saved_filters table created
- ✅ task_templates and task_template_items tables created
- ✅ daily_plans table created
- ✅ pomodoro_sessions table created
- ✅ reviews table created
- ✅ task_dependencies table created

#### 3. Verify Database Integrity
```sql
-- Check tables exist
SHOW TABLES LIKE 'user_%';
SHOW TABLES LIKE '%template%';
SHOW TABLES LIKE 'daily_plans';
SHOW TABLES LIKE 'reviews';

-- Verify data was migrated
SELECT COUNT(*) FROM user_roles;    -- Should show existing user count
SELECT COUNT(*) FROM user_points;   -- Should show existing user count
```

---

### Code Deployment

#### 1. Git Pull Latest Changes
```bash
cd /path/to/foxhole
git pull origin claude/edit-tasks-project-option-011CUgoXxfvyhWT1JrYCPdCS
```

#### 2. Verify File Permissions
```bash
# Sessions directory must be writable
chmod 755 sessions/
chmod 755 uploads/

# Check PHP files are readable
find . -name "*.php" -type f -exec chmod 644 {} \;
```

#### 3. Clear PHP OpCache (if enabled)
```bash
# Restart PHP-FPM or Apache
sudo systemctl restart php-fpm
# OR
sudo systemctl restart apache2
```

---

### Feature Verification Tests

#### Employee Panel Tests
Test URL: `https://neofox.live/employee/`

- [ ] **Dashboard** (`index.php`)
  - Gamification widget displays (Level, Points, Streak)
  - Stats cards show in horizontal grid (4 columns)
  - Time tracker works
  - My tasks load correctly

- [ ] **Daily Plan** (`daily-plan.php`)
  - Can add tasks to today's plan
  - Can mark tasks as completed
  - Can remove tasks from plan
  - Progress percentage updates

- [ ] **Reviews** (`reviews.php?type=weekly`)
  - Weekly review form loads
  - Can save accomplishments, challenges, lessons
  - Mood selector works
  - Productivity rating slider works
  - Review history displays

- [ ] **Task Templates** (`task-templates.php`)
  - Templates list displays
  - Can create new template
  - Can use template to create tasks
  - Public/private toggle works

- [ ] **Time Analytics** (`time-analytics.php`)
  - Charts render correctly (Chart.js loaded)
  - Time breakdown by project shows
  - Productivity trends display
  - Period selector works (week/month)

- [ ] **Rewards** (`rewards.php`)
  - Current title and icon display
  - Badges earned show correctly
  - Milestones progress displays
  - Streak rewards visible

- [ ] **Focus Mode** (`focus-mode.php`)
  - Loads with task selected
  - Pomodoro timer works
  - Full-screen mode functions
  - Break timer works

#### Manager Panel Tests
Test URL: `https://neofox.live/manager/`

- [ ] **Edit Task** (`edit-task.php?id=[task_id]`)
  - Task loads correctly
  - Can change status to "completed"
  - **CRITICAL**: Verify gamification triggers (check user_points table)
  - Dependencies can be set
  - Form validation works

- [ ] **Create Project** (`create-project.php`)
  - Multi-manager assignment works
  - All form fields save correctly
  - Budget tracking fields work

- [ ] **Team Management** (`team.php`)
  - Team members list loads
  - Performance metrics show
  - Can view team stats

#### Admin Panel Tests
Test URL: `https://neofox.live/admin/`

- [ ] **Master Dashboard** (`master-dashboard.php`)
  - All projects load with stats
  - Team activity shows
  - Bottlenecks section displays
  - Performance metrics accurate

- [ ] **Gamification** (`gamification.php`) - **NEW FEATURE**
  - Leaderboard displays with top 20 users
  - Points, levels, streaks show correctly
  - Recent badges section loads
  - Level distribution chart renders
  - Overall stats cards display

- [ ] **Dashboard** (`index.php`)
  - Tab navigation works (Overview, Statistics, Activity)
  - Stats grid displays horizontally
  - Charts render correctly

---

### Gamification System Tests

#### Test Task Completion Flow
1. As Manager: Edit a task and change status to "completed"
2. Verify in database:
```sql
SELECT * FROM user_points WHERE user_id = [assigned_user_id];
-- Points should have increased
-- total_tasks_completed should increment
```

3. Check for badges:
```sql
SELECT * FROM user_badges WHERE user_id = [assigned_user_id] ORDER BY earned_at DESC;
-- May see new badges for milestones (1st task, 10 tasks, etc.)
```

4. As Employee: Check rewards page
   - Points should reflect completion
   - Level may have increased
   - New badges should appear

#### Test Streak System
1. Complete tasks on consecutive days
2. Verify current_streak increments
3. Check for streak badges (3-day, 7-day, etc.)

---

### Navigation & UI Tests

#### Sidebar Navigation
- [ ] Employee sidebar shows all menu items with text labels
- [ ] Manager sidebar shows all menu items with text labels
- [ ] Admin sidebar shows all menu items with text labels
- [ ] Clicking main menu items expands correct submenu
- [ ] Only active submenu section is visible
- [ ] Active page is highlighted correctly

#### Responsive Design
- [ ] Desktop (1920x1080): Stats show in 4 columns
- [ ] Tablet (768px): Stats show in 2 columns
- [ ] Mobile (375px): Stats show in 1 column
- [ ] Calendar is usable on mobile
- [ ] Forms are usable on mobile

---

### Security Checks

#### Authentication
- [ ] Unauthenticated users redirected to login
- [ ] Employees cannot access manager pages
- [ ] Managers cannot access admin pages
- [ ] Session timeout works correctly

#### SQL Injection Protection
- [ ] All queries use prepared statements
- [ ] No raw $_GET/$_POST in SQL queries
- [ ] Input validation on all forms

#### Error Handling
- [ ] PHP errors don't expose sensitive info
- [ ] Database errors handled gracefully
- [ ] Missing tables return safe defaults (gamification)

---

### Performance Tests

#### Page Load Times
- [ ] Dashboard loads < 2 seconds
- [ ] Master Dashboard loads < 3 seconds
- [ ] API calls respond < 500ms
- [ ] Charts render smoothly

#### Database Performance
```sql
-- Check for missing indexes
EXPLAIN SELECT * FROM tasks WHERE assigned_to = 1 AND status = 'todo';
EXPLAIN SELECT * FROM user_points WHERE user_id = 1;
```

---

### Known Issues & Workarounds

#### 1. Calendar Toggle Buttons (Reported by User)
**Issue**: FullCalendar view toggle buttons may not be visually obvious
**Status**: Buttons are functional, rendered by FullCalendar library
**Workaround**: Buttons appear in top-right of calendar (Month, Week, Day, List)
**Action**: Monitor user feedback; may need CSS customization

#### 2. First-Time Migration
**Issue**: Users without gamification tables may see errors
**Status**: FIXED - getUserRoles() has defensive try-catch
**Verification**: Visit any page before running migrations - should not crash

---

### Rollback Plan

If critical issues occur:

1. **Database Rollback**
```bash
# Restore from backup
mysql -u [username] -p [database_name] < backup_[timestamp].sql
```

2. **Code Rollback**
```bash
# Revert to previous commit
git log --oneline  # Find last working commit
git checkout [commit-hash]
```

3. **Quick Fix Options**
   - Disable gamification by commenting out `require_once '../includes/gamification-functions.php'`
   - Remove new menu items from sidebars
   - Redirect new feature pages to dashboard temporarily

---

### Post-Deployment Monitoring

#### First Hour
- [ ] Monitor error logs: `tail -f /var/log/apache2/error.log`
- [ ] Check database slow queries
- [ ] Verify user logins working
- [ ] Test task creation and completion

#### First Day
- [ ] Review user feedback
- [ ] Check gamification point awards
- [ ] Verify all background jobs running
- [ ] Monitor database size growth

#### First Week
- [ ] Analyze feature adoption
- [ ] Review leaderboard activity
- [ ] Check for unused features
- [ ] Plan optimization if needed

---

### Support & Documentation

#### For Users
- Gamification: Points earned by completing tasks (10 base + priority bonus)
- Daily Plan: Drag tasks to plan your day, check off when done
- Reviews: Weekly/monthly reflections for personal growth
- Templates: Save recurring task lists for quick creation

#### For Admins
- Leaderboard updates in real-time as tasks complete
- Badges auto-awarded at milestones (1, 10, 50, 100, 500 tasks)
- Streaks track consecutive days of task completion
- Master Dashboard shows comprehensive office overview

---

### Emergency Contacts
- **Database Issues**: [DBA Contact]
- **Server Issues**: [DevOps Contact]
- **Application Bugs**: GitHub Issues or [Support Email]
- **User Training**: [Training Team Contact]

---

## Deployment Sign-Off

- [ ] Database migrations successful
- [ ] All syntax checks passed
- [ ] Feature tests completed
- [ ] Security audit passed
- [ ] Performance acceptable
- [ ] Rollback plan ready
- [ ] Monitoring configured
- [ ] User documentation provided

**Deployed By**: _______________
**Date**: _______________
**Time**: _______________

**Approved By**: _______________
**Sign-Off**: _______________

---

## Notes
_Add any deployment notes, issues encountered, or special configurations here:_




