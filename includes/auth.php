<?php
require_once __DIR__ . '/config.php';

function getUsers(): array {
    if (!file_exists(USERS_FILE)) {
        $defaults = [[
            'id'         => 1,
            'username'   => 'admin',
            'password'   => password_hash('admin', PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s'),
            'last_login' => null,
        ]];
        _atomicWrite(USERS_FILE, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    return json_decode(file_get_contents(USERS_FILE), true) ?: [];
}

function saveUsers(array $users): void {
    _atomicWrite(USERS_FILE, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function authenticateUser(string $username, string $password): ?array {
    foreach (getUsers() as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            return $user;
        }
    }
    return null;
}

function updateUserPassword(string $username, string $newPassword): void {
    $users = getUsers();
    foreach ($users as &$user) {
        if ($user['username'] === $username) {
            $user['password']   = password_hash($newPassword, PASSWORD_BCRYPT);
            $user['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    saveUsers($users);
}

function updateUsername(string $oldUsername, string $newUsername): bool {
    $users = getUsers();
    foreach ($users as $u) {
        if ($u['username'] === $newUsername) return false; // already taken
    }
    foreach ($users as &$user) {
        if ($user['username'] === $oldUsername) {
            $user['username']   = $newUsername;
            $user['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    saveUsers($users);
    return true;
}

function updateUserLastLogin(string $username): void {
    $users = getUsers();
    foreach ($users as &$user) {
        if ($user['username'] === $username) {
            $user['last_login'] = date('Y-m-d H:i:s');
            break;
        }
    }
    saveUsers($users);
}

function requireAuth(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        header('Location: index.php');
        exit;
    }
}

function validateCSRF(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function _atomicWrite(string $file, string $content): void {
    $tmp = $file . '.tmp.' . getmypid();
    file_put_contents($tmp, $content, LOCK_EX);
    rename($tmp, $file);
}
