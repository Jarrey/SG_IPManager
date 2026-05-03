<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in
if (!empty($_SESSION['user'])) {
    header('Location: app.php');
    exit;
}

$error = '';
$showChangePassword = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = '请输入用户名和密码';
        } else {
            $user = authenticateUser($username, $password);
            if ($user) {
                $_SESSION['user'] = $user['username'];
                generateCSRF();
                updateUserLastLogin($username);
                header('Location: app.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        }
    }
}

// Ensure default users exist
getUsers();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>登录 — 索格 IPManager</title>
  <link rel="icon" href="logo.svg" type="image/svg+xml">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg0: #090e1a; --bg1: #0f172a; --bg2: #1e293b; --bg3: #334155;
      --fg1: #f1f5f9; --fg2: #cbd5e1; --fg3: #94a3b8;
      --accent: #3b82f6; --accent-h: #2563eb;
      --danger: #ef4444;
      --radius: 12px;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
      background: var(--bg0);
      color: var(--fg1);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .login-card {
      background: var(--bg2);
      border: 1px solid var(--bg3);
      border-radius: var(--radius);
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,.6);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 2rem;
      justify-content: center;
    }
    .brand img { width: 48px; height: 48px; border-radius: 10px; }
    .brand-text { font-size: 1.35rem; font-weight: 700; letter-spacing: -.5px; }
    .brand-sub { font-size: .75rem; color: var(--fg3); margin-top: 1px; }
    h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--fg2); text-align: center; }
    .form-group { margin-bottom: 1rem; }
    label { display: block; font-size: .8rem; color: var(--fg3); margin-bottom: .4rem; }
    input[type=text], input[type=password] {
      width: 100%;
      background: var(--bg1);
      border: 1px solid var(--bg3);
      border-radius: 8px;
      color: var(--fg1);
      padding: .65rem .9rem;
      font-size: .95rem;
      outline: none;
      transition: border-color .15s;
    }
    input:focus { border-color: var(--accent); }
    .btn {
      width: 100%;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: .75rem;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: .5rem;
      transition: background .15s;
    }
    .btn:hover { background: var(--accent-h); }
    .error {
      background: rgba(239,68,68,.12);
      border: 1px solid rgba(239,68,68,.35);
      color: #fca5a5;
      border-radius: 8px;
      padding: .65rem .9rem;
      font-size: .88rem;
      margin-bottom: 1rem;
    }
    .footer { text-align: center; margin-top: 2rem; font-size: .75rem; color: var(--fg3); }
  </style>
</head>
<body>
<div class="login-card">
  <div class="brand">
    <img src="logo.svg" alt="索格">
    <div>
      <div class="brand-text">索格</div>
      <div class="brand-sub">IPManager</div>
    </div>
  </div>
  <h2>登录</h2>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="action" value="login">
    <div class="form-group">
      <label for="username">用户名</label>
      <input type="text" id="username" name="username" autocomplete="username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autofocus>
    </div>
    <div class="form-group">
      <label for="password">密码</label>
      <input type="password" id="password" name="password" autocomplete="current-password">
    </div>
    <button type="submit" class="btn">登 录</button>
  </form>

  <div class="footer">索格 IPManager v<?= APP_VERSION ?></div>
</div>
</body>
</html>
