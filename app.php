<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

requireAuth();

$username    = $_SESSION['user'];
$role        = $_SESSION['role'] ?? 'user';
$mustChange  = !empty($_SESSION['must_change']);
$csrf        = generateCSRF();
$settings    = getSettings();
$defaultGW   = htmlspecialchars($settings['default_gateway'] ?? '192.168.2.1');
$defaultIFace= htmlspecialchars($settings['default_interface'] ?? 'lan1');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>索格 IPManager</title>
  <link rel="icon" href="logo.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- ═══════════ SIDEBAR ════════════════════════════════════════════════════ -->
<aside id="sidebar">
  <div class="sidebar-header">
    <img src="logo.svg" alt="索格" class="sidebar-logo">
    <div class="sidebar-brand">
      <span class="sidebar-title">索格</span>
      <span class="sidebar-sub">IPManager</span>
    </div>
    <button id="sidebar-close" class="icon-btn" aria-label="关闭侧边栏">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <nav class="sidebar-nav">
    <a href="#" class="nav-item active" data-page="overview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
      </svg>
      <span>总 览</span>
    </a>
    <a href="#" class="nav-item" data-page="iplist">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
      </svg>
      <span>IP 列表</span>
    </a>
    <a href="#" class="nav-item" data-page="subnet">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
        <path d="M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>
      </svg>
      <span>网段视图</span>
    </a>
    <a href="#" class="nav-item" data-page="io">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
      <span>导入/导出</span>
    </a>
    <a href="#" class="nav-item" data-page="settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
      </svg>
      <span>设 置</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
      <div class="user-detail">
        <span class="user-name"><?= htmlspecialchars($username) ?></span>
        <span class="user-role"><?= $role === 'admin' ? '管理员' : '普通用户' ?></span>
      </div>
    </div>
    <div class="sidebar-links">
      <a href="#" onclick="App.showPage('settings');App.openChangePassword();return false;" class="sl-link">修改密码</a>
      <a href="logout.php" class="sl-link sl-link-danger">退 出</a>
    </div>
  </div>
</aside>
<div id="sidebar-overlay"></div>

<!-- ═══════════ MAIN ═══════════════════════════════════════════════════════ -->
<div id="main">

  <!-- Header -->
  <header id="top-header">
    <button id="menu-toggle" class="icon-btn" aria-label="菜单">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <div class="header-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="search-icon">
        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
      </svg>
      <input type="text" id="global-search" placeholder="搜索 IP、MAC、备注、主机名…" autocomplete="off">
    </div>

    <div class="header-actions">
      <button id="btn-check-all" class="btn btn-sm btn-outline" title="一键检测所有IP">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        </svg>
        <span class="btn-label">一键检测</span>
      </button>
      <button id="btn-add-ip" class="btn btn-sm btn-primary" title="添加IP">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        <span class="btn-label">添加 IP</span>
      </button>
    </div>
  </header>

  <!-- Pages -->
  <div id="content">

    <!-- ── Overview ──────────────────────────────────────────────────── -->
    <section id="page-overview" class="page active">
      <div class="page-title">
        <h1>总 览</h1>
        <span class="page-sub" id="last-update-time"></span>
      </div>
      <div id="stats-grid" class="stats-grid">
        <div class="stat-card" data-action="filter-all">
          <div class="stat-icon stat-blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="stat-body"><div class="stat-value" id="st-total">—</div><div class="stat-label">已分配 IP</div></div>
        </div>
        <div class="stat-card" data-action="filter-online">
          <div class="stat-icon stat-green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          </div>
          <div class="stat-body"><div class="stat-value" id="st-online">—</div><div class="stat-label">在 线</div></div>
        </div>
        <div class="stat-card" data-action="filter-offline">
          <div class="stat-icon stat-red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          </div>
          <div class="stat-body"><div class="stat-value" id="st-offline">—</div><div class="stat-label">离 线</div></div>
        </div>
        <div class="stat-card" data-action="show-free">
          <div class="stat-icon stat-cyan">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
          </div>
          <div class="stat-body"><div class="stat-value" id="st-free">—</div><div class="stat-label">空闲 IP</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <div class="stat-body"><div class="stat-value" id="st-unchecked">—</div><div class="stat-label">未检测</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-gray">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
          </div>
          <div class="stat-body"><div class="stat-value" id="st-disabled">—</div><div class="stat-label">已禁用</div></div>
        </div>
      </div>

      <!-- Ping progress bar (hidden by default) -->
      <div id="ping-progress-wrap" class="ping-progress-wrap hidden">
        <div class="ping-progress-label">
          <span>正在检测… <span id="ping-done">0</span> / <span id="ping-total">0</span></span>
          <button id="btn-cancel-ping" class="btn btn-xs btn-danger">取消</button>
        </div>
        <div class="ping-progress-bar"><div id="ping-progress-fill"></div></div>
      </div>

      <!-- Recent checks -->
      <div class="section-title">最近状态变化</div>
      <div id="recent-changes" class="recent-list">
        <div class="empty-hint">尚未检测，点击「一键检测」开始</div>
      </div>
    </section>

    <!-- ── IP List ────────────────────────────────────────────────────── -->
    <section id="page-iplist" class="page">
      <div class="page-title">
        <h1>IP 列表</h1>
        <div class="page-actions">
          <button id="bulk-delete-btn" class="btn btn-sm btn-danger hidden">删除所选</button>
        </div>
      </div>
      <div class="filter-bar">
        <select id="filter-status" class="select-sm">
          <option value="">全部状态</option>
          <option value="online">在线</option>
          <option value="offline">离线</option>
          <option value="unchecked">未检测</option>
        </select>
        <select id="filter-enabled" class="select-sm">
          <option value="">全部启用</option>
          <option value="yes">已启用</option>
          <option value="no">已禁用</option>
        </select>
        <select id="filter-iface" class="select-sm">
          <option value="">全部接口</option>
        </select>
      </div>

      <div class="table-wrap">
        <table id="ip-table">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" id="select-all"></th>
              <th class="col-status">状态</th>
              <th class="sortable col-ip" data-col="ip_addr">IP 地址 <span class="sort-icon">↕</span></th>
              <th class="sortable col-mac" data-col="mac">MAC <span class="sort-icon">↕</span></th>
              <th class="col-vendor">厂 商</th>
              <th class="sortable col-iface" data-col="interface">接口 <span class="sort-icon">↕</span></th>
              <th class="sortable col-comment" data-col="comment">备 注 <span class="sort-icon">↕</span></th>
              <th class="col-gw">网 关</th>
              <th class="col-enabled">启用</th>
              <th class="col-actions">操 作</th>
            </tr>
          </thead>
          <tbody id="ip-tbody"></tbody>
        </table>
        <div id="table-empty" class="empty-hint hidden">没有找到匹配的记录</div>
      </div>
      <div id="table-footer" class="table-footer">
        <span id="table-count"></span>
        <div class="pagination" id="pagination"></div>
      </div>
    </section>

    <!-- ── Subnet view ────────────────────────────────────────────────── -->
    <section id="page-subnet" class="page">
      <div class="page-title"><h1>网段视图</h1></div>
      <div id="subnet-panels"></div>
    </section>

    <!-- ── Import / Export ───────────────────────────────────────────── -->
    <section id="page-io" class="page">
      <div class="page-title"><h1>导入 / 导出</h1></div>
      <div class="io-grid">
        <!-- Import -->
        <div class="card">
          <div class="card-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            导入 CSV
          </div>
          <div class="card-body">
            <p class="hint-text">兼容 RouterOS / Mikrotik / 爱快等系统 dhcp_static.csv 格式。<br>支持 UTF-8 / GBK 编码自动检测。</p>
            <div class="form-group">
              <label>导入模式</label>
              <select id="import-mode" class="select-full">
                <option value="merge">智能合并（按 IP 去重，更新已有）</option>
                <option value="append">追加（保留全部现有，全部新增）</option>
                <option value="replace">替换（清空现有，全部导入）</option>
              </select>
            </div>
            <div class="drop-zone" id="drop-zone">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span>拖拽 CSV 文件到此，或</span>
              <label class="btn btn-sm btn-outline" style="cursor:pointer">
                选择文件
                <input type="file" id="import-file" accept=".csv,.txt" style="display:none">
              </label>
            </div>
            <div id="import-preview" class="hidden">
              <div class="preview-info" id="preview-info"></div>
              <div class="table-wrap preview-table-wrap">
                <table id="preview-table">
                  <thead><tr id="preview-thead"></tr></thead>
                  <tbody id="preview-tbody"></tbody>
                </table>
              </div>
              <div class="form-actions">
                <button id="btn-confirm-import" class="btn btn-primary">确认导入</button>
                <button id="btn-cancel-import" class="btn btn-outline">取 消</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Export -->
        <div class="card">
          <div class="card-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            导出 CSV
          </div>
          <div class="card-body">
            <p class="hint-text">导出为标准 dhcp_static.csv 格式，可直接导入到 Mikrotik / RouterOS / 爱快等系统。</p>
            <div class="form-group">
              <label>导出范围</label>
              <select id="export-scope" class="select-full">
                <option value="all">全部 IP</option>
                <option value="enabled">仅已启用</option>
              </select>
            </div>
            <button id="btn-export" class="btn btn-primary" style="width:100%">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:1rem;height:1rem"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              下载 dhcp_static.csv
            </button>
            <div class="divider"></div>
            <p class="hint-text">也可直接复制 CSV 内容：</p>
            <button id="btn-copy-csv" class="btn btn-outline" style="width:100%">复制 CSV 内容到剪贴板</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Settings ──────────────────────────────────────────────────── -->
    <section id="page-settings" class="page">
      <div class="page-title"><h1>设 置</h1></div>
      <div class="settings-grid">

        <!-- General -->
        <div class="card">
          <div class="card-header">常规设置</div>
          <div class="card-body">
            <div class="form-group">
              <label>默认网关</label>
              <input type="text" id="set-gateway" class="input-full" value="<?= $defaultGW ?>">
            </div>
            <div class="form-group">
              <label>默认接口</label>
              <input type="text" id="set-iface" class="input-full" value="<?= $defaultIFace ?>">
            </div>
            <div class="form-group">
              <label>Ping 超时 (ms)</label>
              <input type="number" id="set-ping-timeout" class="input-full" min="100" max="10000" step="100" value="<?= (int)($settings['ping_timeout'] ?? 1000) ?>">
            </div>
            <div class="form-group">
              <label>MAC 厂商缓存有效期（月）</label>
              <input type="number" id="set-mac-cache-months" class="input-full" min="1" max="24" step="1" value="<?= (int)($settings['mac_cache_months'] ?? 6) ?>">
            </div>
            <button id="btn-save-settings" class="btn btn-primary">保存设置</button>
          </div>
        </div>

        <!-- Subnet management -->
        <div class="card">
          <div class="card-header">
            网段管理
            <button id="btn-add-subnet" class="btn btn-xs btn-outline ml-auto">+ 添加网段</button>
          </div>
          <div class="card-body">
            <div id="subnet-list"></div>
          </div>
        </div>

        <!-- Password -->
        <div class="card">
          <div class="card-header">修改密码</div>
          <div class="card-body">
            <div id="pw-must-change-notice" class="<?= $mustChange ? 'alert alert-warning' : 'hidden' ?>">
              ⚠ 首次登录，请修改默认密码
            </div>
            <div class="form-group">
              <label>当前密码</label>
              <input type="password" id="pw-current" class="input-full" <?= $mustChange ? 'disabled placeholder="（首次登录免填）"' : '' ?>>
            </div>
            <div class="form-group">
              <label>新密码（至少 6 位）</label>
              <input type="password" id="pw-new" class="input-full">
            </div>
            <div class="form-group">
              <label>确认新密码</label>
              <input type="password" id="pw-confirm" class="input-full">
            </div>
            <button id="btn-change-pw" class="btn btn-primary">修改密码</button>
          </div>
        </div>

        <?php if ($role === 'admin'): ?>
        <!-- User management -->
        <div class="card">
          <div class="card-header">
            用户管理
            <button id="btn-add-user" class="btn btn-xs btn-outline ml-auto">+ 添加用户</button>
          </div>
          <div class="card-body">
            <div id="user-list"></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Cache management -->
        <div class="card">
          <div class="card-header">缓存管理</div>
          <div class="card-body">
            <div class="form-group">
              <label style="color:var(--fg2)">MAC 厂商缓存</label>
              <p style="font-size:.82rem;color:var(--fg4);margin:.25rem 0 .6rem">查询结果优先读取本地缓存。缓存有效期可在「常规设置」中调整（默认 6 个月）。</p>
              <button id="btn-clear-mac-cache" class="btn btn-outline btn-sm">清除厂商缓存</button>
            </div>
            <div class="form-group" style="margin-top:1rem">
              <label style="color:var(--fg2)">Ping 缓存</label>
              <p style="font-size:.82rem;color:var(--fg4);margin:.25rem 0 .6rem">清除所有已检测的在线状态记录。</p>
              <button id="btn-clear-ping-cache" class="btn btn-outline btn-sm">清除 Ping 缓存</button>
            </div>
          </div>
        </div>

      </div>
    </section>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ═══════════ MODALS ═════════════════════════════════════════════════════ -->

<!-- IP Form Modal -->
<div id="modal-ip" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-ip-title">添加 IP</h3>
      <button class="modal-close icon-btn" data-close="modal-ip">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ip-form-id">
      <div class="form-row">
        <div class="form-group">
          <label>IP 地址 *</label>
          <input type="text" id="ip-form-ip" class="input-full" placeholder="192.168.2.100">
        </div>
        <div class="form-group">
          <label>MAC 地址</label>
          <input type="text" id="ip-form-mac" class="input-full" placeholder="AA:BB:CC:DD:EE:FF">
        </div>
      </div>
      <div class="form-group mac-vendor-row">
        <label>厂 商</label>
        <div id="mac-vendor-hint" class="mac-vendor-hint hidden"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>接口</label>
          <input type="text" id="ip-form-iface" class="input-full" placeholder="lan1">
        </div>
        <div class="form-group">
          <label>主机名</label>
          <input type="text" id="ip-form-hostname" class="input-full" placeholder="hostname">
        </div>
      </div>
      <div class="form-group">
        <label>备 注</label>
        <input type="text" id="ip-form-comment" class="input-full" placeholder="设备描述">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>网 关</label>
          <input type="text" id="ip-form-gw" class="input-full" placeholder="192.168.2.1">
        </div>
        <div class="form-group">
          <label>DNS 1</label>
          <input type="text" id="ip-form-dns1" class="input-full" placeholder="">
        </div>
        <div class="form-group">
          <label>DNS 2</label>
          <input type="text" id="ip-form-dns2" class="input-full" placeholder="">
        </div>
      </div>
      <div class="form-group">
        <label>标 签（逗号分隔）</label>
        <input type="text" id="ip-form-tags" class="input-full" placeholder="IoT, 服务器">
      </div>
      <div class="form-group">
        <label>备忘录</label>
        <textarea id="ip-form-notes" class="input-full" rows="2" placeholder="更多说明…"></textarea>
      </div>
      <div class="form-group form-inline">
        <label><input type="checkbox" id="ip-form-enabled" checked> 启用此条目</label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-close="modal-ip">取 消</button>
      <button id="btn-save-ip" class="btn btn-primary">保 存</button>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div id="modal-confirm" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3 id="confirm-title">确认操作</h3>
      <button class="modal-close icon-btn" data-close="modal-confirm">✕</button>
    </div>
    <div class="modal-body">
      <p id="confirm-msg"></p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-close="modal-confirm">取 消</button>
      <button id="btn-confirm-ok" class="btn btn-danger">确 认</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast-container"></div>

<!-- Pass config to JS -->
<script>
window.APP = {
  csrf:        <?= json_encode($csrf) ?>,
  username:    <?= json_encode($username) ?>,
  role:        <?= json_encode($role) ?>,
  mustChange:  <?= json_encode($mustChange) ?>,
  defaultGW:   <?= json_encode($settings['default_gateway'] ?? '192.168.2.1') ?>,
  defaultIFace:<?= json_encode($settings['default_interface'] ?? 'lan1') ?>,
};
</script>
<script src="assets/app.js"></script>
</body>
</html>
