# Foxhole - Deployment Guide

## Latest Updates Summary

This deployment includes critical bug fixes and premium features to make Foxhole a complete $500-level productivity platform.

---

## 🔧 Critical Bug Fixes

### 1. Logout Issue - FIXED ✅
**Problem:** Logout page kept refreshing indefinitely
**Solution:** Improved session handling and buffer clearing in `logout.php`
**Files Changed:** `logout.php`

### 2. Project Name Not Showing - FIXED ✅
**Problem:** "Currently Working On" didn't show project name
**Solution:** Updated `getActiveTimeLog()` function to JOIN with projects table
**Files Changed:** `includes/functions.php`

### 3. Multiple Parallel Timers - ENABLED ✅
**Problem:** Users couldn't work on multiple tasks simultaneously
**Solution:** Removed global timer restriction, now allows multiple timers per user
**Files Changed:** `api/time-tracking.php`

### 4. Inconsistent Navigation - FIXED ✅
**Problem:** Different navigation on each employee page
**Solution:** Created standardized sidebar includes
**Files Changed:** Created `includes/admin-sidebar.php`, `includes/employee-sidebar.php`

---

## 🎨 Premium Features Added

### 1. Global Search (Cmd/Ctrl + K)
**Feature:** Search across all projects, tasks, and people
**Files:**
- `api/search.php` - Search API endpoint
- `includes/global-search.php` - Search modal component
- `assets/js/global-search.js` - Search functionality with keyboard shortcuts
- `includes/global-search-assets.php` - Include file for easy integration

**How to Use:**
- Press `Cmd+K` (Mac) or `Ctrl+K` (Windows) anywhere in the app
- Or click the search button in the topbar
- Start typing to search

### 2. User Profile Pages with Gamification
**Feature:** Complete user profiles showing stats, achievements, badges, and points
**Files:**
- `admin/user-profile.php` - User profile page

**Features:**
- Total points and streak tracking
- Earned badges display
- Task completion statistics
- Time tracking stats
- Early completion bonuses
- Project history
- Recent achievements

### 3. Comprehensive Project Detail Page
**Feature:** Detailed project overview with all information
**Files:**
- `admin/project-detail.php` - Project detail page

**Features:**
- Project status and priority
- Progress tracking
- Budget usage visualization
- Team member assignments
- All tasks list
- Recent activity
- Overdue task alerts

### 4. Bulk Operations
**Feature:** Bulk add/remove projects and team members
**Files:**
- `admin/bulk-operations.php` - Bulk operations interface

**Features:**
- Bulk add projects (pipe-delimited format)
- Bulk add team members (pipe-delimited format)
- Bulk remove projects with confirmation
- Select all/deselect all functionality

---

## 📊 Database Updates Required

### **IMPORTANT: You MUST run these SQL updates!**

The gamification system requires new database tables. Run the following SQL file:

**File:** `gamification-schema.sql`

This creates:
1. `user_points` - Points, streaks, and completion stats
2. `badges` - Achievement badges (10 pre-configured badges)
3. `user_badges` - User-earned badges tracking
4. `point_transactions` - Points history
5. `performance_metrics` - Employee performance tracking

### How to Run:

#### Option 1: cPanel (Recommended)
1. Log into cPanel
2. Go to phpMyAdmin
3. Select your database
4. Click "SQL" tab
5. Copy the entire contents of `gamification-schema.sql`
6. Paste and click "Go"

#### Option 2: Command Line
```bash
mysql -u your_username -p your_database < gamification-schema.sql
```

#### Option 3: Import File
1. In phpMyAdmin, click "Import"
2. Choose `gamification-schema.sql`
3. Click "Go"

---

## 🚀 Deployment Checklist

### Before Deploying:
- [ ] Backup your current database
- [ ] Backup your current files
- [ ] Note your database credentials

### Deploy Steps:
1. [ ] Upload all changed files to server
2. [ ] Run `gamification-schema.sql` in database
3. [ ] Clear browser cache
4. [ ] Test login/logout
5. [ ] Test timer functionality
6. [ ] Test global search (Cmd/Ctrl+K)
7. [ ] Check user profile pages
8. [ ] Verify bulk operations work

### Testing Checklist:
- [ ] Admin can log in and log out
- [ ] Employee can log in and log out
- [ ] Multiple timers can run simultaneously
- [ ] Project names show in "Currently Working On"
- [ ] Navigation is consistent across all pages
- [ ] Global search works (try pressing Cmd/Ctrl+K)
- [ ] User profiles display correctly
- [ ] Project detail pages load
- [ ] Bulk operations function properly

---

## 🎯 New Features Documentation

### Global Search
```
Keyboard Shortcut: Cmd+K (Mac) or Ctrl+K (Windows)
Searches: Projects, Tasks, Team Members
Speed: Instant results with 300ms debounce
```

### Gamification System
```
Points System:
- Task completion: Variable points based on priority
- Early completion: Bonus points
- Streak tracking: Daily activity streaks

Badges (10 types):
- First Steps (Bronze) - Complete 1 task
- Task Master (Silver) - Complete 10 tasks
- Speed Demon (Silver) - Complete 5 tasks early
- Super Achiever (Gold) - Complete 50 tasks
- Early Bird (Gold) - Complete 20 tasks before deadline
- Perfect Week (Gold) - 7-day streak
- Century Club (Platinum) - Complete 100 tasks
- Legendary (Platinum) - 30-day streak
- Quick Finisher (Platinum) - Complete 50 tasks early
- Quality Expert (Platinum) - Maintain 95% completion rate
```

### Multiple Timers
```
Users can now:
- Start multiple timers for different tasks
- Work on parallel projects simultaneously
- Each task maintains its own independent timer
- Prevents duplicate timers for the same task
```

---

## 📁 New Files Added

### API Files
- `api/search.php` - Global search API

### Include Files
- `includes/admin-sidebar.php` - Standardized admin navigation
- `includes/employee-sidebar.php` - Standardized employee navigation
- `includes/global-search.php` - Search modal HTML/CSS
- `includes/global-search-assets.php` - Search integration helper

### JavaScript Files
- `assets/js/global-search.js` - Search functionality

### Admin Pages
- `admin/bulk-operations.php` - Bulk operations interface
- `admin/user-profile.php` - User profile with gamification
- `admin/project-detail.php` - Comprehensive project details

### Database
- `gamification-schema.sql` - Gamification database schema

---

## 🐛 Known Issues (None Currently)

All reported issues have been fixed in this release.

---

## 🔄 Future Enhancements (Optional)

These were planned but can be added later if needed:
- [ ] Activity feed/timeline component
- [ ] Export to CSV/PDF capabilities
- [ ] Advanced filter system
- [ ] Real-time notifications with WebSocket
- [ ] Task comments with @mentions
- [ ] File attachments for tasks

---

## 📞 Support

If you encounter any issues during deployment:
1. Check database connection in `config/database.php`
2. Verify all SQL updates were applied successfully
3. Clear browser cache and cookies
4. Check PHP error logs
5. Ensure file permissions are correct (644 for files, 755 for directories)

---

## ✅ Verification Commands

### Check Database Tables
```sql
SHOW TABLES LIKE 'user_points';
SHOW TABLES LIKE 'badges';
SHOW TABLES LIKE 'user_badges';
SHOW TABLES LIKE 'point_transactions';
SHOW TABLES LIKE 'performance_metrics';
```

All 5 tables should exist after running the gamification schema.

### Check for Errors
```bash
# In browser console (F12):
- No JavaScript errors
- Search modal opens with Cmd/Ctrl+K
- All images/CSS load correctly
```

---

**Version:** 2.0.0
**Release Date:** 2025-10-27
**Codename:** Premium Edition
