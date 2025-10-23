# Post-Deployment Configuration Steps

After cPanel deploys your code, you need to complete these steps:

## 1. Configure Database Connection

Edit the deployed file at:
```
/home2/sunburni/public_html/neofoxmedia/foxhole/tests/v1/config/database.php
```

Update with your cPanel database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sunburni_productivity');  // Your database name
define('DB_USER', 'sunburni_dbuser');        // Your database user
define('DB_PASS', 'your_password_here');     // Your database password
```

## 2. Create Database (If Not Already Done)

Via cPanel:
1. Go to "MySQL Databases"
2. Create database: `sunburni_productivity` (or your preferred name)
3. Create user with strong password
4. Add user to database with ALL PRIVILEGES

## 3. Import Database Schema

Via phpMyAdmin:
1. Select your database
2. Click "Import" tab
3. Upload `setup.sql` from your deployed files
4. Click "Go"

The file is located at:
```
/home2/sunburni/public_html/neofoxmedia/foxhole/tests/v1/setup.sql
```

## 4. Update Site URL (Optional)

Edit `/config/config.php` and update:
```php
define('SITE_URL', 'https://neofoxmedia.com/foxhole/tests/v1');
```

## 5. Verify Sessions Directory

The deployment should create this automatically, but verify:
```
/home2/sunburni/public_html/neofoxmedia/foxhole/tests/v1/sessions/
```

Permissions should be: 755

## 6. Test Your Installation

Visit: https://neofoxmedia.com/foxhole/tests/v1/login.php

Login with:
- Username: `admin`
- Password: `admin123`

**Important: Change password immediately after first login!**

## 7. Security After Deployment

1. Change all default passwords
2. Remove or restrict access to:
   - `debug.php`
   - `login-debug.php`
   - `setup.sql`

3. Turn off error display in `login.php` (remove first 3 lines after testing)

## Troubleshooting After Deployment

If you see errors after deployment:

1. **Session errors**: Verify `/sessions/` directory exists and is writable (755)
2. **Database errors**: Check credentials in `/config/database.php`
3. **Blank page**: Access `/debug.php` to see diagnostics
4. **Permission errors**: Check file permissions (files: 644, directories: 755)

## File Permissions Summary

After deployment, verify:
- Directories: 755
- PHP files: 644
- .htaccess: 644
- sessions/ directory: 755 (writable)

## Need Help?

Check these files in your deployment:
- `README.md` - Complete documentation
- `INSTALL.md` - Installation guide
- `TROUBLESHOOTING.md` - Common issues and solutions
- `debug.php` - System diagnostics
