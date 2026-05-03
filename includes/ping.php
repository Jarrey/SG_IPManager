<?php
require_once __DIR__ . '/config.php';

/**
 * Check if an IP is in a local/private network range that may not respond to ping
 * (e.g., Docker host, gateway, or network infrastructure)
 * Returns true if IP should be treated as local infrastructure
 */
function isLocalInfrastructure(string $ip): bool {
    // Docker internal gateway (default bridge network)
    $dockerGateway = '172.17.0.1';
    // Common Docker host aliases
    if ($ip === $dockerGateway || $ip === '127.0.0.1' || $ip === 'localhost') {
        return true;
    }
    
    // Check if in private/local ranges
    $ip_long = ip2long($ip);
    // 10.0.0.0/8
    if ($ip_long >= ip2long('10.0.0.0') && $ip_long <= ip2long('10.255.255.255')) return true;
    // 172.16.0.0/12
    if ($ip_long >= ip2long('172.16.0.0') && $ip_long <= ip2long('172.31.255.255')) return true;
    // 192.168.0.0/16
    if ($ip_long >= ip2long('192.168.0.0') && $ip_long <= ip2long('192.168.255.255')) return true;
    
    return false;
}

/**
 * Check if IP is within Docker host ranges (configured by user)
 * Format: "192.168.2.6-192.168.2.10,10.0.0.1" or single IPs
 */
function isDockerHostIP(string $ip, string $rangesConfig = ''): bool {
    if (empty($rangesConfig)) return false;
    
    $ip_long = ip2long($ip);
    if ($ip_long === false) return false;
    
    $ranges = array_map('trim', explode(',', $rangesConfig));
    foreach ($ranges as $range) {
        if (empty($range)) continue;
        
        // Check if it's a range (e.g., "192.168.2.6-192.168.2.10")
        if (strpos($range, '-') !== false) {
            [$start, $end] = array_map('trim', explode('-', $range, 2));
            $start_long = ip2long($start);
            $end_long = ip2long($end);
            if ($start_long !== false && $end_long !== false && $ip_long >= $start_long && $ip_long <= $end_long) {
                return true;
            }
        } else {
            // Single IP
            $range_long = ip2long($range);
            if ($range_long !== false && $range_long === $ip_long) {
                return true;
            }
        }
    }
    return false;
}

function pingIP(string $ip, int $timeoutMs = 1000, string $dockerHostRanges = ''): array {
    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP);
    if (!$ip) {
        return ['online' => false, 'time' => null, 'error' => 'Invalid IP'];
    }

    // If this IP is marked as Docker host, consider it always online
    if (isDockerHostIP($ip, $dockerHostRanges)) {
        return ['online' => true, 'time' => 0, 'method' => 'docker_host'];
    }

    $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));

    // 1. ICMP ping — fastest and most accurate
    if (function_exists('exec') && !in_array('exec', $disabled)) {
        $r = _pingExec($ip, $timeoutMs);
        if ($r['online']) return $r;
    }

    // 2. TCP port scan — catches devices that block ICMP (cameras, IoT, firewalls, routers)
    $r = _pingSocket($ip, $timeoutMs);
    if ($r['online']) return $r;

    // 3. HTTP probe — sends actual HTTP request; covers web-only devices (NAS, smart TV, etc.)
    return _pingHTTP($ip, $timeoutMs);
}

function _pingExec(string $ip, int $timeoutMs): array {
    $isWin = (PHP_OS_FAMILY === 'Windows') || (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $start = microtime(true);

    if ($isWin) {
        $cmd = 'ping -n 1 -w ' . (int)$timeoutMs . ' ' . escapeshellarg($ip);
    } else {
        $sec = max(1, (int)ceil($timeoutMs / 1000));
        $cmd = 'ping -c 1 -W ' . $sec . ' ' . escapeshellarg($ip);
    }

    exec($cmd . ' 2>&1', $out, $ret);
    $elapsed = (int)round((microtime(true) - $start) * 1000);

    return ['online' => $ret === 0, 'time' => $elapsed, 'method' => 'ping'];
}

function _pingSocket(string $ip, int $timeoutMs): array {
    // Use a short per-port timeout so we can probe many ports quickly on LAN
    $portTimeout = max(0.15, min(0.4, $timeoutMs / 4000));
    // Covers: HTTP(80), HTTPS(443), SSH(22), alt-HTTP(8080), DNS(53), Telnet(23),
    //         FTP(21), RDP(3389), SMB(445), RTSP/camera(554), MQTT/IoT(1883),
    //         alt-HTTPS(8443), Synology NAS(5001), Prometheus(9090), custom(8888)
    $ports = [80, 443, 22, 8080, 53, 23, 21, 3389, 445, 554, 1883, 8443, 5001, 9090, 8888];
    $start = microtime(true);

    foreach ($ports as $port) {
        $sock = @fsockopen($ip, $port, $errno, $errstr, $portTimeout);
        if ($sock) {
            fclose($sock);
            $elapsed = (int)round((microtime(true) - $start) * 1000);
            return ['online' => true, 'time' => $elapsed, 'method' => 'tcp:' . $port];
        }
    }

    $elapsed = (int)round((microtime(true) - $start) * 1000);
    return ['online' => false, 'time' => $elapsed, 'method' => 'socket'];
}

function _pingHTTP(string $ip, int $timeoutMs): array {
    $connTimeout = max(0.5, $timeoutMs / 1000);
    $start = microtime(true);

    foreach ([80, 8080, 8888, 8008] as $port) {
        $sock = @stream_socket_client(
            "tcp://{$ip}:{$port}", $errno, $errstr, $connTimeout,
            STREAM_CLIENT_CONNECT
        );
        if (!$sock) continue;
        // Send a minimal HTTP request and check for any response
        @fwrite($sock, "HEAD / HTTP/1.0\r\nHost: {$ip}\r\nUser-Agent: IPManager/1.0\r\nConnection: close\r\n\r\n");
        stream_set_timeout($sock, 0, 400000); // 400 ms read timeout
        $resp = @fread($sock, 12);
        @fclose($sock);
        if ($resp !== false && $resp !== '') {
            $elapsed = (int)round((microtime(true) - $start) * 1000);
            return ['online' => true, 'time' => $elapsed, 'method' => 'http:' . $port];
        }
    }

    $elapsed = (int)round((microtime(true) - $start) * 1000);
    return ['online' => false, 'time' => $elapsed, 'method' => 'http'];
}
