<?php
require_once __DIR__ . '/config.php';

function pingIP(string $ip, int $timeoutMs = 1000): array {
    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP);
    if (!$ip) {
        return ['online' => false, 'time' => null, 'error' => 'Invalid IP'];
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
