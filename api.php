<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/ping.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// CSRF check for all state-changing requests
if (in_array($method, ['POST', 'DELETE', 'PUT'])) {
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    // export_csv is GET, so exempt
    if ($action !== 'export_csv' && !validateCSRF($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$out = ['success' => false, 'error' => 'Unknown action'];

switch ($action) {

    // ── Statistics ──────────────────────────────────────────────────────────
    case 'get_stats': {
        $ips      = getIPs();
        $cache    = getPingCache();
        $settings = getSettings();

        $total    = count($ips);
        $enabled  = count(array_filter($ips, fn($i) => ($i['enabled'] ?? '') === 'yes'));
        $online   = 0; $offline = 0; $checked = 0;
        foreach ($ips as $ip) {
            $a = $ip['ip_addr'] ?? '';
            if (isset($cache[$a])) { $checked++; $cache[$a]['online'] ? $online++ : $offline++; }
        }
        $freeCount = 0;
        foreach ($settings['subnets'] ?? [] as $sn) $freeCount += count(getFreeIPs($sn, $ips));

        $ifaces = array_values(array_unique(array_filter(array_column($ips, 'interface'))));
        $out = ['success' => true, 'stats' => [
            'total'     => $total,
            'enabled'   => $enabled,
            'disabled'  => $total - $enabled,
            'online'    => $online,
            'offline'   => $offline,
            'unchecked' => $total - $checked,
            'free'      => $freeCount,
        ], 'interfaces' => $ifaces];
        break;
    }

    // ── IP list ─────────────────────────────────────────────────────────────
    case 'get_ips': {
        $ips   = getIPs();
        $cache = getPingCache();

        // Merge status from ping cache
        foreach ($ips as &$ip) {
            $a = $ip['ip_addr'] ?? '';
            if ($a && isset($cache[$a])) {
                $ip['status']      = $cache[$a]['online'] ? 'online' : 'offline';
                $ip['last_check']  = $cache[$a]['last_check'];
                $ip['ping_time']   = $cache[$a]['time'];
                $ip['ping_method'] = $cache[$a]['method'] ?? null;
            }
        }
        unset($ip);

        // Filters
        $q      = strtolower(trim($_GET['q'] ?? ''));
        $fStat  = $_GET['status']  ?? '';
        $fEn    = $_GET['enabled'] ?? '';
        $fIface = $_GET['iface']   ?? '';

        if ($q !== '') {
            $ips = array_filter($ips, function($ip) use ($q) {
                return str_contains(strtolower($ip['ip_addr'] ?? ''), $q)
                    || str_contains(strtolower($ip['mac'] ?? ''), $q)
                    || str_contains(strtolower($ip['comment'] ?? ''), $q)
                    || str_contains(strtolower($ip['cl_name'] ?? ''), $q)
                    || str_contains(strtolower($ip['interface'] ?? ''), $q)
                    || str_contains(strtolower(implode(' ', $ip['tags'] ?? [])), $q);
            });
        }
        if ($fStat === 'online')    $ips = array_filter($ips, fn($i) => ($i['status'] ?? '') === 'online');
        if ($fStat === 'offline')   $ips = array_filter($ips, fn($i) => ($i['status'] ?? '') === 'offline');
        if ($fStat === 'unchecked') $ips = array_filter($ips, fn($i) => empty($i['status']));
        if ($fEn === 'yes')         $ips = array_filter($ips, fn($i) => ($i['enabled'] ?? '') === 'yes');
        if ($fEn === 'no')          $ips = array_filter($ips, fn($i) => ($i['enabled'] ?? '') !== 'yes');
        if ($fIface)                $ips = array_filter($ips, fn($i) => ($i['interface'] ?? '') === $fIface);

        // Sort
        $col = in_array($_GET['sort'] ?? '', ['ip_addr','mac','comment','interface','enabled'])
            ? $_GET['sort'] : 'ip_addr';
        $dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? SORT_DESC : SORT_ASC;
        $ips = array_values($ips);
        $vals = array_map(fn($ip) => $col === 'ip_addr'
            ? sprintf('%010u', ip2long($ip['ip_addr'] ?? '0.0.0.0') ?: 0)
            : strtolower($ip[$col] ?? ''), $ips);
        array_multisort($vals, $dir, $ips);

        $out = ['success' => true, 'data' => array_values($ips), 'total' => count($ips)];
        break;
    }

    // ── Add IP ──────────────────────────────────────────────────────────────
    case 'add_ip': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $d     = $_POST;
        $ips   = getIPs();
        $ipStr = filter_var(trim($d['ip_addr'] ?? ''), FILTER_VALIDATE_IP);
        if (!$ipStr) { $out = ['success' => false, 'error' => '无效的 IP 地址']; break; }
        foreach ($ips as $x) {
            if ($x['ip_addr'] === $ipStr) { $out = ['success' => false, 'error' => 'IP 地址已存在']; break 2; }
        }
        $new = [
            'id'         => getNextID($ips),
            'enabled'    => ($d['enabled'] ?? '') === 'yes' ? 'yes' : 'no',
            'interface'  => htmlspecialchars(strip_tags($d['interface'] ?? 'lan1'), ENT_QUOTES),
            'ip_addr'    => $ipStr,
            'mac'        => strtolower(preg_replace('/[^a-fA-F0-9:]/', '', $d['mac'] ?? '')),
            'cl_name'    => htmlspecialchars(strip_tags($d['cl_name'] ?? ''), ENT_QUOTES),
            'comment'    => htmlspecialchars(strip_tags($d['comment'] ?? ''), ENT_QUOTES),
            'gateway'    => filter_var(trim($d['gateway'] ?? ''), FILTER_VALIDATE_IP) ?: '',
            'dns1'       => filter_var(trim($d['dns1'] ?? ''), FILTER_VALIDATE_IP) ?: '',
            'dns2'       => filter_var(trim($d['dns2'] ?? ''), FILTER_VALIDATE_IP) ?: '',
            'tags'       => array_filter(array_map('trim', explode(',', strip_tags($d['tags'] ?? '')))),
            'notes'      => htmlspecialchars(strip_tags($d['notes'] ?? ''), ENT_QUOTES),
            'status'     => null,
            'last_check' => null,
        ];
        $ips[] = $new;
        saveIPs($ips);
        $out = ['success' => true, 'ip' => $new];
        break;
    }

    // ── Update IP ───────────────────────────────────────────────────────────
    case 'update_ip': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $d     = $_POST;
        $id    = (int)($d['id'] ?? 0);
        $ips   = getIPs();
        $ipStr = filter_var(trim($d['ip_addr'] ?? ''), FILTER_VALIDATE_IP);
        if (!$ipStr) { $out = ['success' => false, 'error' => '无效的 IP 地址']; break; }
        $found = false;
        foreach ($ips as &$ip) {
            if ($ip['id'] !== $id) continue;
            // Duplicate check
            foreach ($ips as $x) {
                if ($x['id'] !== $id && $x['ip_addr'] === $ipStr) {
                    $out = ['success' => false, 'error' => 'IP 地址已被占用']; break 3;
                }
            }
            $ip['enabled']   = ($d['enabled'] ?? '') === 'yes' ? 'yes' : 'no';
            $ip['interface'] = htmlspecialchars(strip_tags($d['interface'] ?? $ip['interface']), ENT_QUOTES);
            $ip['ip_addr']   = $ipStr;
            $ip['mac']       = strtolower(preg_replace('/[^a-fA-F0-9:]/', '', $d['mac'] ?? $ip['mac']));
            $ip['cl_name']   = htmlspecialchars(strip_tags($d['cl_name'] ?? $ip['cl_name'] ?? ''), ENT_QUOTES);
            $ip['comment']   = htmlspecialchars(strip_tags($d['comment'] ?? $ip['comment'] ?? ''), ENT_QUOTES);
            $ip['gateway']   = filter_var(trim($d['gateway'] ?? ''), FILTER_VALIDATE_IP) ?: ($ip['gateway'] ?? '');
            $ip['dns1']      = filter_var(trim($d['dns1'] ?? ''), FILTER_VALIDATE_IP) ?: ($ip['dns1'] ?? '');
            $ip['dns2']      = filter_var(trim($d['dns2'] ?? ''), FILTER_VALIDATE_IP) ?: ($ip['dns2'] ?? '');
            $ip['tags']      = array_filter(array_map('trim', explode(',', strip_tags($d['tags'] ?? implode(',', $ip['tags'] ?? [])))));
            $ip['notes']     = htmlspecialchars(strip_tags($d['notes'] ?? $ip['notes'] ?? ''), ENT_QUOTES);
            $found = true;
            $out   = ['success' => true, 'ip' => $ip];
            break;
        }
        unset($ip);
        if (!$found) { $out = ['success' => false, 'error' => '记录不存在']; break; }
        saveIPs($ips);
        break;
    }

    // ── Delete IP ───────────────────────────────────────────────────────────
    case 'delete_ip': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $id  = (int)($_POST['id'] ?? 0);
        $ips = array_filter(getIPs(), fn($i) => $i['id'] !== $id);
        saveIPs($ips);
        $out = ['success' => true];
        break;
    }

    // ── Bulk delete ─────────────────────────────────────────────────────────
    case 'bulk_delete': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $raw = $_POST['ids'] ?? '';
        $ids = is_array($raw) ? array_map('intval', $raw) : array_map('intval', explode(',', $raw));
        $ips = array_filter(getIPs(), fn($i) => !in_array($i['id'], $ids));
        saveIPs($ips);
        $out = ['success' => true, 'deleted' => count($ids)];
        break;
    }

    // ── Toggle enabled ──────────────────────────────────────────────────────
    case 'toggle_ip': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $id  = (int)($_POST['id'] ?? 0);
        $ips = getIPs();
        foreach ($ips as &$ip) {
            if ($ip['id'] === $id) {
                $ip['enabled'] = $ip['enabled'] === 'yes' ? 'no' : 'yes';
                $out = ['success' => true, 'enabled' => $ip['enabled']];
                break;
            }
        }
        unset($ip);
        saveIPs($ips);
        break;
    }

    // ── Ping single ─────────────────────────────────────────────────────────
    case 'ping_ip': {
        $addr = filter_var(trim($_GET['ip'] ?? $_POST['ip'] ?? ''), FILTER_VALIDATE_IP);
        if (!$addr) { $out = ['success' => false, 'error' => 'Invalid IP']; break; }
        $settings = getSettings();
        $result   = pingIP($addr, (int)($settings['ping_timeout'] ?? 1000));
        $saved    = updatePingResult($addr, $result);
        $out      = ['success' => true, 'ip' => $addr, 'online' => $result['online'],
                     'time' => $result['time'], 'method' => $result['method'] ?? null,
                     'checked_at' => $saved['last_check']];
        break;
    }

    // ── Ping all (server-side batch, for SSE/fallback) ──────────────────────
    case 'ping_all': {
        set_time_limit(300);
        $ips      = getIPs();
        $settings = getSettings();
        $results  = [];
        foreach ($ips as $entry) {
            $addr = $entry['ip_addr'] ?? '';
            if (!$addr) continue;
            $r         = pingIP($addr, (int)($settings['ping_timeout'] ?? 1000));
            $saved     = updatePingResult($addr, $r);
            $results[$addr] = ['online' => $r['online'], 'time' => $r['time'], 'checked_at' => $saved['last_check']];
        }
        $out = ['success' => true, 'results' => $results, 'total' => count($results)];
        break;
    }

    // ── Get ping cache ──────────────────────────────────────────────────────
    case 'get_ping_cache': {
        $out = ['success' => true, 'cache' => getPingCache()];
        break;
    }

    case 'clear_ping_cache': {
        if ($method !== 'POST') { http_response_code(405); break; }
        savePingCache([]);
        $out = ['success' => true];
        break;
    }

    case 'clear_mac_vendor_cache': {
        if ($method !== 'POST') { http_response_code(405); break; }
        saveMacVendorCache([]);
        $out = ['success' => true];
        break;
    }

    // ── Import CSV ──────────────────────────────────────────────────────────
    case 'import_csv': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $mode    = in_array($_POST['mode'] ?? '', ['merge','append','replace']) ? $_POST['mode'] : 'merge';
        $content = '';
        if (!empty($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $content = file_get_contents($_FILES['csv_file']['tmp_name']);
        } elseif (!empty($_POST['csv_content'])) {
            $content = $_POST['csv_content'];
        } else {
            $out = ['success' => false, 'error' => '未提供 CSV 内容']; break;
        }
        $newIPs = parseCSVContent($content);
        if (empty($newIPs)) { $out = ['success' => false, 'error' => 'CSV 中未找到有效数据']; break; }

        if ($mode === 'replace') {
            saveIPs($newIPs);
            $out = ['success' => true, 'imported' => count($newIPs), 'added' => count($newIPs), 'updated' => 0];
        } elseif ($mode === 'append') {
            $existing = getIPs();
            $maxId    = empty($existing) ? 0 : (int)max(array_column($existing, 'id'));
            foreach ($newIPs as &$ip) { $ip['id'] = ++$maxId; }
            unset($ip);
            saveIPs(array_merge($existing, $newIPs));
            $out = ['success' => true, 'imported' => count($newIPs), 'added' => count($newIPs), 'updated' => 0];
        } else { // merge
            $existing = getIPs();
            $byIP = [];
            foreach ($existing as $ip) $byIP[$ip['ip_addr']] = $ip;
            $added = $updated = 0;
            foreach ($newIPs as $n) {
                $a = $n['ip_addr'];
                if (isset($byIP[$a])) {
                    // Preserve runtime fields; update CSV fields
                    $byIP[$a] = array_merge($byIP[$a], array_intersect_key($n, array_flip(['enabled','interface','mac','cl_name','comment','gateway','dns1','dns2'])));
                    $updated++;
                } else {
                    $byIP[$a] = $n; $added++;
                }
            }
            // Resequence IDs
            $maxId  = 0;
            $merged = [];
            foreach ($byIP as $ip) {
                if (!empty($ip['id'])) $maxId = max($maxId, (int)$ip['id']);
                $merged[] = $ip;
            }
            foreach ($merged as &$ip) { if (empty($ip['id'])) $ip['id'] = ++$maxId; }
            unset($ip);
            saveIPs($merged);
            $out = ['success' => true, 'imported' => $added + $updated, 'added' => $added, 'updated' => $updated];
        }
        break;
    }

    // ── Export CSV ──────────────────────────────────────────────────────────
    case 'export_csv': {
        $ips = getIPs();
        if (!empty($_GET['enabled_only'])) {
            $ips = array_filter($ips, fn($i) => ($i['enabled'] ?? '') === 'yes');
        }
        $csv = exportToCSV($ips);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="dhcp_static_' . date('Ymd_His') . '.csv"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    // ── Subnet info ─────────────────────────────────────────────────────────
    case 'get_subnets': {
        $settings = getSettings();
        $ips      = getIPs();
        $cache    = getPingCache();
        $result   = [];
        foreach ($settings['subnets'] ?? [] as $sn) {
            $usedIPs = array_filter($ips, fn($ip) => isIPInSubnet($ip['ip_addr'] ?? '', $sn['network'], (int)$sn['prefix']));
            foreach ($usedIPs as &$u) {
                $a = $u['ip_addr'];
                $u['status'] = isset($cache[$a]) ? ($cache[$a]['online'] ? 'online' : 'offline') : 'unchecked';
            }
            unset($u);
            $freeIPs = getFreeIPs($sn, $ips);
            $result[] = ['subnet' => $sn, 'used' => array_values($usedIPs), 'free' => $freeIPs,
                'used_count' => count($usedIPs), 'free_count' => count($freeIPs),
                'total' => ($sn['range_end'] - $sn['range_start'] + 1)];
        }
        $out = ['success' => true, 'data' => $result];
        break;
    }

    // ── Get / Save settings ─────────────────────────────────────────────────
    case 'get_settings': {
        $out = ['success' => true, 'settings' => getSettings()];
        break;
    }
    case 'save_settings': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $s = getSettings();
        if (isset($_POST['default_gateway'])) {
            $gw = filter_var(trim($_POST['default_gateway']), FILTER_VALIDATE_IP);
            if ($gw) $s['default_gateway'] = $gw;
        }
        if (isset($_POST['default_interface'])) {
            $s['default_interface'] = htmlspecialchars(strip_tags($_POST['default_interface']), ENT_QUOTES);
        }
        if (isset($_POST['ping_timeout'])) {
            $s['ping_timeout'] = min(10000, max(100, (int)$_POST['ping_timeout']));
        }
        if (!empty($_POST['subnets'])) {
            $raw = json_decode($_POST['subnets'], true);
            if (is_array($raw)) {
                $valid = [];
                foreach ($raw as $sn) {
                    $net = filter_var(trim($sn['network'] ?? ''), FILTER_VALIDATE_IP);
                    if (!$net) continue;
                    $valid[] = [
                        'id'          => (int)($sn['id'] ?? count($valid) + 1),
                        'name'        => htmlspecialchars(strip_tags($sn['name'] ?? 'Subnet'), ENT_QUOTES),
                        'network'     => $net,
                        'prefix'      => min(32, max(0, (int)($sn['prefix'] ?? 24))),
                        'range_start' => min(254, max(1, (int)($sn['range_start'] ?? 2))),
                        'range_end'   => min(254, max(1, (int)($sn['range_end'] ?? 254))),
                        'gateway'     => filter_var(trim($sn['gateway'] ?? ''), FILTER_VALIDATE_IP) ?: '',
                    ];
                }
                $s['subnets'] = $valid;
            }
        }
        saveSettings($s);
        $out = ['success' => true, 'settings' => $s];
        break;
    }

    // ── Change password ─────────────────────────────────────────────────────
    case 'change_password': {
        if ($method !== 'POST') { http_response_code(405); break; }
        $cur     = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 6) { $out = ['success' => false, 'error' => '新密码至少 6 位']; break; }
        if ($new !== $confirm) { $out = ['success' => false, 'error' => '两次密码不一致']; break; }
        if (!($_SESSION['must_change'] ?? false)) {
            if (!authenticateUser($_SESSION['user'], $cur)) {
                $out = ['success' => false, 'error' => '当前密码错误']; break;
            }
        }
        updateUserPassword($_SESSION['user'], $new);
        $_SESSION['must_change'] = false;
        $out = ['success' => true, 'message' => '密码修改成功'];
        break;
    }

    // ── User management (admin only) ────────────────────────────────────────
    case 'get_users': {
        if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); $out = ['error' => 'Forbidden']; break; }
        $users = array_map(function($u) { unset($u['password']); return $u; }, getUsers());
        $out   = ['success' => true, 'users' => array_values($users)];
        break;
    }
    case 'add_user': {
        if ($method !== 'POST') { http_response_code(405); break; }
        if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); break; }
        $uname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['username'] ?? '');
        $pwd   = $_POST['password'] ?? '';
        $role2 = in_array($_POST['role'] ?? 'user', ['admin','user']) ? $_POST['role'] : 'user';
        if (strlen($uname) < 3) { $out = ['success' => false, 'error' => '用户名至少 3 位']; break; }
        if (strlen($pwd) < 6)   { $out = ['success' => false, 'error' => '密码至少 6 位'];   break; }
        $users = getUsers();
        foreach ($users as $u) { if ($u['username'] === $uname) { $out = ['success' => false, 'error' => '用户名已存在']; break 2; } }
        $users[] = ['id' => count($users) + 1, 'username' => $uname,
            'password' => password_hash($pwd, PASSWORD_BCRYPT), 'role' => $role2,
            'must_change' => false, 'created_at' => date('Y-m-d H:i:s'), 'last_login' => null];
        saveUsers($users);
        $out = ['success' => true];
        break;
    }
    case 'delete_user': {
        if ($method !== 'POST') { http_response_code(405); break; }
        if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); break; }
        $uname = $_POST['username'] ?? '';
        if ($uname === $_SESSION['user']) { $out = ['success' => false, 'error' => '不能删除自己']; break; }
        $users = array_filter(getUsers(), fn($u) => $u['username'] !== $uname);
        saveUsers($users);
        $out = ['success' => true];
        break;
    }

    case 'lookup_mac': {
        // Read-only GET endpoint — no CSRF required
        $rawMac = trim($_GET['mac'] ?? '');

        // Normalize to hex digits only, uppercase
        $hex = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $rawMac));
        if (strlen($hex) < 6) {
            $out = ['success' => false, 'error' => 'Invalid MAC address'];
            break;
        }
        $oui = substr($hex, 0, 6); // first 3 bytes (for cache key)

        // Check local cache
        // Known vendors cached 30 days; empty (not-found) results cached only 1 day
        $cache = getMacVendorCache();
        if (isset($cache[$oui])) {
            $ttl = ($cache[$oui]['vendor'] !== '') ? 86400 * 30 : 86400;
            if ((time() - ($cache[$oui]['ts'] ?? 0)) < $ttl) {
                $out = ['success' => true, 'vendor' => $cache[$oui]['vendor'], 'oui' => $oui, 'cached' => true];
                break;
            }
        }

        // Build URL using full MAC with dashes (e.g. 88-25-93-95-e6-d7)
        $hexToUse  = strlen($hex) >= 12 ? $hex : str_pad($hex, 12, '0');
        $hexPairs  = str_split(strtolower($hexToUse), 2);
        $formatted = implode('-', $hexPairs);
        $vendor    = '';
        $apiUrl    = 'https://api.macvendors.com/' . $formatted;

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_FAILONERROR    => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_HTTPHEADER     => ['Accept: text/plain, */*'],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp     = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 429) {
                $out = ['success' => false, 'error' => 'rate_limited', 'vendor' => '', 'oui' => $oui];
                break;
            }
            if ($resp !== false && $httpCode === 200) {
                $body = trim($resp);
                if (strlen($body) > 0 && $body[0] !== '{' && strlen($body) < 200) {
                    $vendor = $body;
                }
            }
        } elseif (ini_get('allow_url_fopen')) {
            $ctx  = stream_context_create(['http' => [
                'timeout'       => 5,
                'header'        => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36\r\nAccept: text/plain, */*\r\n",
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($apiUrl, false, $ctx);
            $statusLine = $http_response_header[0] ?? '';

            if (str_contains($statusLine, '429')) {
                $out = ['success' => false, 'error' => 'rate_limited', 'vendor' => '', 'oui' => $oui];
                break;
            }
            if (str_contains($statusLine, '200') && $resp !== false) {
                $body = trim($resp);
                if (strlen($body) > 0 && $body[0] !== '{' && strlen($body) < 200) {
                    $vendor = $body;
                }
            }
        }

        $cache[$oui] = ['vendor' => $vendor, 'ts' => time()];
        saveMacVendorCache($cache);

        $out = ['success' => true, 'vendor' => $vendor, 'oui' => $oui, 'cached' => false];
        break;
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
