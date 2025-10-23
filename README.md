# Neofox Productivity Management Platform

A comprehensive, ADHD-friendly productivity management platform designed specifically for creative and marketing agencies. Features real-time time tracking, project management, and detailed reporting capabilities.

## Features

### Core Functionality
- **3-Tier Role-Based Access Control**
  - Admin: Full system access, team management, comprehensive reports
  - Project Manager: Project oversight, task assignment, team monitoring
  - Employee: Task management, time tracking, personal statistics

- **Advanced Time Tracking**
  - Real-time timer with start/stop functionality
  - Automatic calculation of task and project hours
  - Daily, weekly, and monthly time logs
  - Visual time distribution across projects

- **Comprehensive Reporting**
  - Weekly, monthly, and custom date range reports
  - Employee-wise performance analytics
  - Project time breakdown and variance analysis
  - Detailed time log exports

- **Project & Task Management**
  - Multi-level project organization
  - Task assignment and tracking
  - Priority and status management
  - Progress visualization
  - Comments and updates system

- **ADHD-Friendly UI**
  - Clean, minimal design with high contrast
  - Color-coded status indicators
  - Large, easy-to-read numbers and stats
  - Visual progress bars
  - Reduced cognitive load through simplified navigation

## Technology Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Server**: Apache 2.4+ (cPanel compatible)
- **Dependencies**: None (completely self-contained)

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite enabled
- cPanel or local development environment

### Step 1: Upload Files

Upload all files to your web server (e.g., `/public_html/productivity/`)

### Step 2: Create Database

1. Log into cPanel > MySQL Databases
2. Create a new database (e.g., `productivity_platform`)
3. Create a database user with a strong password
4. Add the user to the database with ALL PRIVILEGES

### Step 3: Configure Database Connection

Edit `/config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

### Step 4: Import Database Schema

**Option A: Using phpMyAdmin**
1. Open phpMyAdmin from cPanel
2. Select your database
3. Click "Import" tab
4. Choose `setup.sql` file
5. Click "Go"

**Option B: Using MySQL Command Line**
```bash
mysql -u your_username -p your_database_name < setup.sql
```

### Step 5: Update Site Configuration

Edit `/config/config.php`:

```php
define('SITE_URL', 'https://yourdomain.com/productivity');
```

### Step 6: Set Permissions

Ensure the following directories are writable (755):
```bash
chmod 755 /path/to/productivity/
chmod 755 /path/to/productivity/assets/
```

### Step 7: Security

After installation:
1. Remove or restrict access to `setup.sql`
2. Change default admin password immediately
3. Update `.htaccess` file if needed for your server configuration

## Default Login Credentials

**IMPORTANT: Change these passwords immediately after first login!**

- **Admin**
  - Username: `admin`
  - Password: `admin123`

- **Project Manager**
  - Username: `john_manager`
  - Password: `admin123`

- **Employee**
  - Username: `sarah_employee`
  - Password: `admin123`

## Usage Guide

### For Administrators

1. **Dashboard**: View team overview and key metrics
2. **Reports**: Generate detailed reports
   - Select time period (daily, weekly, monthly, custom)
   - Filter by employee
   - Export or print reports
3. **Team Management**: Add/edit team members
4. **Projects**: Create and manage projects
5. **User Settings**: Configure user accounts and permissions

### For Project Managers

1. **Dashboard**: Monitor assigned projects and team activity
2. **My Projects**: View and manage your projects
3. **Task Management**: Create and assign tasks
4. **Team Activity**: Track team member progress
5. **Reports**: View project-specific reports

### For Employees

1. **Dashboard**: View assigned tasks and statistics
2. **Time Tracking**:
   - Click "Start" on any task to begin tracking
   - Timer runs in real-time
   - Click "Stop Timer" when done
   - Add optional notes about your work
3. **My Tasks**: View all assigned tasks
4. **Time Logs**: Review your time entries
5. **My Statistics**: View personal performance metrics

## Features in Detail

### Time Tracking System

The platform includes a sophisticated time tracking system:

- **Start/Stop Timer**: One-click time tracking per task
- **Real-time Updates**: Live timer display with hours:minutes:seconds
- **Automatic Calculations**: Hours automatically added to tasks and projects
- **Session Notes**: Add notes to each work session
- **Prevent Overlap**: Only one timer can run at a time
- **Visual Feedback**: Clear indication of active timers

### Reporting Capabilities

Comprehensive reporting includes:

- **Employee Performance**: Hours logged, tasks completed, projects worked
- **Project Time Breakdown**: Time spent per project with variance analysis
- **Detailed Time Logs**: Every work session with start/end times
- **Custom Filters**: Date ranges, employee selection
- **Print Support**: Optimized for printing reports

### ADHD-Friendly Design

Special attention to cognitive accessibility:

- **Color Coding**: Consistent color scheme for statuses and priorities
  - Blue: In Progress
  - Green: Completed
  - Orange: Review/High Priority
  - Red: Blocked/Urgent
  - Gray: Todo/Low Priority

- **Visual Hierarchy**: Large numbers, clear labels, minimal clutter
- **High Contrast**: Easy-to-read text and backgrounds
- **Progress Indicators**: Visual bars for quick understanding
- **Status Badges**: Immediate visual status recognition

## Database Structure

### Main Tables
- `users`: User accounts with role-based access
- `projects`: Project information and tracking
- `tasks`: Individual tasks assigned to users
- `time_logs`: Detailed time tracking entries
- `project_comments`: Project updates and comments
- `task_comments`: Task-specific comments and updates

## Security Features

- Password hashing with PHP's password_hash()
- SQL injection prevention via PDO prepared statements
- XSS protection with htmlspecialchars()
- Session-based authentication
- Role-based access control
- HTTPS recommended for production

## Browser Compatibility

- Chrome/Edge (Recommended)
- Firefox
- Safari
- Opera
- IE11+ (Limited support)

## Troubleshooting

### Database Connection Errors
- Verify database credentials in `/config/database.php`
- Check if MySQL service is running
- Ensure database user has proper permissions

### Login Issues
- Clear browser cache and cookies
- Verify session support is enabled in PHP
- Check file permissions on session directory

### Time Tracking Not Working
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify API endpoints are accessible

### Missing Data in Reports
- Confirm date filters are correct
- Check if employees have logged time
- Verify timezone settings in `/config/config.php`

## Customization

### Adding New Team Members

1. Login as Admin
2. Go to User Settings
3. Click "Add New User"
4. Fill in details and select role
5. Provide credentials to the new user

### Creating Projects

1. Login as Admin or Project Manager
2. Navigate to Projects
3. Click "New Project"
4. Fill in project details:
   - Project name
   - Client name
   - Due date
   - Estimated hours
   - Priority level
5. Assign project manager
6. Create tasks within the project

### Customizing Colors

Edit `/assets/css/style.css` and modify the `:root` variables:

```css
:root {
    --primary: #4F46E5;
    --status-completed: #10B981;
    /* etc... */
}
```

## Backup

### Database Backup
```bash
mysqldump -u username -p database_name > backup.sql
```

### File Backup
- Backup entire project directory
- Include config files
- Store securely off-server

## Support

For issues or questions:
1. Check the Troubleshooting section
2. Review error logs in cPanel
3. Check browser console for JavaScript errors

## License

Proprietary - Neofox Media

## Credits

Developed for Neofox Media - Creative & Marketing Agency
Designed with ADHD-friendly principles for optimal user experience

## Version History

**v1.0.0** - Initial Release
- Core functionality complete
- 3-tier role system
- Time tracking
- Project management
- Comprehensive reporting
- ADHD-friendly UI

---

**Built with care for productivity and accessibility**
