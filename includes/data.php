<?php
require_once __DIR__ . '/config.php';

// ── Low-level helpers ────────────────────────────────────────────────────────

function _atomicWriteData(string $file, string $content): void {
    $tmp = $file . '.tmp.' . getmypid();
    file_put_contents($tmp, $content, LOCK_EX);
    rename($tmp, $file);
}

// ── IP records ───────────────────────────────────────────────────────────────

function getIPs(): array {
    if (!file_exists(IPS_FILE)) {
        // Auto-import from dhcp_static.csv if present beside this app
        $csvPath = ROOT_DIR . DIRECTORY_SEPARATOR . 'dhcp_static.csv';
        if (file_exists($csvPath)) {
            $ips = parseCSVContent(file_get_contents($csvPath));
            saveIPs($ips);
            return $ips;
        }
        saveIPs([]);
        return [];
    }
    return json_decode(file_get_contents(IPS_FILE), true) ?: [];
}

function saveIPs(array $ips): void {
    _atomicWriteData(IPS_FILE, json_encode(array_values($ips), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getNextID(array $ips): int {
    if (empty($ips)) return 1;
    return max(array_column($ips, 'id')) + 1;
}

function parseCSVContent(string $content): array {
    // Detect & convert encoding
    // 1. Strip UTF-8 BOM if present
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    } elseif (!mb_check_encoding($content, 'UTF-8')) {
        // Not valid UTF-8 — treat as GBK / GB2312 / GB18030
        $content = mb_convert_encoding($content, 'UTF-8', 'GBK');
    }

    $lines   = preg_split('/\r?\n/', trim($content));
    $headers = null;
    $ips     = [];

    foreach ($lines as $i => $line) {
        $line = trim($line);
        if ($line === '') continue;

        $row = str_getcsv($line, ',', '"');

        // First data-looking row containing 'ip_addr' is the header
        if ($headers === null) {
            if (in_array('ip_addr', $row)) {
                $headers = $row;
                continue;
            }
            continue; // skip garbage before header
        }

        if (count($row) < 4) continue;

        $entry = [];
        foreach ($headers as $j => $h) {
            $entry[$h] = isset($row[$j]) ? trim($row[$j]) : '';
        }

        // URL-decode text fields (original format uses %20 etc.)
        foreach (['comment', 'cl_name'] as $f) {
            if (isset($entry[$f])) $entry[$f] = urldecode($entry[$f]);
        }

        $entry['id'] = isset($entry['id']) ? (int)$entry['id'] : 0;

        // Extra fields (only add if missing)
        $entry += ['status' => null, 'last_check' => null, 'tags' => [], 'notes' => ''];

        $ips[] = $entry;
    }
    return $ips;
}

function exportToCSV(array $ips): string {
    $headers = ['id', 'enabled', 'interface', 'ip_addr', 'mac', 'cl_name', 'comment', 'gateway', 'dns1', 'dns2'];
    $lines   = [implode(',', $headers)];

    foreach ($ips as $ip) {
        $row = [];
        foreach ($headers as $h) {
            $val = (string)($ip[$h] ?? '');
            if (in_array($h, ['comment', 'cl_name'])) {
                $val = str_replace(' ', '%20', $val);
            }
            $row[] = '"' . str_replace('"', '""', $val) . '"';
        }
        $lines[] = implode(',', $row);
    }
    return implode("\r\n", $lines);
}

// ── Settings ─────────────────────────────────────────────────────────────────

function getSettings(): array {
    if (!file_exists(SETTINGS_FILE)) {
        $default = [
            'subnets' => [[
                'id'          => 1,
                'name'        => 'LAN',
                'network'     => '192.168.2.0',
                'prefix'      => 24,
                'range_start' => 2,
                'range_end'   => 254,
                'gateway'     => '192.168.2.1',
            ]],
            'default_gateway'   => '192.168.2.1',
            'default_interface' => 'lan1',
            'ping_timeout'      => 1000,
            'mac_cache_months'  => 6,
            'docker_host_ranges' => '', // e.g., "192.168.2.6-192.168.2.10" for Docker host IPs
            'enable_arp'        => false, // ARP probe as first detection step (disable in bridge-mode Docker)
        ];
        _atomicWriteData(SETTINGS_FILE, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $default;
    }
    return json_decode(file_get_contents(SETTINGS_FILE), true) ?: [];
}

function saveSettings(array $settings): void {
    _atomicWriteData(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ── Subnet helpers ────────────────────────────────────────────────────────────

function isIPInSubnet(string $ip, string $network, int $prefix): bool {
    $ipL  = ip2long($ip);
    $netL = ip2long($network);
    if ($ipL === false || $netL === false) return false;
    $mask = $prefix === 0 ? 0 : (~0 << (32 - $prefix));
    return ($ipL & $mask) === ($netL & $mask);
}

function getFreeIPs(array $subnet, array $usedIPs): array {
    $parts = explode('.', $subnet['network']);
    $base  = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.';
    $used  = array_column($usedIPs, 'ip_addr');
    $free  = [];
    for ($i = (int)$subnet['range_start']; $i <= (int)$subnet['range_end']; $i++) {
        $candidate = $base . $i;
        if (!in_array($candidate, $used)) $free[] = $candidate;
    }
    return $free;
}

// ── Ping cache ────────────────────────────────────────────────────────────────

function getPingCache(): array {
    if (!file_exists(PING_CACHE_FILE)) return [];
    return json_decode(file_get_contents(PING_CACHE_FILE), true) ?: [];
}

function savePingCache(array $cache): void {
    _atomicWriteData(PING_CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT));
}

function updatePingResult(string $ip, array $result): array {
    $cache      = getPingCache();
    $cache[$ip] = [
        'online'     => $result['online'],
        'time'       => $result['time'] ?? null,
        'method'     => $result['method'] ?? null,
        'last_check' => date('Y-m-d H:i:s'),
    ];
    savePingCache($cache);
    return $cache[$ip];
}

// ── Port Mappings ─────────────────────────────────────────────────────────────

function getPortMappings(): array {
    if (!file_exists(PORT_MAPPINGS_FILE)) {
        // Auto-import from dst_nat.csv if present beside this app
        $csvPath = ROOT_DIR . DIRECTORY_SEPARATOR . 'dst_nat.csv';
        if (file_exists($csvPath)) {
            $pms = parseNATCSVContent(file_get_contents($csvPath));
            savePortMappings($pms);
            return $pms;
        }
        savePortMappings([]);
        return [];
    }
    return json_decode(file_get_contents(PORT_MAPPINGS_FILE), true) ?: [];
}

function savePortMappings(array $pms): void {
    _atomicWriteData(PORT_MAPPINGS_FILE, json_encode(array_values($pms), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getNextPortMappingID(array $pms): int {
    if (empty($pms)) return 1;
    return max(array_column($pms, 'id')) + 1;
}

function parseNATCSVContent(string $content): array {
    // Detect & convert encoding
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    } elseif (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'GBK');
    }

    $lines   = preg_split('/\r?\n/', trim($content));
    $headers = null;
    $pms     = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $row = str_getcsv($line, ',', '"');

        if ($headers === null) {
            // Detect header row by checking for known NAT CSV columns
            if (in_array('lan_addr', $row) || in_array('wan_port', $row)) {
                $headers = $row;
                continue;
            }
            continue;
        }

        if (count($row) < 5) continue;

        $entry = [];
        foreach ($headers as $j => $h) {
            $entry[$h] = isset($row[$j]) ? trim($row[$j]) : '';
        }

        // URL-decode text fields
        foreach (['comment', 'src_addr'] as $f) {
            if (isset($entry[$f])) $entry[$f] = urldecode($entry[$f]);
        }

        $entry['id'] = isset($entry['id']) ? (int)$entry['id'] : 0;

        // Ensure all expected keys exist
        $entry += [
            'enabled'   => 'yes',
            'comment'   => '',
            'interface' => 'wan1',
            'src_addr'  => '',
            'lan_addr'  => '',
            'protocol'  => 'tcp+udp',
            'wan_port'  => '',
            'lan_port'  => '',
        ];

        $pms[] = $entry;
    }
    return $pms;
}

function exportNATToCSV(array $pms): string {
    $headers = ['id', 'enabled', 'comment', 'interface', 'src_addr', 'lan_addr', 'protocol', 'wan_port', 'lan_port'];
    $lines   = [implode(',', $headers)];

    foreach ($pms as $pm) {
        $row = [];
        foreach ($headers as $h) {
            $val = (string)($pm[$h] ?? '');
            if (in_array($h, ['comment', 'src_addr'])) {
                $val = str_replace(' ', '%20', $val);
            }
            $row[] = '"' . str_replace('"', '""', $val) . '"';
        }
        $lines[] = implode(',', $row);
    }
    return implode("\r\n", $lines);
}

// ── MAC vendor cache ──────────────────────────────────────────────────────────

function getMacVendorCache(): array {
    if (!file_exists(MAC_VENDOR_CACHE_FILE)) return [];
    return json_decode(file_get_contents(MAC_VENDOR_CACHE_FILE), true) ?: [];
}

function saveMacVendorCache(array $cache): void {
    _atomicWriteData(MAC_VENDOR_CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
