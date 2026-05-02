# SG IPManager

## Overview

`SG IPManager` is a lightweight PHP local application designed for DHCP static IP management in home and small office networks. It provides a simple web dashboard for user authentication, IP binding management, subnet visualization, and device online/offline checking.

## Key Features

- User authentication with role management and default admin account `admin/admin`
- Forced password change on first login
- CSV import/export for DHCP static address lists
- Supports `dhcp_static.csv` format compatible with RouterOS, MikroTik, iKuai, and other router platforms
- Automatic encoding detection for UTF-8 and GBK/GB2312 to avoid Chinese comment garbling
- Visual subnet layout showing assigned and free IP addresses
- Single IP checking and one-click scan for all addresses
- Local file-based storage under `data/`

## File Structure

- `index.php`: login page
- `app.php`: main dashboard and SPA frontend
- `api.php`: JSON API backend
- `logout.php`: logout endpoint
- `includes/`: backend helpers for auth, data, encoding, ping, etc.
- `assets/`: CSS and JavaScript front-end assets
- `data/`: local data storage folder, protected by `.htaccess`
- `dhcp_static.csv`: sample import file

## CSV Compatibility

The import/export CSV format is compatible with multiple router systems, including but not limited to:

- RouterOS / MikroTik
- iKuai
- OpenWrt / LEDE compatible static DHCP lists
- Other systems using the `dhcp_static.csv` format

### Import Notes

- Supports drag-and-drop or file selection
- Detects UTF-8 and GBK/GB2312 encoding automatically
- Offers merge, append, and replace import modes

### Export Notes

- Generates standard `dhcp_static.csv`
- Downloadable file can be imported into compatible routers directly

## How to Use

1. Deploy the project to a PHP-enabled server, such as Apache or Nginx
2. Open `index.php` in a browser
3. Login with `admin` / `admin`
4. Change the default password immediately after login

## Notes

- Ensure the `data/` directory is writable
- The app uses local storage files and does not require a database
- If `exec()` is disabled on the server, ping checks will fall back to socket-based detection

## Language Switch

For Chinese instructions, see [README_CN.md](README_CN.md)
