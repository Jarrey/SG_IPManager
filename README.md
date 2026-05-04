# SG IPManager

This repository uses English as the primary README entry.

- [中文说明](README_CN.md)

For the full English documentation, see the content below.

## Overview

`SG IPManager` is a lightweight PHP local application designed for DHCP static IP management in home and small office networks. It provides a simple web dashboard for basic user authentication, IP binding management, subnet visualization, and device online/offline checking.

## Key Features

- Single-user authentication model (no role or permission split)
- Account settings page to update username and password
- CSV import/export for DHCP static address lists
- Supports `dhcp_static.csv` format compatible with RouterOS, MikroTik, iKuai, and other router platforms
- Automatic encoding detection for UTF-8 and GBK/GB2312 to avoid Chinese comment garbling
- Visual subnet layout showing assigned and free IP addresses
- Single IP checking and one-click scan for all addresses
- Local file-based storage under `data/`

## Screenshots

### Overview Dashboard

![Overview](img/overview.png)

Main panel with summary cards, status indicators, and quick actions.

### IP List

![IP List](img/iplist.png)

Central table for static IP records, filtering, sorting, and status checks.

### Subnet View

![Subnet View](img/iprange.png)

Visual subnet allocation page showing used and free address ranges.

### Import / Export

![Import Export](img/import_export.png)

CSV workflow page for importing existing records and exporting router-compatible files.

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
4. Open Settings and update your account username/password

## Docker Deployment

Quick start with Docker Compose:

```bash
cd docker
cp .env.sample .env
docker compose up -d
```

Then open: `http://localhost:8080`

Notes:

- Persistent data is stored in host path `./data` (mapped to container `/var/www/html/data`)
- Change `HTTP_PORT` in `docker/.env` if `8080` is already in use

## ARP Detection Notes

The Settings page provides an `Enable ARP detection` switch.

- When enabled, the system tries ARP first, which can improve scan speed on same-LAN devices
- When disabled, ARP is skipped and detection starts from ICMP/TCP/HTTP methods

Docker network mode recommendation:

- `host` network mode: ARP can be enabled (recommended if you need ARP-first detection)
- `bridge` network mode: do not enable ARP (container ARP visibility is limited and may reduce overall efficiency)

## Notes

- Ensure the `data/` directory is writable
- The app uses local storage files and does not require a database
- If `exec()` is disabled on the server, ping checks will fall back to socket-based detection

## Language Switch

For Chinese instructions, see [README_CN.md](README_CN.md)
