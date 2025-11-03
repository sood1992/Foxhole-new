# FOXHOLE - COMPLETE PROJECT AUDIT & DOCUMENTATION
## Comprehensive Reference Guide for All Features & Functionalities

**Date:** November 3, 2025
**Version:** 2.2.0
**Purpose:** Definitive reference to prevent feature overwrites and document all functionality
**Audited By:** Lead Developer

---

## 🚨 CRITICAL FINDINGS FROM AUDIT

### ❌ **Issues Found:**

1. **Missing Feature: Gamification Page**
   - **Location:** `admin/gamification.php` - **DOES NOT EXIST**
   - **Referenced in:** Admin sidebar (v3-admin-sidebar.php:35-38, 111-123)
   - **Database:** Gamification schema EXISTS (gamification-schema.sql)
   - **Impact:** Broken link in admin navigation
   - **Fix Required:** Create gamification.php page with leaderboard, badges, achievements

2. **Missing Feature: Profile Page**
   - **Location:** `admin/profile.php` - **DOES NOT EXIST**
   - **Referenced in:** Admin sidebar settings section (v3-admin-sidebar.php:148)
   - **Alternative:** `admin/user-profile.php` EXISTS but not linked correctly
   - **Impact:** Broken link in admin settings
   - **Fix Required:** Either create profile.php or update sidebar to point to user-profile.php

3. **Placeholder Code: Notifications Button**
   - **Location:** `includes/v3-header.php:103`
   - **Code:** `alert('Notifications feature - coming soon!');`
   - **Reality:** Notifications ARE fully implemented (notifications-dropdown.php, api/notifications.php)
   - **Impact:** Users see "coming soon" when notifications actually work
   - **Fix Required:** Remove placeholder alert, use actual notifications-dropdown.php component

4. **NO Amazing Marvin Features**
   - **IMPORTANT:** There are **NO Amazing Marvin features** in this project
   - **NO Pomodoro Timer** - Only regular time tracking with start/stop
   - **Confusion:** User asked about pomodoro timer - it doesn't exist
   - **Reality:** Simple time tracking system (start/stop timers, log hours)

---

## 📊 PROJECT OVERVIEW

### **What is Foxhole?**
Foxhole is a **productivity management platform** built for marketing agencies (specifically Neofox Media). It's a PHP/MySQL web application focused on:
- Time tracking (NOT pomodoro - just start/stop timers)
- Project management
- Task management
- Team collaboration
- Budget tracking
- Performance analytics
- Gamification (leaderboards, badges, points)

### **Technology Stack:**
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Design System:** Vien V3 (custom ultra-premium CSS)
- **External Libraries:**
  - FullCalendar.js v6.1.10 (calendar view)
  - Chart.js v4.4.0 (analytics charts)
  - Font Awesome 6.4.0 (icons)
- **Fonts:** Inter, Poppins
- **Currency:** INR (₹)
- **Timezone:** Asia/Kolkata (IST)

---

## 🎯 USER ROLES & ACCESS LEVELS

### **1. ADMIN** (Full System Control)
**Who:** CEO, Operations Manager, Company Owner

**Can Access:**
- ✅ All projects across all managers
- ✅ All users (add/edit/delete)
- ✅ All tasks (view/create/assign/delete)
- ✅ System-wide analytics
- ✅ Budget tracking (all projects)
- ✅ Bulk operations
- ✅ Team performance metrics
- ✅ Email configuration
- ✅ Advanced analytics with bottleneck detection
- ✅ Gamification leaderboards

**Cannot:**
- ❌ N/A - Admin has full access

**Dashboard Pages:**
- `/admin/index.php` - Dashboard overview
- `/admin/projects.php` - All projects
- `/admin/team.php` - Team management
- `/admin/users.php` - User add/edit
- `/admin/reports.php` - System reports
- `/admin/analytics.php` - Charts dashboard
- `/admin/advanced-analytics.php` - Performance analytics with WHO IS SLOW/QUICK detection
- `/admin/budget.php` - Budget vs actual costs
- `/admin/calendar.php` - System calendar
- `/admin/chat.php` - Team chat
- `/admin/bulk-operations.php` - Bulk add/delete projects/users
- `/admin/bulk-import.php` - CSV import
- `/admin/email-config.php` - Email settings
- `/admin/email-test.php` - Test email delivery
- `/admin/project-detail.php` - Individual project view
- `/admin/user-profile.php` - View user profiles with badges/points
- **MISSING:** `/admin/gamification.php` (referenced but doesn't exist)
- **MISSING:** `/admin/profile.php` (referenced but doesn't exist)

---

### **2. MANAGER** (Project/Team Level)
**Who:** Project Managers, Team Leads, Department Heads

**Can Access:**
- ✅ Their assigned projects only
- ✅ Tasks within their projects
- ✅ Team members on their projects
- ✅ Create/assign tasks
- ✅ View time logs for their projects
- ✅ Generate reports for their projects
- ✅ Calendar view
- ✅ Team chat

**Cannot:**
- ❌ Create/delete projects
- ❌ Add/remove users
- ❌ View other managers' projects
- ❌ Access system-wide analytics
- ❌ Modify budgets
- ❌ Bulk operations
- ❌ System configuration

**Dashboard Pages:**
- `/manager/index.php` - Manager dashboard
- `/manager/projects.php` - My assigned projects
- `/manager/create-project.php` - Request new project (NOT create - admin only)
- `/manager/tasks.php` - All tasks in my projects
- `/manager/create-task.php` - Create new tasks
- `/manager/team.php` - My team members
- `/manager/manage-users.php` - Manage team (limited)
- `/manager/reports.php` - Project reports
- `/manager/calendar.php` - Project calendar
- `/manager/chat.php` - Team chat

---

### **3. EMPLOYEE** (Individual Contributor)
**Who:** Designers, Developers, Content Writers, Video Editors

**Can Access:**
- ✅ Tasks assigned to them only
- ✅ Personal time tracking
- ✅ Personal statistics
- ✅ Time logs (own only)
- ✅ Kanban board for tasks
- ✅ Calendar view (own tasks)
- ✅ Team chat
- ✅ Personal gamification stats (points, badges)

**Cannot:**
- ❌ View other employees' data
- ❌ Create projects
- ❌ Create tasks
- ❌ Assign tasks to others
- ❌ Delete anything
- ❌ Access reports
- ❌ View team analytics
- ❌ Modify budgets

**Dashboard Pages:**
- `/employee/index.php` - Employee dashboard with active timers
- `/employee/tasks.php` - My tasks (list view with filters)
- `/employee/tasks-premium.php` - My tasks (Kanban board: To Do → In Progress → Review → Done)
- `/employee/create-task.php` - Create personal task
- `/employee/time-logs.php` - My time tracking history
- `/employee/my-stats.php` - Personal statistics & badges
- `/employee/calendar.php` - My task calendar
- `/employee/chat.php` - Team chat

---

## ⏱️ TIME TRACKING SYSTEM (NOT POMODORO!)

### **IMPORTANT CLARIFICATION:**
**There is NO Pomodoro Timer in this system!**
- No 25-minute work intervals
- No break reminders
- No Pomodoro technique implementation
- No Amazing Marvin features

### **What Actually Exists:**

**Simple Start/Stop Time Tracking:**

1. **How It Works:**
   - Employee sees task on dashboard
   - Clicks "▶ Start" button
   - Timer starts running (displays hours:minutes:seconds)
   - Employee works on task
   - Clicks "⏹ Stop Timer" when done
   - Can add optional notes
   - Duration automatically calculated and saved

2. **File Locations:**
   - **API:** `/api/time-tracking.php` (lines 1-158)
   - **Functions:** `/includes/functions.php` (lines 5-89)
   - **Employee Dashboard:** `/employee/index.php` (lines 104-133, 390-461)
   - **Tasks Pages:** All task pages have start/stop buttons

3. **Features:**
   - ✅ Multiple parallel timers (can track multiple tasks at once)
   - ✅ Real-time clock display (updates every second)
   - ✅ Automatic duration calculation
   - ✅ Manual notes per session
   - ✅ History view (all past time logs)
   - ✅ Project-based summaries
   - ✅ Automatic task status updates (changes to "in_progress" when timer starts)

4. **Database:**
   - **Table:** `time_logs`
   - **Fields:** user_id, task_id, project_id, start_time, end_time, duration_minutes, notes, is_active

5. **Where Employees Access:**
   - Dashboard: Active timer display with running clock
   - Tasks page: Start button on each task card
   - Time Logs page: `/employee/time-logs.php` - View all logged hours
   - Keyboard Shortcut: `T` + `T` = Toggle timer

6. **Calculations:**
   - Total hours per task (sum of all time_logs for task)
   - Total hours per project (sum across all tasks)
   - Labor cost: hours × hourly_rate
   - Average session length
   - Time variance: estimated vs actual

---

## 📁 COMPLETE FEATURE LIST

### **CORE FEATURES (100% Working)**

#### **1. Project Management**
**Location:** `/admin/projects.php`, `/manager/projects.php`

**Features:**
- Create projects (admin only)
- Assign project managers
- Set budgets and deadlines
- Track project status (Planning → In Progress → Review → Completed → On Hold)
- Priority levels (Low/Medium/High/Urgent)
- Client information
- Estimated vs actual hours
- Budget vs actual costs
- Progress percentage
- Task completion tracking

**Project Fields:**
- project_name
- description
- client_name
- status (5 options)
- priority (4 options)
- start_date, due_date, completed_date
- estimated_hours, actual_hours
- budget, actual_cost, currency
- created_by, assigned_manager

**Database:** `projects` table

---

#### **2. Task Management**
**Location:** `/admin/*`, `/manager/tasks.php`, `/employee/tasks.php`, `/employee/tasks-premium.php`

**Features:**
- Create tasks
- Assign to team members
- Set priorities and deadlines
- Task dependencies (link tasks that block each other)
- File attachments
- Comments system
- Status workflow: To Do → In Progress → Review → Completed → Blocked
- Kanban board view (premium)
- List view with filters
- Due date tracking with smart labels:
  - "Due today"
  - "Due tomorrow"
  - "Overdue by X days" (red)
  - "X days left" (green/yellow)

**Task Fields:**
- task_name
- description
- project_id
- assigned_to
- status (5 options)
- priority (4 options)
- estimated_hours, actual_hours
- budget, actual_cost
- start_date, due_date, completed_date
- created_by

**Task Views:**
- List view: `/employee/tasks.php` - Filterable table
- Kanban view: `/employee/tasks-premium.php` - Drag-and-drop board
- Calendar view: See tasks on calendar
- Dashboard view: Active tasks widget

**Database:** `tasks` table

---

#### **3. Time Tracking (REGULAR, NOT POMODORO)**
**Location:** All employee pages, `/employee/time-logs.php`

**How It Works:**
1. Employee dashboard shows assigned tasks
2. Click "▶ Start" on any task
3. Timer runs in real-time (HH:MM:SS)
4. Employee can work on task
5. Click "⏹ Stop Timer" when done
6. Add optional notes about work completed
7. System calculates duration in minutes
8. Updates task.actual_hours automatically
9. Updates project.actual_hours automatically

**Features:**
- Multiple active timers (work on several tasks)
- Running timer display on dashboard
- Time log history view
- Filter by date range
- Project-based time summaries
- Export time logs
- Notes per work session

**Database:** `time_logs` table

**API:** `/api/time-tracking.php`
- `start` action: Create active time log
- `stop` action: End timer, calculate duration
- `get_active` action: Get currently running timers

---

#### **4. Budget Tracking**
**Location:** `/admin/budget.php`

**Features:**
- Set project budgets (₹ INR)
- Track actual costs (labor + expenses)
- Labor cost calculation: hours logged × hourly rate per employee
- Direct expenses tracking (optional table)
- Variance analysis (over/under budget)
- Budget utilization percentage
- Cost breakdown by project
- Budget alerts

**Calculations:**
```
Labor Cost = Σ(hours_logged × user.hourly_rate)
Actual Cost = Labor Cost + Direct Expenses
Variance = Budget - Actual Cost
Utilization % = (Actual Cost / Budget) × 100
```

**Database:**
- `projects` table: budget, actual_cost, currency
- `tasks` table: budget, actual_cost
- `users` table: hourly_rate
- `expenses` table (optional): Direct project expenses

---

#### **5. Team Chat**
**Location:** `/admin/chat.php`, `/manager/chat.php`, `/employee/chat.php`

**Features:**
- Real-time team messaging
- Multiple channels:
  - General Team channel
  - Project-specific channels (one per active project)
- Message history
- Auto-refresh every 3 seconds
- User avatars
- Timestamps
- Edit/delete own messages
- Edited message indicators

**Database:** `chat_messages` table
- sender_id
- channel_type (team/project/direct)
- channel_id (project ID or NULL for general)
- message
- is_edited
- created_at, updated_at

**API:** `/api/chat.php`

---

#### **6. Calendar View**
**Location:** `/admin/calendar.php`, `/manager/calendar.php`, `/employee/calendar.php`

**Features:**
- Full calendar display using FullCalendar.js
- Multiple views: Month / Week / Day / List
- Color-coded events:
  - Blue: Default tasks
  - Orange: High priority
  - Red: Urgent priority
  - Green: Completed
  - Purple: Project milestones
- Drag-and-drop to reschedule (admin/manager)
- Click event to view details
- Filter by project/employee
- Shows all tasks with due dates
- Shows project milestones

**Database:**
- `tasks` table (where due_date IS NOT NULL)
- `project_milestones` table

**API:** `/api/calendar.php`

---

#### **7. Notifications System**
**Location:** Bell icon in header (all pages)

**Features:**
- Real-time notification dropdown
- Unread count badge
- Auto-refresh every 30 seconds
- Notification types:
  - Task assignments
  - Project updates
  - Comments/mentions
  - Approaching deadlines
  - System announcements
- Mark as read/unread
- Mark all as read
- Click to navigate to related item
- Time indicators ("5m ago", "2h ago", etc.)

**Database:** `notifications` table
- user_id
- title, message
- type (task/project/comment/mention/deadline/system)
- related_type, related_id
- is_read
- created_at

**API:** `/api/notifications.php`
**Component:** `/includes/notifications-dropdown.php`

**⚠️ ISSUE:** v3-header.php has placeholder `alert('coming soon')` but notifications are FULLY working

---

#### **8. Analytics & Reports**

**A. Basic Analytics**
**Location:** `/admin/analytics.php`

**Charts:**
- Daily Hours Tracked (line chart)
- Employee Productivity (bar chart - hours by employee)
- Project Status Distribution (doughnut chart)
- Task Completion Trend (12-week line chart)
- Active Tasks by Priority (pie chart)
- Peak Working Hours (bar chart - hourly activity)

**Features:**
- Date range filtering
- Interactive charts (Chart.js)
- Hover tooltips
- Real-time data from database

**B. Advanced Analytics**
**Location:** `/admin/advanced-analytics.php`

**WHO IS SLOW / WHO IS QUICK Detection:**

For EACH employee, calculates:
- Total tasks assigned/completed
- Average completion time
- Days before/after deadline
- Early/on-time/late completion counts
- Blocked/overdue task counts

**Scoring System (0-100):**
1. **Completion Rate Score:** (Completed / Total) × 100
2. **Speed Score:** 100 - (Avg Hours / 24) × 10
3. **Quality Score:** 100 - (Late × 10) - (Overdue × 20) + (Early × 5)
4. **Overall Productivity:** (Completion × 40%) + (Speed × 30%) + (Quality × 30%)

**Performance Categories:**
- 90-100: Excellent ⭐⭐⭐
- 75-89: Good ⭐⭐
- 60-74: Average ⭐
- <60: Needs Improvement ⚠️

**Bottleneck Detection:**
Automatically flags employees if:
- Overdue tasks > 3 OR
- Blocked tasks > 2 OR
- Completion rate < 50%

**Leaderboard:**
Ranks all employees by productivity score with medals (🥇🥈🥉)

**Database:** `performance_metrics` table (if using caching)

---

#### **9. Gamification System**
**⚠️ PARTIALLY IMPLEMENTED**

**Database:** ✅ **EXISTS** (`gamification-schema.sql`)
**Admin Page:** ❌ **MISSING** (`admin/gamification.php` does NOT exist)
**User Profile:** ✅ **EXISTS** (`admin/user-profile.php` shows badges/points)

**Tables:**
- `user_points`: total_points, streak_days, early/on-time/late completion counts
- `badges`: 10 predefined badges (First Steps, Task Master, Speed Demon, etc.)
- `user_badges`: earned badges per user
- `point_transactions`: points history
- `performance_metrics`: daily stats

**Point System:**
```
Task completion base points:
- Low priority: 10 points
- Medium priority: 20 points
- High priority: 30 points
- Urgent: 50 points

Early completion bonus:
- 1 day early: +5 points
- 2-3 days early: +10 points
- 4+ days early: +20 points

Streak bonuses:
- 7 day streak: +50 points
- 30 day streak: +200 points
```

**Badges (10 types):**
1. First Steps 🎯 (Bronze) - Complete first task
2. Getting Started ⭐ (Bronze) - Complete 5 tasks
3. Task Master 🏅 (Silver) - Complete 25 tasks
4. Productivity King 👑 (Gold) - Complete 100 tasks
5. Speed Demon ⚡ (Silver) - Complete 5 tasks early
6. Early Bird 🎁 (Gold) - Complete 20 tasks early
7. Streak Master 🔥 (Silver) - 7 day streak
8. Consistency Champion 💎 (Platinum) - 30 day streak
9. Quality Guru ✨ (Gold) - 90%+ on-time completion
10. Lightning Fast ⚡ (Platinum) - 95%+ early completion

**Where It Works:**
- User profile page: `/admin/user-profile.php` - Shows badges, points, stats
- Employee stats: `/employee/my-stats.php` - Personal achievement view
- **MISSING:** Leaderboard page (`admin/gamification.php`)

---

#### **10. File Uploads**
**Location:** Task detail pages, Project pages

**Features:**
- Attach files to tasks/projects/comments
- Supported formats:
  - Images: JPG, PNG, GIF, WEBP
  - Documents: PDF, DOCX, XLSX, TXT, CSV
  - Archives: ZIP, RAR
- 10MB file size limit
- Download files
- Delete files (uploader only)
- File type validation
- Organized storage: `/uploads/projects/`, `/uploads/tasks/`, `/uploads/comments/`

**Database:** `file_uploads` table
- file_name, file_path
- file_type, file_size
- uploaded_by
- entity_type (project/task/comment)
- entity_id
- created_at

**API:** `/api/file-upload.php`
**Component:** `/includes/file-upload-component.php`

---

#### **11. Task Dependencies**
**Location:** Task detail pages

**Features:**
- Link tasks that depend on each other
- 3 dependency types:
  - **Finish to Start:** Task B starts after Task A finishes
  - **Start to Start:** Task B starts when Task A starts
  - **Finish to Finish:** Task B finishes when Task A finishes
- Circular dependency prevention
- View blocked/blocking tasks
- Dependency visualization

**Database:** `task_dependencies` table
- task_id
- depends_on_task_id
- dependency_type
- Unique constraint prevents duplicates

**API:** `/api/dependencies.php`
**Component:** `/includes/task-dependencies-component.php`

---

#### **12. Quick Actions (Keyboard Shortcuts)**
**Location:** All pages (when included)

**Shortcuts:**

**Navigation:**
- `G` + `D` → Dashboard
- `G` + `T` → Tasks
- `G` + `C` → Calendar
- `G` + `H` → Chat
- `G` + `P` → Projects
- `G` + `A` → Analytics

**Actions:**
- `Ctrl/Cmd` + `K` → Command palette
- `?` → Show shortcuts help
- `/` → Focus search
- `N` → New task (on tasks page)
- `T` + `T` → Toggle timer
- `N` + `N` → Open notifications
- `Esc` → Close modals

**Files:**
- `/assets/js/quick-actions.js`
- `/assets/css/quick-actions.css`
- `/includes/quick-actions-assets.php`

---

#### **13. Global Search**
**Location:** Header search box (all pages)

**Features:**
- Search across projects, tasks, users
- Real-time results
- Keyboard shortcut: `/`
- Highlighting matching text
- Quick navigation to results

**Files:**
- `/includes/global-search.php`
- `/assets/js/global-search.js`
- `/api/search.php`

---

#### **14. Bulk Operations**
**Location:** `/admin/bulk-operations.php`, `/admin/bulk-import.php`

**Bulk Operations Page:**
- Bulk add projects (pipe-delimited format)
- Bulk add team members (pipe-delimited format)
- Bulk delete projects (select multiple)
- Bulk status updates

**Format:**
```
Projects: Name|Client|Manager|Budget|Start Date|Due Date|Priority
Users: Username|Email|Full Name|Role|Job Title|Hourly Rate
```

**Bulk Import Page:**
- CSV import for users
- CSV import for tasks
- Download templates
- Error reporting for failed rows
- Default password for imported users: `welcome123`

**Templates:**
- `/templates/users-template.csv`
- `/templates/tasks-template.csv`

---

#### **15. Email System**
**Location:** `/admin/email-config.php`, `/admin/email-test.php`

**Features:**
- Configure SMTP settings
- Test email delivery
- Send notifications via email
- Task assignment emails
- Deadline reminder emails

**Configuration:**
- SMTP host, port
- Email username/password
- From address and name
- TLS/SSL encryption

**Functions:** `/includes/email-functions.php`

---

#### **16. Reports**
**Location:** `/admin/reports.php`, `/manager/reports.php`

**Report Types:**
- Daily/Weekly/Monthly reports
- Employee performance reports
- Project time breakdown
- Time variance analysis (estimated vs actual)
- Custom date range reports
- Export to print

**Data Shown:**
- Hours logged per employee
- Tasks completed
- Projects worked on
- Budget utilization
- Time distribution

---

## 📂 COMPLETE FILE STRUCTURE

### **Root Directory**
```
/Foxhole-new/
├── admin/                      # Admin dashboard pages
├── manager/                    # Manager dashboard pages
├── employee/                   # Employee dashboard pages
├── api/                        # REST API endpoints
├── assets/                     # CSS, JS, images
│   ├── css/
│   │   ├── vien-v3.css        # Main V3 design system
│   │   └── quick-actions.css
│   ├── js/
│   │   ├── main.js
│   │   ├── quick-actions.js
│   │   ├── global-search.js
│   │   └── theme.js
│   └── images/
├── config/                     # Configuration files
│   ├── config.php             # Site configuration
│   └── database.php           # Database connection
├── includes/                   # Reusable components
│   ├── functions.php          # Helper functions
│   ├── v3-header.php          # Header component
│   ├── v3-admin-sidebar.php   # Admin navigation
│   ├── v3-manager-sidebar.php # Manager navigation
│   ├── v3-employee-sidebar.php# Employee navigation
│   ├── notifications-dropdown.php
│   ├── file-upload-component.php
│   ├── task-dependencies-component.php
│   ├── global-search.php
│   └── ...
├── templates/                  # CSV templates
│   ├── users-template.csv
│   └── tasks-template.csv
├── uploads/                    # File storage
│   ├── projects/
│   ├── tasks/
│   └── comments/
├── backup-v2/                  # Old V2 system backup
├── sessions/                   # PHP session storage
├── logs/                       # Error logs
├── login.php                   # Login page
├── logout.php                  # Logout handler
├── setup.sql                   # Initial database schema
├── upgrade-schema.sql          # Feature upgrades
├── gamification-schema.sql    # Gamification tables
├── README.md                   # Project overview
└── [Multiple .md docs]         # Documentation
```

### **Admin Pages (16 files)**
```
/admin/
├── index.php                  # Dashboard ✅
├── projects.php               # All projects ✅
├── project-detail.php         # Single project view ✅
├── team.php                   # Team overview ✅
├── users.php                  # Add/edit users ✅
├── user-profile.php           # View user profile ✅
├── reports.php                # Reports generator ✅
├── analytics.php              # Charts dashboard ✅
├── advanced-analytics.php     # Performance analytics ✅
├── budget.php                 # Budget tracking ✅
├── calendar.php               # System calendar ✅
├── chat.php                   # Team chat ✅
├── bulk-operations.php        # Bulk add/delete ✅
├── bulk-import.php            # CSV import ✅
├── email-config.php           # Email settings ✅
├── email-test.php             # Test emails ✅
├── gamification.php           # ❌ MISSING (referenced in sidebar)
└── profile.php                # ❌ MISSING (referenced in sidebar)
```

### **Manager Pages (10 files)**
```
/manager/
├── index.php                  # Manager dashboard ✅
├── projects.php               # My projects ✅
├── create-project.php         # Request project ✅
├── tasks.php                  # All tasks ✅
├── create-task.php            # Create task ✅
├── team.php                   # My team ✅
├── manage-users.php           # Manage team ✅
├── reports.php                # Reports ✅
├── calendar.php               # Calendar ✅
└── chat.php                   # Chat ✅
```

### **Employee Pages (8 files)**
```
/employee/
├── index.php                  # Employee dashboard ✅
├── tasks.php                  # My tasks (list) ✅
├── tasks-premium.php          # My tasks (Kanban) ✅
├── create-task.php            # Create task ✅
├── time-logs.php              # Time tracking history ✅
├── my-stats.php               # Personal statistics ✅
├── calendar.php               # My calendar ✅
└── chat.php                   # Team chat ✅
```

### **API Endpoints (9 files)**
```
/api/
├── calendar.php               # Calendar events CRUD ✅
├── chat.php                   # Chat messages CRUD ✅
├── comments.php               # Task/project comments ✅
├── dependencies.php           # Task dependencies CRUD ✅
├── file-upload.php            # File upload/download/delete ✅
├── notifications.php          # Notifications CRUD ✅
├── search.php                 # Global search ✅
├── tasks.php                  # Tasks API ✅
└── time-tracking.php          # Start/stop timers ✅
```

### **Database Schema Files**
```
├── setup.sql                  # Initial schema (users, projects, tasks, time_logs)
├── upgrade-schema.sql         # Advanced features (chat, notifications, files, etc.)
└── gamification-schema.sql    # Gamification (badges, points, leaderboard)
```

---

## 🗄️ DATABASE SCHEMA

### **Core Tables (6 tables - setup.sql)**
1. **users** - Team members with roles
2. **projects** - Client projects
3. **tasks** - Work items
4. **time_logs** - Time tracking entries
5. **project_comments** - Project updates
6. **task_comments** - Task discussions

### **Feature Tables (12 tables - upgrade-schema.sql)**
7. **file_uploads** - File attachments
8. **notifications** - User notifications
9. **chat_messages** - Team chat
10. **task_dependencies** - Task dependencies
11. **activity_log** - Action history
12. **user_preferences** - User settings
13. **project_milestones** - Calendar milestones
14. **expenses** - Direct project expenses
15. **saved_filters** - Quick filters

### **Gamification Tables (5 tables - gamification-schema.sql)**
16. **user_points** - Points and streaks
17. **badges** - Badge definitions
18. **user_badges** - Earned badges
19. **point_transactions** - Points history
20. **performance_metrics** - Daily performance data

**Total Tables:** 20

---

## 🎮 USER WORKFLOWS

### **Employee Daily Workflow:**

**Morning (9:00 AM):**
1. Login to Foxhole
2. Go to Dashboard (`/employee/index.php`)
3. See 8 assigned tasks
4. Check "Currently Working On" section (empty)
5. View task cards with priorities and deadlines

**Start Work (9:15 AM):**
1. Click "▶ Start" on "Design social media graphics" task
2. Timer starts: 00:00:01... (counts up every second)
3. Task automatically moves to "In Progress" status
4. Dashboard shows: "Currently Working On: Design social media graphics (01:23:45)"

**Work Session (9:15 AM - 11:45 AM):**
- Employee works on task (2.5 hours)
- Timer continues running in background
- Can start additional timers on other tasks if needed

**End Work (11:45 AM):**
1. Click "⏹ Stop Timer" button
2. Popup: "Add notes about your work (optional)"
3. Enter: "Completed 5 graphic variations, awaiting feedback"
4. Click "Save"
5. System calculates: 2h 30m = 150 minutes
6. Updates `tasks.actual_hours` += 2.5
7. Updates `projects.actual_hours` += 2.5
8. Creates entry in `time_logs` table

**Move Task Forward:**
1. Go to Kanban board (`/employee/tasks-premium.php`)
2. Drag "Design social media graphics" from "In Progress" to "Review" column
3. Task status changes to 'review'
4. Notification sent to project manager

**Check Stats:**
1. Go to "My Statistics" (`/employee/my-stats.php`)
2. See total points: 245 pts
3. See earned badges: "First Steps" 🎯, "Getting Started" ⭐, "Speed Demon" ⚡
4. Current streak: 5 days
5. Completion rate: 87% (23/24 tasks on time)

---

### **Manager Daily Workflow:**

**Morning (9:00 AM):**
1. Login to Foxhole
2. Dashboard shows:
   - 3 active projects
   - 15 tasks in progress
   - 8 team members working
   - Budget utilization: 65%

**Review Work:**
1. Notification: "Sarah moved task to Review"
2. Click notification → Goes to task
3. Review Sarah's work (5 graphic variations)
4. Add comment: "Looks great! Go with Option 3"
5. Move task to "Completed"
6. System awards Sarah:
   - 20 points (medium priority)
   - +10 bonus (1 day early)
   - Progress toward "Task Master" badge (24/25)

**Create New Task:**
1. Go to Projects → Website Redesign project
2. Click "Create Task"
3. Fill in:
   - Name: "Homepage wireframe"
   - Assign to: Mike
   - Priority: High
   - Estimated hours: 8
   - Due date: November 10, 2025
4. Click "Create Task"
5. System sends notification to Mike
6. Mike sees new task in his dashboard

**Check Team Performance:**
1. Go to "My Team" (`/manager/team.php`)
2. See all 8 team members
3. View hours logged this week
4. Check productivity scores
5. Notice Tom has 3 overdue tasks
6. Send message via chat: "Tom, need help with those tasks?"

---

### **Admin Daily Workflow:**

**Morning (9:00 AM):**
1. Login to Foxhole
2. Dashboard shows:
   - 12 active projects
   - 47 tasks in progress
   - 15 team members
   - Total budget: ₹2,400,000
   - Actual cost: ₹1,850,000 (77% utilized)

**Review Analytics:**
1. Go to Advanced Analytics (`/admin/advanced-analytics.php`)
2. **Leaderboard:**
   - 🥇 #1 Sarah (93) - Excellent
   - 🥈 #2 Mike (91) - Excellent
   - 🥉 #3 Lisa (88) - Good
   - ...
   - #15 Tom (58) - Needs Improvement ⚠️
3. **Bottleneck Detection:**
   - Tom flagged: 4 overdue tasks, 45% completion rate
4. **WHO IS SLOW:**
   - Tom: Avg 48 hours per task (2x slower than team avg)
5. **WHO IS QUICK:**
   - Sarah: Avg 15 hours per task (completes 85% early)

**Address Issues:**
1. Open chat with Tom
2. Message: "Tom, I see you're struggling with deadlines. Let's schedule a 1-on-1 to discuss"
3. Check Tom's current tasks
4. Reassign 2 urgent tasks to Mike
5. Reduce Tom's workload

**Budget Review:**
1. Go to Budget (`/admin/budget.php`)
2. **Project: Website Redesign**
   - Budget: ₹200,000
   - Labor: ₹112,800
   - Expenses: ₹5,000
   - Total: ₹117,800
   - Variance: +₹82,200 (Under budget ✅)
   - Utilization: 59%
3. **Project: Mobile App**
   - Budget: ₹150,000
   - Actual: ₹178,000
   - Variance: -₹28,000 (Over budget ⚠️)
   - Action: Notify manager to reduce scope

**Add New Team Member:**
1. Go to Team Management (`/admin/users.php`)
2. Click "Add New User"
3. Fill in:
   - Username: emily_dev
   - Email: emily@neofox.com
   - Full Name: Emily Chen
   - Role: Employee
   - Job Title: Frontend Developer
   - Hourly Rate: ₹650
4. Click "Create User"
5. System generates password
6. Send credentials to Emily

---

## 🔍 HOW TO ACCESS SPECIFIC FEATURES

### **"Where is the Pomodoro timer?"**
**Answer:** There is NO Pomodoro timer. Only regular time tracking.

**How to track time:**
1. Go to Employee Dashboard
2. Find task card
3. Click "▶ Start" button
4. Timer starts and counts up
5. Work on task
6. Click "⏹ Stop Timer" when done
7. Add notes (optional)
8. Done!

---

### **"Where are Amazing Marvin features?"**
**Answer:** There are NO Amazing Marvin features in this system.

**What exists instead:**
- Regular time tracking (start/stop)
- Task management
- Gamification (badges, points)
- Calendar view
- Notifications

---

### **"Where is the leaderboard?"**
**Answer:** Partially implemented.

**Where it works:**
- Advanced Analytics page (`/admin/advanced-analytics.php`) - Shows leaderboard with productivity scores
- User Profile page (`/admin/user-profile.php`) - Shows individual badges and points
- **MISSING:** Dedicated gamification page (`admin/gamification.php`)

**To view leaderboard:**
1. Login as Admin
2. Go to "Analytics" in sidebar
3. Click "Advanced Analytics"
4. Scroll to "Team Leaderboard" section
5. See ranked list with medals

---

### **"How do I see my badges?"**
**Employee:**
1. Go to "My Statistics" (`/employee/my-stats.php`)
2. Scroll to "Achievements" section
3. See all earned badges with descriptions

**Admin viewing others:**
1. Go to "Team Management"
2. Click on employee name
3. Goes to `/admin/user-profile.php?id=X`
4. See badges, points, stats

---

### **"How do notifications work?"**
1. Bell icon (🔔) in top-right of every page
2. Shows red badge with unread count
3. Click bell → Dropdown opens
4. See list of notifications with icons
5. Click notification → Goes to related item
6. Notifications auto-refresh every 30 seconds
7. Click "Mark all read" to clear

**Notification triggers:**
- Task assigned to you
- Comment on your task
- Deadline approaching (3 days, 1 day, overdue)
- Project status changed
- Badge unlocked

---

### **"How do I attach files to tasks?"**
1. Go to task detail page
2. Scroll to "File Attachments" section
3. Click "Choose File" button
4. Select file (max 10MB)
5. Click "Upload"
6. File appears in list with download button
7. Can delete own files

---

### **"How do I see budget vs actual cost?"**
**Admin only:**
1. Go to "Budget & Costs" in sidebar (`/admin/budget.php`)
2. See summary cards:
   - Total Budget Allocated
   - Actual Cost to Date
   - Direct Expenses
   - Variance (over/under)
3. Scroll down for per-project breakdown
4. Each project shows:
   - Budget bar (green/yellow/red based on utilization)
   - Labor cost
   - Expenses
   - Variance (₹ and %)

---

### **"How do I use keyboard shortcuts?"**
1. Press `?` key anywhere
2. Modal opens showing all shortcuts
3. Or press `Ctrl+K` to open command palette
4. Type command or navigate with arrows
5. Common shortcuts:
   - `G` + `D` = Dashboard
   - `T` + `T` = Toggle timer
   - `N` + `N` = Notifications
   - `/` = Search

---

## 🐛 BUGS & ISSUES FOUND

### **Critical Issues:**

1. **Missing Page: admin/gamification.php**
   - **Severity:** High
   - **Impact:** Broken link in admin sidebar
   - **Location:** Sidebar references lines 35-38, 111-123
   - **Fix:** Create gamification.php with leaderboard display

2. **Missing Page: admin/profile.php**
   - **Severity:** Medium
   - **Impact:** Broken link in admin settings
   - **Location:** Sidebar line 148
   - **Fix:** Create profile.php OR update sidebar to link to user-profile.php

3. **Placeholder Code: Notifications**
   - **Severity:** High
   - **Impact:** Users see "coming soon" alert when clicking bell icon
   - **Location:** includes/v3-header.php line 103
   - **Current:** `alert('Notifications feature - coming soon!');`
   - **Reality:** Notifications ARE fully implemented
   - **Fix:** Remove placeholder function, ensure notifications-dropdown.php is included properly

### **Minor Issues:**

4. **README.md Outdated**
   - **Severity:** Low
   - **Impact:** Documentation doesn't reflect V3 conversion
   - **Content:** Still mentions old V1/V2 structure
   - **Fix:** Update README to reflect current V3 state

5. **Duplicate Code in Backup Folder**
   - **Severity:** Low
   - **Impact:** Confusion, wasted space
   - **Location:** `/backup-v2/` contains old code
   - **Fix:** Remove backup folder or clearly mark as archived

---

## ✅ VERIFICATION CHECKLIST

### **Features That Work 100%:**
- ✅ Login/logout system
- ✅ User management (add/edit/delete)
- ✅ Project management (CRUD)
- ✅ Task management (CRUD)
- ✅ Time tracking (start/stop/log)
- ✅ Budget tracking (budget vs actual)
- ✅ Team chat (real-time messaging)
- ✅ Calendar view (FullCalendar.js)
- ✅ Notifications system (dropdown works)
- ✅ File uploads (attach to tasks/projects)
- ✅ Task dependencies (link tasks)
- ✅ Analytics charts (Chart.js)
- ✅ Advanced analytics (bottleneck detection)
- ✅ Reports generation
- ✅ Bulk operations
- ✅ Bulk CSV import
- ✅ Email configuration
- ✅ Global search
- ✅ Keyboard shortcuts
- ✅ User profiles (view badges/points)
- ✅ Employee statistics page

### **Features Partially Working:**
- ⚠️ Gamification: Database exists, user profiles show badges, but NO leaderboard page
- ⚠️ Notifications: Fully implemented but header has "coming soon" placeholder

### **Features Missing:**
- ❌ Gamification leaderboard page (admin/gamification.php)
- ❌ Profile settings page (admin/profile.php)
- ❌ Amazing Marvin features (NEVER existed)
- ❌ Pomodoro timer (NEVER existed)

---

## 📝 RECOMMENDATIONS

### **Immediate Actions Required:**

1. **Create admin/gamification.php**
   - Display leaderboard from advanced-analytics.php
   - Show all badges with criteria
   - List top performers
   - Award new badges manually

2. **Fix Notifications Button**
   - Remove placeholder `alert()` from v3-header.php
   - Ensure notifications-dropdown.php is properly included
   - Test bell icon click → should open dropdown, not alert

3. **Create admin/profile.php OR Update Sidebar**
   - Option A: Create new profile.php for logged-in user settings
   - Option B: Update sidebar to link to existing user-profile.php

4. **Update Documentation**
   - Clarify NO Pomodoro/Amazing Marvin features
   - Update README.md to reflect V3 state
   - Add this document as primary reference

---

## 🎯 FEATURE PROTECTION GUIDELINES

### **To Prevent Overwriting Features:**

1. **Always check this document before adding features**
2. **Search codebase for existing functionality before coding new**
3. **Review all related files:**
   - Check API endpoints (`/api/*.php`)
   - Check database schema (`setup.sql`, `upgrade-schema.sql`)
   - Check components (`/includes/*.php`)
   - Check all 3 role dashboards (admin/manager/employee)

4. **Before modifying:**
   - Read file completely
   - Check for dependencies
   - Test in all 3 user roles
   - Verify database changes

5. **Use version control:**
   - Commit before major changes
   - Create feature branches
   - Test thoroughly before merging

---

## 📞 SUPPORT INFORMATION

### **Common Questions:**

**Q: Where is Pomodoro timer?**
A: Doesn't exist. Use regular time tracking (start/stop buttons).

**Q: Where are Amazing Marvin features?**
A: Don't exist. This is a custom productivity platform.

**Q: How do I see who's slow?**
A: Admin → Advanced Analytics → Bottleneck Detection section

**Q: How do I earn badges?**
A: Complete tasks early, maintain streaks, complete many tasks. Badges auto-award.

**Q: Can employees see the leaderboard?**
A: Currently NO dedicated page. Only admin sees in Advanced Analytics.

**Q: Can I customize point values?**
A: Yes, edit gamification logic in PHP (needs code changes).

---

## 📊 PROJECT STATISTICS

- **Total PHP Files:** 74 (excluding backup folder)
- **Total Pages:**
  - Admin: 16 pages (2 missing)
  - Manager: 10 pages
  - Employee: 8 pages
- **Total API Endpoints:** 9
- **Total Database Tables:** 20
- **Total Features:** 16 major features
- **Lines of Code:** ~15,000+ (estimated)
- **Design System:** Vien V3
- **External Dependencies:** 2 (FullCalendar, Chart.js)

---

## 🔐 DEFAULT LOGIN CREDENTIALS

**Admin:**
- Username: `admin`
- Password: `admin123`

**Manager:**
- Username: `john_manager`
- Password: `admin123`

**Employee:**
- Username: `sarah_employee`
- Password: `admin123`

**⚠️ Change these passwords in production!**

---

## 📅 VERSION HISTORY

**v2.2.0 (Current)**
- V3 design conversion complete
- All features functional
- Missing: gamification page, profile page
- Issue: Notifications placeholder

**v2.1.0**
- Added gamification system
- Added advanced analytics
- Added bulk operations

**v2.0.0**
- Added chat, calendar, notifications
- Added file uploads, task dependencies
- Upgraded database schema

**v1.0.0**
- Initial release
- Core features: projects, tasks, time tracking

---

## 🎉 CONCLUSION

Foxhole is a **comprehensive productivity management platform** with:
- ✅ 16 major features
- ✅ 34 working pages
- ✅ 20 database tables
- ✅ 9 API endpoints
- ✅ 3 user roles with proper permissions
- ⚠️ 3 issues to fix (gamification page, profile page, notifications alert)
- ❌ NO Pomodoro or Amazing Marvin features

**This document is the definitive reference for all features. Refer to it before making ANY changes to prevent overwrites.**

---

**Document Created:** November 3, 2025
**Last Updated:** November 3, 2025
**Author:** Lead Developer
**Status:** COMPLETE PROJECT AUDIT ✅
