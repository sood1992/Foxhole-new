# Quick Installation Guide

## Prerequisites Checklist
- [ ] PHP 7.4 or higher installed
- [ ] MySQL 5.7 or higher installed
- [ ] Web server (Apache) running
- [ ] Access to cPanel or server terminal

## Installation Steps

### 1. Database Setup (5 minutes)

**Via cPanel:**
1. Login to cPanel
2. Go to "MySQL Databases"
3. Create new database: `productivity_platform`
4. Create new user with strong password
5. Add user to database with ALL PRIVILEGES
6. Go to phpMyAdmin
7. Select your database
8. Click "Import" tab
9. Upload `setup.sql` file
10. Click "Go"

**Via Command Line:**
```bash
mysql -u root -p
CREATE DATABASE productivity_platform;
CREATE USER 'prod_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON productivity_platform.* TO 'prod_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

mysql -u prod_user -p productivity_platform < setup.sql
```

### 2. Configure Database Connection (2 minutes)

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'productivity_platform');
define('DB_USER', 'prod_user');
define('DB_PASS', 'your_secure_password');
```

### 3. Update Site URL (1 minute)

Edit `config/config.php`:
```php
define('SITE_URL', 'https://yourdomain.com');
```

### 4. Set File Permissions (1 minute)

```bash
chmod 755 /path/to/productivity/
chmod 644 /path/to/productivity/config/*.php
```

### 5. Test Installation (2 minutes)

1. Open your browser
2. Navigate to: `https://yourdomain.com`
3. You should see the login page

### 6. First Login (1 minute)

Use default admin credentials:
- Username: `admin`
- Password: `admin123`

**IMPORTANT: Change password immediately after login!**

### 7. Security Hardening (2 minutes)

1. Change all default passwords
2. Delete or restrict `setup.sql` access
3. Update `.htaccess` if needed
4. Enable HTTPS (recommended)

## Default User Accounts

| Role | Username | Password | Email |
|------|----------|----------|-------|
| Admin | admin | admin123 | admin@neofox.com |
| Manager | john_manager | admin123 | john@neofox.com |
| Employee | sarah_employee | admin123 | sarah@neofox.com |

**Change ALL passwords immediately!**

## Verification Checklist

After installation, verify:
- [ ] Can login as admin
- [ ] Can see dashboard with statistics
- [ ] Can navigate to different sections
- [ ] No PHP errors displayed
- [ ] Database connections working
- [ ] Time tracking buttons visible

## Troubleshooting

### "Database connection failed"
- Check database credentials in `config/database.php`
- Verify MySQL is running
- Confirm database exists

### "Page not found" errors
- Check file permissions
- Verify .htaccess is uploaded
- Check Apache mod_rewrite is enabled

### Login redirects to login page
- Clear browser cookies
- Check session directory permissions
- Verify PHP sessions are enabled

### Blank white screen
- Enable PHP error display temporarily
- Check PHP error logs
- Verify all files uploaded correctly

## Need Help?

1. Check `README.md` for detailed documentation
2. Review error logs in cPanel
3. Check browser console for JavaScript errors
4. Verify all files are uploaded

## Next Steps

After successful installation:
1. Change all default passwords
2. Add your team members via Admin > User Settings
3. Create your first project
4. Assign tasks to team members
5. Start tracking time!

---

**Installation should take approximately 15 minutes**
