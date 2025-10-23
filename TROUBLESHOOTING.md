# Troubleshooting Guide

## Issue: Blank Page on Login

If you're seeing a blank page, follow these steps:

### Step 1: Enable Error Display

Upload `debug.php` to your server and access it:
```
https://neofoxmedia.com/foxhole/tests/v1/debug.php
```

This will show you:
- PHP version
- Missing files
- Database connection status
- Session support

### Step 2: Test Login with Debug Mode

Access the debug login page:
```
https://neofoxmedia.com/foxhole/tests/v1/login-debug.php
```

This shows detailed error messages.

### Common Issues and Solutions

#### Issue 1: Database Not Connected

**Symptoms:** "Database connection failed" in debug.php

**Solution:**
1. Edit `config/database.php`
2. Update with your cPanel database credentials:

```php
define('DB_HOST', 'localhost');  // Usually 'localhost'
define('DB_NAME', 'your_cpanel_db_name');
define('DB_USER', 'your_cpanel_db_user');
define('DB_PASS', 'your_cpanel_db_password');
```

#### Issue 2: Database Not Imported

**Symptoms:** "users table does NOT exist"

**Solution:**
1. Login to cPanel
2. Open phpMyAdmin
3. Select your database
4. Click "Import"
5. Upload `setup.sql`
6. Click "Go"

#### Issue 3: File Paths Wrong

**Symptoms:** Files showing as "MISSING" in debug.php

**Solution:**
Make sure all files are uploaded to the correct location:
```
public_html/foxhole/tests/v1/
├── config/
├── includes/
├── assets/
├── admin/
├── manager/
├── employee/
├── api/
├── login.php
└── setup.sql
```

#### Issue 4: PHP Version Too Old

**Symptoms:** PHP version shows < 7.4 in debug.php

**Solution:**
1. Login to cPanel
2. Go to "Select PHP Version" or "MultiPHP Manager"
3. Select PHP 7.4 or higher
4. Save changes

#### Issue 5: Session Errors

**Symptoms:** Session-related errors in debug output

**Solution:**
Create a writable sessions directory:
```bash
mkdir sessions
chmod 755 sessions
```

Then edit `config/config.php` and add:
```php
ini_set('session.save_path', __DIR__ . '/../sessions');
```

#### Issue 6: Wrong File Permissions

**Symptoms:** Permission denied errors

**Solution:**
Set correct permissions via cPanel File Manager or FTP:
```
Directories: 755
PHP files: 644
```

### Quick Checklist

Before going live, verify:

- [ ] PHP version 7.4 or higher
- [ ] Database created in cPanel
- [ ] Database user has ALL PRIVILEGES
- [ ] setup.sql imported successfully
- [ ] config/database.php has correct credentials
- [ ] All files uploaded to correct directory
- [ ] File permissions set correctly
- [ ] Can access debug.php without errors
- [ ] Can access login-debug.php and see the form

### Testing Steps

1. **Test database connection:**
   ```
   https://yourdomain.com/foxhole/tests/v1/debug.php
   ```
   Should show "✓ Database connected successfully!"

2. **Test login page:**
   ```
   https://yourdomain.com/foxhole/tests/v1/login-debug.php
   ```
   Should show login form (not blank)

3. **Try logging in:**
   - Username: `admin`
   - Password: `admin123`
   - Should see "Password verified!" message

4. **Once working, use regular login:**
   ```
   https://yourdomain.com/foxhole/tests/v1/login.php
   ```

### Still Having Issues?

Check the following:

1. **View PHP error log:**
   - In cPanel, go to "Error Log"
   - Look for recent errors
   - Share the error messages for specific help

2. **Browser Console:**
   - Open browser DevTools (F12)
   - Check Console tab for JavaScript errors
   - Check Network tab to see if CSS is loading

3. **Check .htaccess:**
   - Make sure `.htaccess` file is uploaded
   - Try temporarily renaming it to see if it's causing issues

### Contact Information

If you're still stuck, provide:
1. Output from debug.php
2. Error messages from PHP error log
3. Any error messages in browser console
4. Your PHP version
5. Your hosting provider

### After Fixing

Once everything works:
1. Remove or rename `debug.php` and `login-debug.php`
2. Turn off error display in `login.php` (remove first 3 lines)
3. Change all default passwords
4. Start using the platform!
