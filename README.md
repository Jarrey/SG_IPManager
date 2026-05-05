# SG IPManager

A lightweight, self-hosted PHP web application for **DHCP static IP address management** in home and small office networks. No database required — all data is stored as local JSON files.

[中文说明](README_CN.md)

---

## Key Features

- **DHCP Static IP Management** — Full CRUD for IP bindings with enable/disable toggle and bulk delete
- **Port Mapping Management** — NAT port mapping CRUD with iKuai-style `dst_nat.csv` import/export
- **CSV Import/Export** — Compatible with RouterOS, MikroTik, iKuai, OpenWrt `dhcp_static.csv` format; auto-detects UTF-8 / GBK encoding
- **Multi-Method Device Detection** — Cascading probe: ARP → ICMP ping → TCP port scan (15 ports) → HTTP HEAD request
- **Concurrent Batch Ping** — User-configurable concurrency (1–20); default 5 concurrent pings for fast one-click scanning
- **MAC Vendor Lookup** — Auto-fetches vendor from macvendors.com with local caching (configurable TTL)
- **Device Type Auto-Detection** — Recognizes phones, laptops, servers, routers, cameras, printers, IoT, TVs, game consoles, smart speakers from MAC vendor + comment
- **Visual Subnet View** — Grid layout showing used (with online/offline status) and free IPs per subnet
- **Overview Dashboard** — Summary cards, device category distribution, subnet summary, recent status changes
- **Port Mapping Overview** — Quick-view widget on the dashboard
- **Session Authentication** — bcrypt password hashing, CSRF protection, session regeneration on login, configurable username
- **Dark Theme UI** — Responsive design with full dark theme using CSS custom properties
- **Docker Support** — Ready-to-use Dockerfile and docker-compose with automated multi-platform image builds via GitHub Actions

## Screenshots

### Overview Dashboard

![Overview](img/overview.png)

Summary cards, device category distribution, subnet summary, recent changes, and port mapping quick-view.

### IP List

![IP List](img/iplist.png)

Search, filter (by status/enabled/interface), sortable columns, inline toggle, MAC vendor display with device icons, and copy-to-clipboard.

### Subnet View

![Subnet View](img/iprange.png)

Visual grid of each subnet showing assigned (color-coded by status) and free IP addresses.

### Import / Export

![Import Export](img/import_export.png)

Drag-and-drop CSV import with preview, three merge modes, and one-click export for both DHCP and NAT port mappings.

## File Structure

```
├── index.php          Login page
├── app.php            Main dashboard (SPA shell)
├── api.php            JSON API backend
├── logout.php         Logout endpoint
├── includes/
│   ├── config.php     App constants & data directory setup
│   ├── auth.php       Authentication, bcrypt, CSRF
│   ├── data.php       CRUD, CSV parsing, cache, settings
│   └── ping.php       Multi-method IP reachability detection
├── assets/
│   ├── app.js         Single-page application (JS)
│   └── style.css      Dark theme stylesheet
├── data/              JSON storage (protected)
└── docker/            Dockerfile, docker-compose, nginx config
```

## CSV Compatibility

The import/export CSV format is compatible with:

- RouterOS / MikroTik `dhcp_static.csv`
- iKuai (爱快) `dhcp_static.csv` and `dst_nat.csv` (port mappings)
- OpenWrt / LEDE static DHCP lists
- Other systems using the same format

### Import Notes

- Drag-and-drop or file selection
- Automatic UTF-8 / GBK encoding detection
- Three modes: **merge** (deduplicate by IP), **append** (keep all), **replace** (clear all)

### Export Notes

- Generates standard `dhcp_static.csv` or `dst_nat.csv`
- Option to export only enabled entries

## Quick Start

### Standalone (Apache / Nginx)

1. Deploy to any PHP 8.0+ server
2. Open `index.php` in a browser
3. Login with `admin` / `admin`
4. Go to Settings to change your password and configure subnets

### Docker

```bash
cd docker
cp .env.sample .env
docker compose up -d
```

Then open: `http://localhost:8080`

> Persistent data is stored in `./data` (host path). Change `HTTP_PORT` in `.env` if needed.

## Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Default Gateway | Fallback gateway for new IP entries | `192.168.2.1` |
| Default Interface | Fallback interface name | `lan1` |
| Ping Timeout | Timeout per IP in milliseconds | `1000` |
| Ping Concurrency | Number of simultaneous ping probes (1–20) | `5` |
| MAC Cache Months | Vendor cache time-to-live | `6` |
| Docker Host Ranges | Always-on IPs for Docker hosts | — |
| Enable ARP | ARP probe before ICMP (disable in bridge-mode Docker) | Off |

### ARP Detection Notes

- **host network mode**: ARP can be enabled (recommended for speed)
- **bridge network mode**: do not enable ARP (container ARP visibility is limited)

## Device Detection Flow

Ping cascades through four methods, returning as soon as one succeeds:

1. **Docker Host** — IPs in configured ranges are immediately marked online
2. **ARP** — Same-LAN devices via `arp`/`ip neigh` (only when enabled)
3. **ICMP Ping** — Standard ping via `exec()`
4. **TCP Port Scan** — 15 common ports (80, 443, 22, 8080, 53, 23, 21, 3389, 445, 554, 1883, 8443, 5001, 9090, 8888)
5. **HTTP Probe** — Sends HEAD request on ports 80/8080/8888/8008

> If `exec()` is disabled, ICMP and ARP are skipped, falling back to TCP/HTTP.

## Notes

- Ensure `data/` is writable by the web server
- No database required — purely file-based storage
- Single-user authentication model
- CSRF protection enabled for all mutating API calls
- Session is regenerated on login to prevent fixation attacks
