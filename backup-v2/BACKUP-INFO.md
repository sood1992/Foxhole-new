# Foxhole V2 Backup

**Backup Date:** October 29, 2025
**Backup Size:** 945KB

## What This Is

This is a complete backup of Foxhole V2 (the version before the V3 Vien Admin Panel redesign).

## Contents

- All PHP application files
- Admin, Manager, and Employee portals
- Email notification system
- Task dependency system
- Gamification features
- All configuration files
- Database schemas

## How to Restore

If you need to roll back from V3 to V2:

1. Stop the web server
2. Copy all files from `backup-v2/` to the main directory
3. Replace all files (overwrite V3 files)
4. Restart the web server
5. Clear browser cache

## Restoration Command

```bash
cp -r backup-v2/* /path/to/foxhole/
```

## What Changed in V3

V3 is a complete UI redesign based on the Vien Admin Panel specifications:
- New two-panel sidebar navigation
- Complete color system overhaul
- New dashboard cards with gradients
- Redesigned data tables
- New form styling
- Updated authentication pages
- Full responsive redesign (XXS to XXL breakpoints)

## Contact

If you need help restoring this backup, contact your development team.
