<?php
require_once __DIR__ . '/config.php';

function pingIP(string $ip, int $timeoutMs = 1000): array {
    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP);
    if (!$ip) {
        return ['online' => false, 'time' => null, 'error' => 'Invalid IP'];
    }

    $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
    if (function_exists('exec') && !in_array('exec', $disabled)) {
        return _pingExec($ip, $timeoutMs);
    }
    return _pingSocket($ip, $timeoutMs);
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
    $timeout = $timeoutMs / 1000;
    $ports   = [80, 443, 22, 8080, 53, 23];
    $start   = microtime(true);

    foreach ($ports as $port) {
        $sock = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if ($sock) {
            fclose($sock);
            $elapsed = (int)round((microtime(true) - $start) * 1000);
            return ['online' => true, 'time' => $elapsed, 'method' => 'socket', 'port' => $port];
        }
    }

    $elapsed = (int)round((microtime(true) - $start) * 1000);
    return ['online' => false, 'time' => $elapsed, 'method' => 'socket'];
}
