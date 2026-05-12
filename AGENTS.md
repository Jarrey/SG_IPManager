# SG_IPManager — Agent Guide

## Architecture

PHP 8.0+ vanilla web app, no framework. JSON file storage (no DB). Single-user session auth with bcrypt + CSRF.

## Key files

| File | Role |
|------|------|
| `api.php` | Single 824-line switch-case JSON API — all CRUD, import/export, ping, settings |
| `app.php` | SPA shell (HTML + inline config in `window.APP`) |
| `includes/data.php` | CRUD, CSV parsing (RouterOS/爱快 format), in-memory per-request cache (`$_DATA_CACHE`) |
| `includes/ping.php` | Cascading detection: Docker host → ARP → ICMP → TCP(15 ports) → HTTP |
| `includes/auth.php` | bcrypt auth, CSRF token, `_atomicWrite` (tmp + rename) |
| `includes/config.php` | Constants for JSON paths under `data/` |

## Storage

All data lives in `data/` as JSON files:
- `ips.json`, `port_mappings.json`, `ping_cache.json`, `mac_vendor_cache.json`, `settings.json`, `users.json`
- `data/.htaccess` denies access (Apache 2.2 + 2.4 syntax)

## Important gotchas

- **No backend pagination** — `get_ips` returns ALL IPs; frontend does memory pagination
- **No tests exist** anywhere in the repo
- **`exec()` required** for ICMP ping and ARP detection; falls back to TCP/HTTP socket probes if disabled
- **ARP in Docker bridge mode** will fail silently and slow things down — settings page warns against it
- **Settings page race condition guard**: subnets are only overwritten when `count($raw) > 0`; explicit clear needs `clear_subnets=1`
- **`getmypid()` can return false** in some PHP SAPIs — `_atomicWrite` tmp filename becomes `.tmp.`
- **`api.php` CSRF check** exempts `export_csv` (GET) and `lookup_mac` (GET)
- **MAC vendor lookup** is sequential with 1.2s delay between requests (free tier rate limit)
- **CSV encoding auto-detect**: UTF-8 BOM → UTF-8, valid UTF-8 → UTF-8, else GBK (both PHP backend and JS frontend)

## Ping detection cascade

1. Docker host range match → instant online
2. ARP (`arp -a` / `ip neigh`) — only if `enable_arp` setting is on
3. ICMP (`ping -n 1` / `ping -c 1`) — via `exec()`
4. TCP socket scan on 15 common ports
5. HTTP HEAD request on ports 80/8080/8888/8008

Returns on first success. Concurrent pinging is frontend-only (individual `ping_ip` API calls); backend `ping_all` is sequential.

## CSV formats

- **DHCP**: `dhcp_static.csv` — columns: id, enabled, interface, ip_addr, mac, cl_name, comment, gateway, dns1, dns2
- **NAT**: `dst_nat.csv` — columns: id, enabled, comment, interface, src_addr, lan_addr, protocol, wan_port, lan_port
- Import modes: merge (dedup by IP/key), append, replace

## Frontend quirks

- `app.js` module pattern returns `{ showPage, openChangePassword }` — some HTML onclick uses `App.showPage()`
- CSRF token exposed in `window.APP.csrf` (visible in HTML source)
- MAC vendor cache is server-side; frontend gets bulk `vendor_cache` map from `get_ips` response
- Device type detection logic in `detectDeviceType()` is duplicated between backend (PHP) and frontend (JS)

## Docker

- `docker/docker-compose.yml` with nginx + PHP-FPM
- `.env` controls `HTTP_PORT`; `./data` directory is mounted for persistence
- GitHub Actions builds multi-platform images automatically

## Setup

- PHP 8.0+ required (uses `str_contains`, `match` not used but `declare(strict_types=1)`)
- Default credentials: `admin` / `admin`
- No config file needed — `data/` dir + `.htaccess` created automatically by `config.php`
