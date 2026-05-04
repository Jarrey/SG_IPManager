/* ════════════════════════════════════════════════════════════════════════════
   索格 IPManager – Frontend Application
   ════════════════════════════════════════════════════════════════════════════ */
'use strict';

const App = (() => {

  // ── Config ────────────────────────────────────────────────────────────────
  const cfg = window.APP || {};

  // ── State ─────────────────────────────────────────────────────────────────
  let state = {
    ips:          [],
    filteredIPs:  [],
    page:         1,
    pageSize:     50,
    sort:         'ip_addr',
    sortDir:      'asc',
    search:       '',
    filterStatus: '',
    filterEnabled:'',
    filterIface:  '',
    selected:     new Set(),
    pingQueue:           [],
    pingRunning:         false,
    pingCancel:          false,
    pingRestartPending:  false,
    currentPage:  'overview',
    settings:     {},
    settingsLoaded: false,
    stats:        {},
    vendorCache:  {},   // oui(6-char-hex) → vendor string
  };

  // ── Device-type icons ────────────────────────────────────────────────────
  const DEVICE_ICONS = {
    phone:   { color: '#3b82f6', title: '手机/移动设备',
      svg: '<path d="M17 2H7a2 2 0 00-2 2v16a2 2 0 002 2h10a2 2 0 002-2V4a2 2 0 00-2-2zm-5 17a1 1 0 110-2 1 1 0 010 2z"/>' },
    tablet:  { color: '#6366f1', title: '平板设备',
      svg: '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18" stroke-width="3"/>' },
    laptop:  { color: '#8b5cf6', title: '笔记本/电脑',
      svg: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M0 21h24" stroke-width="2"/>' },
    server:  { color: '#06b6d4', title: '服务器/NAS',
      svg: '<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><circle cx="6" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="6" cy="18" r="1.5" fill="currentColor" stroke="none"/>' },
    tv:      { color: '#f59e0b', title: '智能电视',
      svg: '<rect x="2" y="7" width="20" height="15" rx="2"/><path d="M17 2l-5 5-5-5"/>' },
    gamepad: { color: '#ec4899', title: '游戏设备',
      svg: '<rect x="2" y="6" width="20" height="12" rx="5"/><path d="M6 12h4M8 10v4" stroke-linecap="round"/><circle cx="17" cy="11" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="13" r="1.2" fill="currentColor" stroke="none"/>' },
    speaker: { color: '#10b981', title: '音箱/智能音响',
      svg: '<rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="14" r="4"/><circle cx="12" cy="14" r="1.5" fill="currentColor" stroke="none"/><line x1="9" y1="6" x2="15" y2="6" stroke-linecap="round"/>' },
    router:  { color: '#f97316', title: '路由器/网络设备',
      svg: '<rect x="3" y="8" width="18" height="12" rx="2"/><line x1="7" y1="8" x2="7" y2="2" stroke-linecap="round"/><line x1="12" y1="8" x2="12" y2="1" stroke-linecap="round"/><line x1="17" y1="8" x2="17" y2="2" stroke-linecap="round"/><circle cx="6" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="18" cy="12" r="1.2" fill="currentColor" stroke="none"/>' },
    camera:  { color: '#64748b', title: '摄像头',
      svg: '<path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/>' },
    printer: { color: '#94a3b8', title: '打印机',
      svg: '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>' },
    iot:     { color: '#84cc16', title: 'IoT/智能家居',
      svg: '<path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>' },
    device:  { color: '#475569', title: '未知设备',
      svg: '<rect x="7" y="7" width="10" height="10" rx="2"/><path d="M9 3v2M12 3v2M15 3v2M9 19v2M12 19v2M15 19v2M3 9h2M3 12h2M3 15h2M19 9h2M19 12h2M19 15h2"/><path d="M11 10.4a1.7 1.7 0 113 1.1c-.6.5-1.3.9-1.3 1.8"/><line x1="12.5" y1="15.2" x2="12.51" y2="15.2" stroke-width="2.6"/>' },
  };

  function detectDeviceType(vendor, comment) {
    const t = ((vendor || '') + ' ' + (comment || '')).toLowerCase();
    if (/playstation|ps[345]|xbox|nintendo|switch|gameshell|steam.*link/i.test(t)) return 'gamepad';
    if (/camera|cam|摄像|hikvision|dahua|axis|hanwha/i.test(t)) return 'camera';
    if (/\btv\b|television|电视|smart.?tv|fire.?tv|apple.?tv|chromecast|天猫魔盒|小米盒子/i.test(t)) return 'tv';
    if (/printer|打印/i.test(t)) return 'printer';
    if (/speaker|音响|音箱|echo|homepod|bose|sonos|harman/i.test(t)) return 'speaker';
    if (/router|gateway|access.?point|access point|\bap\b|路由|mesh|ubiquiti|mikrotik|cisco|tp.?link|netgear|asus.?rt|openwrt|istore|wifi|zyxel/i.test(t)) return 'router';
    if (/nas|synology|qnap|群晖|server|服务器|proxmox|unraid|truenas/i.test(t)) return 'server';
    if (/raspberry|orange.?pi|banana.?pi|arduino|esp8266|esp32/i.test(t)) return 'server';
    if (/macbook|thinkpad|laptop|notebook|surface|mac|windows|笔记本/i.test(t)) return 'laptop';
    if (/ipad|kindle|tablet|平板/i.test(t)) return 'tablet';
    if (/插座|socket|plug|smart.?home|iot|智能灯|灯泡|开关|风扇|bulb|lamp|暖气/i.test(t)) return 'iot';
    if (/iphone|android|mobile|phone|手机|smartphone|oppo|vivo|huawei|xiaomi|samsung|oneplus|pixel|realme|honor|motorola|nokia/i.test(t)) return 'phone';
    // Vendor-only fallback
    if (/apple inc/i.test(vendor))                            return 'phone';
    if (/samsung electronics/i.test(vendor))                  return 'phone';
    if (/raspberry pi/i.test(vendor))                         return 'server';
    if (/tp-link|ubiquiti|mikrotik|cisco|netgear|zyxel/i.test(vendor)) return 'router';
    return 'device';
  }

  function buildDeviceIcon(vendor, comment) {
    const type = detectDeviceType(vendor, comment);
    const def  = DEVICE_ICONS[type] || DEVICE_ICONS.device;
    return `<svg class="device-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:${def.color}" title="${def.title}">${def.svg}</svg>`;
  }

  // ── Async vendor lookup ──────────────────────────────────────────────────
  let _vendorLookupTimer = null;

  function scheduleVendorLookup() {
    clearTimeout(_vendorLookupTimer);
    _vendorLookupTimer = setTimeout(runVendorLookup, 120);
  }

  async function runVendorLookup() {
    // Collect unique OUIs from currently rendered rows
    const rows = document.querySelectorAll('#ip-tbody tr[data-id]');
    const pending = [];
    rows.forEach(row => {
      const macEl = row.querySelector('.mac-raw');
      if (!macEl) return;
      const mac = macEl.dataset.mac || '';
      if (!mac) return;
      const oui = mac.replace(/[^a-fA-F0-9]/g, '').toUpperCase().slice(0, 6);
      if (oui.length < 6) return;
      if (state.vendorCache[oui] !== undefined) {
        // Already cached — refresh icons that are still showing spinner
        applyVendorToRow(row, oui, state.vendorCache[oui], mac);
      } else {
        pending.push({ row, mac, oui });
      }
    });

    // De-duplicate OUIs
    const seen = new Set();
    const unique = pending.filter(({ oui }) => {
      if (seen.has(oui)) return false;
      seen.add(oui);
      return true;
    });

    // Fetch sequentially with a small delay to respect rate limits
    for (const { oui, mac } of unique) {
      try {
        const r = await api('lookup_mac', { mac });
        if (r?.error === 'rate_limited') {
          await new Promise(res => setTimeout(res, 3000)); // back off on rate limit
          continue;
        }
        if (r?.success !== undefined) {
          const vendor = r.vendor || '';
          state.vendorCache[oui] = vendor;
          // Apply to all rows with this OUI
          document.querySelectorAll(`#ip-tbody tr[data-id] .mac-raw[data-oui="${oui}"]`).forEach(el => {
            applyVendorToRow(el.closest('tr'), oui, vendor, mac);
          });
        }
      } catch (_) { /* ignore network errors */ }
      await new Promise(res => setTimeout(res, 1200)); // ≥1 s between requests (macvendors.com free tier)
    }
  }

  function applyVendorToRow(row, oui, vendor, mac) {
    if (!row) return;
    const vendorCell = row.querySelector('.col-vendor');
    const comment    = row.querySelector('.col-comment')?.textContent || '';
    if (!vendorCell) return;
    const label = getVendorLabel(vendor, comment);
    vendorCell.innerHTML = `<div class="vendor-cell">
      <div class="device-icon-wrap">${buildDeviceIcon(vendor, comment)}</div>
      <span class="vendor-name" title="${vendor.replace(/"/g,'&quot;')}">${label}</span>
    </div>`;
  }

  function truncateVendor(v) {
    return v.length > 22 ? v.slice(0, 20) + '…' : v;
  }

  // Returns vendor name if known, else the device-type label (e.g. '路由器/网络设备')
  function getVendorLabel(vendor, comment) {
    if (vendor) return truncateVendor(vendor);
    const type = detectDeviceType('', comment);
    return (DEVICE_ICONS[type] || DEVICE_ICONS.device).title;
  }

  // ── API ──────────────────────────────────────────────────────────────────
  async function api(action, params = {}, method = 'GET') {
    let url   = `api.php?action=${action}`;
    let opts  = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };

    if (method === 'POST') {
      const fd = new FormData();
      fd.append('csrf', cfg.csrf);
      for (const [k, v] of Object.entries(params)) {
        if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x));
        else fd.append(k, v);
      }
      opts.method = 'POST';
      opts.body   = fd;
    } else {
      const qs = Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
      if (qs) url += '&' + qs;
      opts.method = 'GET';
    }

    const res  = await fetch(url, opts);
    if (res.status === 401) { window.location = 'index.php'; return null; }
    return res.json();
  }

  // ── Toast ────────────────────────────────────────────────────────────────
  function toast(msg, type = 'info', duration = 3000) {
    const icons = {
      success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--green)"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
      error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--red)"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
      info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span class="toast-icon">${icons[type] || icons.info}</span><span>${msg}</span>`;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, duration);
  }

  // ── Modal ────────────────────────────────────────────────────────────────
  function openModal(id) { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }

  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-close]');
    if (btn) closeModal(btn.dataset.close);
    if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
  });

  // ── Confirm dialog ────────────────────────────────────────────────────────
  function confirm(title, msg) {
    return new Promise(resolve => {
      const modal = document.getElementById('modal-confirm');
      const okBtn = document.getElementById('btn-confirm-ok');
      document.getElementById('confirm-title').textContent = title;
      document.getElementById('confirm-msg').textContent   = msg;
      // Ensure button is in default danger state for confirmations
      okBtn.textContent = '确 认';
      okBtn.className   = 'btn btn-danger';
      openModal('modal-confirm');
      function cleanup(result) {
        okBtn.removeEventListener('click', okHandler);
        modal.removeEventListener('click', cancelHandler);
        document.removeEventListener('keydown', escHandler);
        closeModal('modal-confirm');
        resolve(result);
      }
      function okHandler()      { cleanup(true); }
      function cancelHandler(e) {
        if (e.target === modal || e.target.closest('[data-close="modal-confirm"]')) cleanup(false);
      }
      function escHandler(e)    { if (e.key === 'Escape') cleanup(false); }
      okBtn.addEventListener('click', okHandler);
      modal.addEventListener('click', cancelHandler);
      document.addEventListener('keydown', escHandler);
    });
  }

  // ── Navigation ────────────────────────────────────────────────────────────
  function showPage(name) {
    state.currentPage = name;
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const pg = document.getElementById('page-' + name);
    if (pg) pg.classList.add('active');
    document.querySelector(`[data-page="${name}"]`)?.classList.add('active');

    // Close mobile sidebar
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');

    // Load page data
    if (name === 'overview')  loadStats();
    if (name === 'iplist')    loadIPs();
    if (name === 'subnet')    loadSubnets();
    if (name === 'settings')  loadSettings();
  }

  // Sidebar toggle
  document.getElementById('menu-toggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
  });
  document.getElementById('sidebar-overlay').addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
  });
  document.getElementById('sidebar-close').addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
  });

  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', e => {
      const page = item.dataset.page;
      if (!page) return; // External link item (e.g., GitHub)
      e.preventDefault();
      showPage(page);
    });
  });

  // ── Stats ─────────────────────────────────────────────────────────────────
  async function loadStats() {
    const [r, subnetResp, ipsResp] = await Promise.all([
      api('get_stats'),
      api('get_subnets'),
      api('get_ips', { sort: 'ip_addr' }),
    ]);
    if (!r?.success) return;
    state.stats = r.stats;
    document.getElementById('st-total').textContent     = r.stats.total;
    document.getElementById('st-online').textContent    = r.stats.online;
    document.getElementById('st-offline').textContent   = r.stats.offline;
    document.getElementById('st-free').textContent      = r.stats.free;
    document.getElementById('st-unchecked').textContent = r.stats.unchecked;
    document.getElementById('st-disabled').textContent  = r.stats.disabled;
    document.getElementById('last-update-time').textContent = '更新于 ' + new Date().toLocaleTimeString('zh-CN');

    if (ipsResp?.success) {
      if (ipsResp.vendor_cache && typeof ipsResp.vendor_cache === 'object') {
        state.vendorCache = { ...state.vendorCache, ...ipsResp.vendor_cache };
      }
      renderDeviceCategories(ipsResp.data);
      buildRecentChanges(ipsResp.data);
    }

    if (subnetResp?.success) {
      renderSubnetSummary(subnetResp.data);
    }
  }

  function renderDeviceCategories(ips) {
    const counts = {};
    const order = ['phone','tablet','laptop','server','router','camera','printer','iot','device'];
    const colors = {
      phone: '#3b82f6', tablet: '#6366f1', laptop: '#8b5cf6', server: '#06b6d4',
      router: '#f97316', camera: '#64748b', printer: '#94a3b8', iot: '#84cc16', device: '#475569',
    };

    ips.forEach(ip => {
      const mac = (ip.mac || '').replace(/[^a-fA-F0-9]/g, '').toUpperCase();
      const vendor = mac.length >= 6 ? state.vendorCache[mac.slice(0, 6)] || '' : '';
      const type = detectDeviceType(vendor, ip.comment || ip.cl_name || '');
      counts[type] = (counts[type] || 0) + 1;
    });

    const list = document.getElementById('device-category-list');
    if (!list) return;
    const rows = order.filter(type => counts[type]).map(type => {
      const label = DEVICE_ICONS[type]?.title || '其他';
      return `<div class="overview-item"><span class="overview-dot" style="background:${colors[type] || 'var(--fg4)'}"></span><span class="overview-item-name">${esc(label)}</span><span class="overview-item-count">${counts[type]}</span></div>`;
    });
    if (!rows.length) {
      list.innerHTML = '<div class="empty-hint">暂无设备类型数据</div>';
      return;
    }
    list.innerHTML = rows.join('');
  }

  function renderSubnetSummary(subnets) {
    const list = document.getElementById('subnet-summary-list');
    if (!list) return;
    if (!Array.isArray(subnets) || subnets.length === 0) {
      list.innerHTML = '<div class="empty-hint">暂无网段配置</div>';
      return;
    }
    const rows = subnets.map(sn => {
      const s = sn.subnet;
      const usedByIP = {};
      sn.used.forEach(u => { usedByIP[u.ip_addr] = u; });
      const parts = s.network.split('.');
      const base = parts.slice(0, 3).join('.');
      const cells = [];
      for (let i = s.range_start; i <= s.range_end; i++) {
        const ipStr = `${base}.${i}`;
        const u = usedByIP[ipStr];
        const cls = u ? (u.enabled === 'no' ? 'used-disabled' : `used-${u.status}`) : 'free';
        cells.push(`<span class="overview-subnet-cell ${cls}" title="${esc(ipStr)}"></span>`);
      }
      return `<div class="subnet-summary-row">
        <div class="subnet-summary-title"><span>${esc(s.name)}</span><span>${esc(s.network)}/${esc(s.prefix)}</span></div>
        <div class="overview-subnet-grid">${cells.join('')}</div>
        <div class="subnet-summary-stats">
          <span>在线 ${sn.used.filter(u => u.status === 'online').length}</span>
          <span>离线 ${sn.used.filter(u => u.status === 'offline').length}</span>
          <span>未检测 ${sn.used.filter(u => u.status === 'unchecked').length}</span>
          <span>已禁用 ${sn.used.filter(u => u.enabled === 'no').length}</span>
          <span>空闲 ${sn.free_count}</span>
        </div>
      </div>`;
    });
    list.innerHTML = rows.join('');
  }

  // Stat card click actions
  document.querySelectorAll('.stat-card[data-action]').forEach(card => {
    card.addEventListener('click', () => {
      const action = card.dataset.action;
      if (action === 'filter-all')     { state.filterStatus = ''; showPage('iplist'); }
      else if (action === 'filter-online')  { state.filterStatus = 'online';  showPage('iplist'); }
      else if (action === 'filter-offline') { state.filterStatus = 'offline'; showPage('iplist'); }
      else if (action === 'show-free') showPage('subnet');
    });
  });

  // ── IP List ───────────────────────────────────────────────────────────────
  async function loadIPs() {
    const params = {
      q:       state.search,
      status:  state.filterStatus,
      enabled: state.filterEnabled,
      iface:   state.filterIface,
      sort:    state.sort,
      dir:     state.sortDir,
    };
    const r = await api('get_ips', params);
    if (!r?.success) return;
    if (r.vendor_cache && typeof r.vendor_cache === 'object') {
      // Preload server-side vendor cache so table can render vendor names instantly.
      state.vendorCache = { ...state.vendorCache, ...r.vendor_cache };
    }
    state.ips = r.data;
    state.filteredIPs = r.data;
    renderTable();
    updateInterfaces(r);
    syncFilterUI();
  }

  function syncFilterUI() {
    const sel = document.getElementById('filter-status');
    if (sel) sel.value = state.filterStatus;
    const enSel = document.getElementById('filter-enabled');
    if (enSel) enSel.value = state.filterEnabled;
  }

  function updateInterfaces(r) {
    const sel = document.getElementById('filter-iface');
    if (!sel || !r?.data) return;
    const cur = sel.value;
    const ifaces = [...new Set(r.data.map(ip => ip.interface).filter(Boolean))].sort();
    sel.innerHTML = '<option value="">全部接口</option>';
    ifaces.forEach(iface => {
      const opt = document.createElement('option');
      opt.value = iface;
      opt.textContent = iface;
      if (iface === cur) opt.selected = true;
      sel.appendChild(opt);
    });
  }

  function renderTable() {
    const tbody = document.getElementById('ip-tbody');
    const empty = document.getElementById('table-empty');
    const total = state.filteredIPs.length;

    const start = (state.page - 1) * state.pageSize;
    const slice = state.filteredIPs.slice(start, start + state.pageSize);

    if (total === 0) {
      tbody.innerHTML = '';
      empty.classList.remove('hidden');
    } else {
      empty.classList.add('hidden');
      tbody.innerHTML = slice.map(ip => renderRow(ip)).join('');
    }

    document.getElementById('table-count').textContent = `共 ${total} 条记录`;
    renderPagination(total);

    // Re-bind row actions
    tbody.querySelectorAll('[data-action]').forEach(el => {
      el.addEventListener('click', handleTableAction);
    });
    tbody.querySelectorAll('.toggle input').forEach(tog => {
      tog.addEventListener('change', async function () {
        const id = parseInt(this.closest('tr').dataset.id);
        const r  = await api('toggle_ip', { id }, 'POST');
        if (r?.success) { toast('状态已更新', 'success'); loadIPs(); loadStats(); }
        else toast(r?.error || '操作失败', 'error');
      });
    });
    tbody.querySelectorAll('.copy-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        navigator.clipboard?.writeText(btn.dataset.copy).then(() => toast('已复制', 'success', 1500));
      });
    });
    // Row select
    tbody.querySelectorAll('.row-check').forEach(cb => {
      cb.addEventListener('change', function () {
        const id = parseInt(this.closest('tr').dataset.id);
        if (this.checked) state.selected.add(id); else state.selected.delete(id);
        updateBulkUI();
      });
    });

    // Async MAC vendor lookup for visible rows
    scheduleVendorLookup();
  }

  function renderRow(ip) {
    const status   = ip.status || 'unknown';
    const dotClass = status === 'online' ? 's-online' : status === 'offline' ? 's-offline' : 's-unknown';
    const dotTitle = status === 'online' ? '在线' : status === 'offline' ? '离线' : '未检测';
    const statusSVG = status === 'online'
      ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`
      : status === 'offline'
      ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`
      : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;

    const pingTimeStr = ip.ping_time != null ? ` ${ip.ping_time}ms` : '';
    const methodStr   = ip.ping_method ? ` · ${ip.ping_method}` : '';
    const lastCheckStr = ip.last_check ? `${ip.last_check.slice(11,16)}${pingTimeStr}${methodStr}` : '';

    const tags = (ip.tags || []).filter(Boolean).map(t => `<span class="tag">${esc(t)}</span>`).join('');
    const comment = [esc(ip.comment || ''), esc(ip.cl_name || '')].filter(Boolean).join(' · ');
    const selected = state.selected.has(ip.id) ? 'selected' : '';
    const disabled = ip.enabled === 'no' ? 'disabled-row' : '';

    return `
<tr data-id="${ip.id}" class="${selected} ${disabled}">
  <td class="col-check"><input type="checkbox" class="row-check" ${state.selected.has(ip.id) ? 'checked' : ''}></td>
  <td class="col-status">
    <div class="status-dot ${dotClass}" id="sdot-${ip.id}" title="${dotTitle}${lastCheckStr}">${statusSVG}</div>
  </td>
  <td class="col-ip">
    <div class="ip-cell">
      <span>${esc(ip.ip_addr)}</span>
      <button class="copy-btn" data-copy="${esc(ip.ip_addr)}" title="复制 IP">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
      </button>
    </div>
  </td>
  <td class="col-mac">
    <div class="ip-cell">
      <span class="mac-raw" data-mac="${esc(ip.mac || '')}" data-oui="${(ip.mac||'').replace(/[^a-fA-F0-9]/gi,'').toUpperCase().slice(0,6)}">${esc(ip.mac || '—')}</span>
      ${ip.mac ? `<button class="copy-btn" data-copy="${esc(ip.mac)}" title="复制 MAC"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg></button>` : ''}
    </div>
  </td>
  <td class="col-vendor">
    ${ip.mac ? (() => {
      const oui   = (ip.mac.replace(/[^a-fA-F0-9]/gi,'').toUpperCase().slice(0,6));
      const v     = state.vendorCache[oui] !== undefined ? state.vendorCache[oui] : null;
      if (v === null) {
        // Still loading — show spinner, async lookup will update it
        return `<div class="vendor-cell vendor-loading">
          <svg class="device-type-icon s-loading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--fg4)"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
          <span class="vendor-name" style="color:var(--fg4)">...</span>
        </div>`;
      }
      return `<div class="vendor-cell">
        <div class="device-icon-wrap">${buildDeviceIcon(v, ip.comment || '')}</div>
        <span class="vendor-name" title="${esc(v)}">${getVendorLabel(v, ip.comment || '')}</span>
      </div>`;
    })() : ''}
  </td>
  <td class="col-iface">${esc(ip.interface || '')}</td>
  <td class="col-comment">${comment}${tags ? '<br>' + tags : ''}${ip.notes ? `<br><small style="color:var(--fg3)">${esc(ip.notes)}</small>` : ''}</td>
  <td class="col-gw">${esc(ip.gateway || '')}</td>
  <td class="col-enabled">
    <label class="toggle toggle-wrap">
      <input type="checkbox" ${ip.enabled === 'yes' ? 'checked' : ''}>
      <span class="toggle-slider"></span>
    </label>
  </td>
  <td class="col-actions">
    <button class="btn btn-ghost btn-xs" data-action="ping" data-id="${ip.id}" data-ip="${esc(ip.ip_addr)}" title="检测">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    </button>
    <button class="btn btn-ghost btn-xs" data-action="edit" data-id="${ip.id}" title="编辑">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    </button>
    <button class="btn btn-ghost btn-xs" data-action="delete" data-id="${ip.id}" title="删除" style="color:var(--red)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
    </button>
  </td>
</tr>`;
  }

  async function handleTableAction(e) {
    const btn    = e.currentTarget;
    const action = btn.dataset.action;
    const id     = parseInt(btn.dataset.id);
    const ipStr  = btn.dataset.ip;

    if (action === 'ping') {
      pingSingleInRow(id, ipStr, btn);
    } else if (action === 'edit') {
      openIPForm(id);
    } else if (action === 'delete') {
      const ok = await confirm('删除确认', `确定要删除 ${ipStr} 吗？`);
      if (!ok) return;
      const r = await api('delete_ip', { id }, 'POST');
      if (r?.success) { toast('已删除', 'success'); loadIPs(); loadStats(); }
      else toast(r?.error || '删除失败', 'error');
    }
  }

  async function pingSingleInRow(id, ip, btn) {
    const dot = document.getElementById(`sdot-${id}`);
    const loadingSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>';
    const unknownSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    if (dot) { dot.className = 'status-dot s-loading'; dot.innerHTML = loadingSvg; }
    let r;
    try {
      r = await api('ping_ip', { ip }, 'GET');
    } catch(e) {
      if (dot) { dot.className = 'status-dot s-unknown'; dot.innerHTML = unknownSvg; }
      toast(`${ip}: 检测请求失败`, 'error');
      return;
    }
    if (r?.success) {
      const cls   = r.online ? 's-online' : 's-offline';
      const label = r.online ? '在线' : '离线';
      const svg   = r.online
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
      if (dot) { dot.className = `status-dot ${cls}`; dot.title = `${label} ${r.time ?? ''}ms`; dot.innerHTML = svg; }
      const methodLabel = r.method && r.method !== 'ping' ? ` · ${r.method}` : '';
      toast(`${ip}: ${label}${r.time != null ? ' (' + r.time + 'ms)' : ''}${methodLabel}`, r.online ? 'success' : 'error', 4000);
    }
    loadStats();
  }

  // Sort
  document.querySelectorAll('th.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const col = th.dataset.col;
      if (state.sort === col) state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
      else { state.sort = col; state.sortDir = 'asc'; }
      document.querySelectorAll('th.sortable').forEach(t => t.classList.remove('sort-asc','sort-desc'));
      th.classList.add('sort-' + state.sortDir);
      state.page = 1;
      loadIPs();
    });
  });

  // Search
  let searchTimer;
  document.getElementById('global-search').addEventListener('input', e => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.search = e.target.value;
      state.page   = 1;
      if (state.currentPage !== 'iplist') showPage('iplist');
      else loadIPs();
    }, 250);
  });

  // Filters
  document.getElementById('filter-status')?.addEventListener('change', e => {
    state.filterStatus = e.target.value; state.page = 1; loadIPs();
  });
  document.getElementById('filter-enabled')?.addEventListener('change', e => {
    state.filterEnabled = e.target.value; state.page = 1; loadIPs();
  });
  document.getElementById('filter-iface')?.addEventListener('change', e => {
    state.filterIface = e.target.value; state.page = 1; loadIPs();
  });

  // Select all
  document.getElementById('select-all')?.addEventListener('change', function () {
    const slice = state.filteredIPs.slice((state.page-1)*state.pageSize, state.page*state.pageSize);
    if (this.checked) slice.forEach(ip => state.selected.add(ip.id));
    else slice.forEach(ip => state.selected.delete(ip.id));
    renderTable();
    updateBulkUI();
  });

  function updateBulkUI() {
    const btn = document.getElementById('bulk-delete-btn');
    if (!btn) return;
    if (state.selected.size > 0) { btn.classList.remove('hidden'); btn.textContent = `删除所选 (${state.selected.size})`; }
    else btn.classList.add('hidden');
  }

  document.getElementById('bulk-delete-btn')?.addEventListener('click', async () => {
    if (!state.selected.size) return;
    const ok = await confirm('批量删除', `确定删除选中的 ${state.selected.size} 条记录吗？`);
    if (!ok) return;
    const r = await api('bulk_delete', { ids: [...state.selected].join(',') }, 'POST');
    if (r?.success) {
      state.selected.clear();
      toast(`已删除 ${r.deleted} 条记录`, 'success');
      loadIPs(); loadStats();
    } else toast(r?.error || '删除失败', 'error');
  });

  function renderPagination(total) {
    const pages = Math.max(1, Math.ceil(total / state.pageSize));
    const pag   = document.getElementById('pagination');
    if (!pag) return;
    if (pages <= 1) { pag.innerHTML = ''; return; }

    let html = `<button class="page-btn" ${state.page <= 1 ? 'disabled' : ''} data-p="${state.page-1}">‹</button>`;
    const start = Math.max(1, state.page - 2);
    const end   = Math.min(pages, state.page + 2);
    if (start > 1) html += `<button class="page-btn" data-p="1">1</button>${start > 2 ? '<span style="color:var(--fg4);padding:0 .25rem">…</span>' : ''}`;
    for (let i = start; i <= end; i++) {
      html += `<button class="page-btn ${i === state.page ? 'active' : ''}" data-p="${i}">${i}</button>`;
    }
    if (end < pages) html += `${end < pages - 1 ? '<span style="color:var(--fg4);padding:0 .25rem">…</span>' : ''}<button class="page-btn" data-p="${pages}">${pages}</button>`;
    html += `<button class="page-btn" ${state.page >= pages ? 'disabled' : ''} data-p="${state.page+1}">›</button>`;
    pag.innerHTML = html;
    pag.querySelectorAll('.page-btn[data-p]').forEach(btn => {
      btn.addEventListener('click', () => { state.page = parseInt(btn.dataset.p); renderTable(); });
    });
  }

  // ── IP Form ────────────────────────────────────────────────────────────────
  function openIPForm(id = null) {
    const title = document.getElementById('modal-ip-title');
    if (id) {
      const ip = state.ips.find(x => x.id === id);
      if (!ip) return;
      title.textContent = '编辑 IP';
      document.getElementById('ip-form-id').value       = ip.id;
      document.getElementById('ip-form-ip').value       = ip.ip_addr || '';
      document.getElementById('ip-form-mac').value      = ip.mac || '';
      document.getElementById('ip-form-iface').value    = ip.interface || '';
      document.getElementById('ip-form-hostname').value = ip.cl_name || '';
      document.getElementById('ip-form-comment').value  = ip.comment || '';
      document.getElementById('ip-form-gw').value       = ip.gateway || '';
      document.getElementById('ip-form-dns1').value     = ip.dns1 || '';
      document.getElementById('ip-form-dns2').value     = ip.dns2 || '';
      document.getElementById('ip-form-tags').value     = (ip.tags || []).join(', ');
      document.getElementById('ip-form-notes').value    = ip.notes || '';
      document.getElementById('ip-form-enabled').checked= ip.enabled === 'yes';
    } else {
      title.textContent = '添加 IP';
      document.getElementById('ip-form-id').value       = '';
      document.getElementById('ip-form-ip').value       = '';
      document.getElementById('ip-form-mac').value      = '';
      document.getElementById('ip-form-iface').value    = cfg.defaultIFace || 'lan1';
      document.getElementById('ip-form-hostname').value = '';
      document.getElementById('ip-form-comment').value  = '';
      document.getElementById('ip-form-gw').value       = cfg.defaultGW || '';
      document.getElementById('ip-form-dns1').value     = '';
      document.getElementById('ip-form-dns2').value     = '';
      document.getElementById('ip-form-tags').value     = '';
      document.getElementById('ip-form-notes').value    = '';
      document.getElementById('ip-form-enabled').checked= true;
    }
    openModal('modal-ip');
    // Read the MAC that was just populated into the form field
    const currentMac = document.getElementById('ip-form-mac').value;
    updateFormVendorHint(currentMac);
    setTimeout(() => document.getElementById('ip-form-ip').focus(), 100);
  }

  // MAC input → live vendor hint
  let _formVendorTimer = null;
  document.getElementById('ip-form-mac').addEventListener('input', function () {
    clearTimeout(_formVendorTimer);
    _formVendorTimer = setTimeout(() => updateFormVendorHint(this.value), 500);
  });

  async function updateFormVendorHint(macVal) {
    const hintEl = document.getElementById('mac-vendor-hint');
    if (!hintEl) return;
    const hex = (macVal || '').replace(/[^a-fA-F0-9]/g, '').toUpperCase();
    if (hex.length < 6) {
      hintEl.innerHTML = `${buildDeviceIcon('', '')} <span class="vendor-hint-name">未知设备</span>`;
      hintEl.classList.remove('hidden');
      return;
    }
    const oui = hex.slice(0, 6);

    let vendor = state.vendorCache[oui];
    if (vendor === undefined) {
      hintEl.innerHTML = '<span class="vendor-hint-loading">查询中…</span>';
      hintEl.classList.remove('hidden');
      try {
        const r = await api('lookup_mac', { mac: macVal });
        if (r?.error === 'rate_limited') {
          // Don't cache — show temporary notice
          hintEl.innerHTML = '<span class="vendor-hint-loading">请求过于频繁，稍后再试</span>';
          return;
        }
        vendor = (r?.success) ? (r.vendor || '') : '';
        state.vendorCache[oui] = vendor;
      } catch (_) { vendor = ''; }
    }

    // Get comment from form to aid device detection
    const comment = document.getElementById('ip-form-comment').value.trim();
    const typeLabel = (DEVICE_ICONS[detectDeviceType(vendor, comment)] || DEVICE_ICONS.device).title;
    if (vendor) {
      hintEl.innerHTML = `${buildDeviceIcon(vendor, comment)} <span class="vendor-hint-name">${esc(vendor)}</span> <span style="color:var(--fg4);font-size:.75rem">(${typeLabel})</span>`;
    } else {
      hintEl.innerHTML = `${buildDeviceIcon('', comment)} <span style="color:var(--fg4)">${typeLabel}（未找到厂商）</span>`;
    }
    hintEl.classList.remove('hidden');
  }

  document.getElementById('btn-add-ip').addEventListener('click', () => openIPForm());

  document.getElementById('btn-save-ip').addEventListener('click', async () => {
    const id      = document.getElementById('ip-form-id').value;
    const payload = {
      ip_addr:  document.getElementById('ip-form-ip').value.trim(),
      mac:      document.getElementById('ip-form-mac').value.trim(),
      interface:document.getElementById('ip-form-iface').value.trim(),
      cl_name:  document.getElementById('ip-form-hostname').value.trim(),
      comment:  document.getElementById('ip-form-comment').value.trim(),
      gateway:  document.getElementById('ip-form-gw').value.trim(),
      dns1:     document.getElementById('ip-form-dns1').value.trim(),
      dns2:     document.getElementById('ip-form-dns2').value.trim(),
      tags:     document.getElementById('ip-form-tags').value.trim(),
      notes:    document.getElementById('ip-form-notes').value.trim(),
      enabled:  document.getElementById('ip-form-enabled').checked ? 'yes' : 'no',
    };
    if (id) payload.id = id;

    const action = id ? 'update_ip' : 'add_ip';
    const r      = await api(action, payload, 'POST');
    if (r?.success) {
      toast(id ? '保存成功' : '添加成功', 'success');
      closeModal('modal-ip');
      loadIPs(); loadStats();
    } else toast(r?.error || '保存失败', 'error');
  });

  // ── Check All ─────────────────────────────────────────────────────────────
  document.getElementById('btn-check-all').addEventListener('click', startPingAll);

  async function startPingAll() {
    if (state.pingRunning) {
      // Cancellation in progress — mark for restart once the current run finishes
      if (state.pingCancel) state.pingRestartPending = true;
      return;
    }
    state.pingRestartPending = false;
    state.pingCancel         = false; // ensure clean state from any prior cancel
    const r = await api('get_ips', {});
    if (!r?.success || r.data.length === 0) { toast('没有 IP 可检测', 'info'); return; }

    state.pingQueue   = r.data.map(ip => ({ id: ip.id, ip: ip.ip_addr }));
    state.pingRunning = true;
    let done = 0;
    const total = state.pingQueue.length;

    const wrap      = document.getElementById('ping-progress-wrap');
    const fill      = document.getElementById('ping-progress-fill');
    const summary   = document.getElementById('ping-progress-summary');
    const current   = document.getElementById('ping-progress-current');
    const log       = document.getElementById('ping-progress-log');
    const cancelBtn = document.getElementById('btn-cancel-ping');
    // Restore the inner span structure that textContent calls in previous runs may have destroyed
    summary.innerHTML = '正在检测… <span id="ping-done">0</span> / <span id="ping-total">0</span>';
    const doneTxt   = document.getElementById('ping-done');
    const totTxt    = document.getElementById('ping-total');
    wrap.classList.remove('hidden');
    if (cancelBtn) cancelBtn.disabled = false;
    totTxt.textContent = total;
    doneTxt.textContent = 0;
    current.textContent = '准备开始检测';
    log.innerHTML = '';
    fill.style.width = '0%';

    // switch to overview
    if (state.currentPage !== 'overview') showPage('overview');

    for (const entry of state.pingQueue) {
      if (state.pingCancel) break;
      current.textContent = `正在检测 ${entry.ip}…`;
      let r;
      try {
        r = await api('ping_ip', { ip: entry.ip }, 'GET');
      } catch(e) {
        done++;
        doneTxt.textContent = done;
        fill.style.width = Math.round((done / total) * 100) + '%';
        const ts = new Date().toLocaleTimeString('zh-CN', { hour12: false });
        log.insertAdjacentHTML('beforeend', `<div class="ping-log-item"><span class="ping-log-ts">${esc(ts)}</span><span class="ping-log-ip">${esc(entry.ip)}</span><span class="ping-log-status offline">请求失败</span></div>`);
        log.scrollTop = log.scrollHeight;
        continue;
      }
      done++;
      doneTxt.textContent = done;
      fill.style.width = Math.round((done / total) * 100) + '%';
      const timestamp = new Date().toLocaleTimeString('zh-CN', { hour12: false });
      if (r?.success) {
        const methodLabel = r.method ? ` · ${r.method}` : '';
        const statusText = r.online ? '在线' : '离线';
        current.textContent = `已检测 ${entry.ip}：${statusText}${methodLabel}`;
        const line = `<div class="ping-log-item">
          <span class="ping-log-ts">${esc(timestamp)}</span>
          <span class="ping-log-ip">${esc(entry.ip)}</span>
          <span class="ping-log-status ${r.online ? 'online' : 'offline'}">${statusText}</span>
          <span class="ping-log-method">${esc(r.method || '')}</span>
          <span class="ping-log-time">${r.time != null ? esc(r.time + 'ms') : ''}</span>
        </div>`;
        log.insertAdjacentHTML('beforeend', line);
        log.scrollTop = log.scrollHeight;
      } else {
        current.textContent = `检测失败 ${entry.ip}`;
        const errorLine = `<div class="ping-log-item">
          <span class="ping-log-ts">${esc(timestamp)}</span>
          <span class="ping-log-ip">${esc(entry.ip)}</span>
          <span class="ping-log-status offline">失败</span>
          <span class="ping-log-method">${esc(r?.error || '未知错误')}</span>
        </div>`;
        log.insertAdjacentHTML('beforeend', errorLine);
        log.scrollTop = log.scrollHeight;
      }
    }

    state.pingRunning = false;

    if (state.pingCancel) {
      state.pingCancel = false; // reset so next run starts clean
      // Reset UI to initial state so re-running works cleanly
      wrap.classList.add('hidden');
      fill.style.width = '0%';
      log.innerHTML = '';
      current.textContent = '准备开始检测';
      if (cancelBtn) cancelBtn.disabled = false;
      toast(`检测已取消（已完成 ${done}/${total}）`, 'info');
      // User clicked 检测 while cancel was in progress — restart now
      if (state.pingRestartPending) {
        state.pingRestartPending = false;
        startPingAll();
        return;
      }
    } else {
      if (cancelBtn) cancelBtn.disabled = true;
      summary.textContent = `检测完成 ${done} / ${total}`;
      current.textContent = '检测完成，查看下面结果';
      toast(`检测完成 ${done} 个 IP`, 'success');
    }

    loadStats();
    if (state.currentPage === 'iplist') loadIPs();
    if (state.currentPage === 'subnet') loadSubnets();
  }

  document.getElementById('btn-cancel-ping')?.addEventListener('click', () => {
    if (!state.pingRunning) return;
    state.pingCancel = true;
    const cancelBtn = document.getElementById('btn-cancel-ping');
    if (cancelBtn) cancelBtn.disabled = true;
    const current = document.getElementById('ping-progress-current');
    if (current) current.textContent = '取消中，请等待当前项完成…';
  });

  async function buildRecentChanges(cachedIPs = null) {
    let allIPs = cachedIPs;
    if (!allIPs) {
      const r = await api('get_ips', { sort: 'ip_addr' });
      if (!r?.success) return;
      allIPs = r.data;
    }
    const container = document.getElementById('recent-changes');
    const ips = allIPs.filter(ip => ip.status).sort((a, b) => {
      const ta = a.last_check || '';
      const tb = b.last_check || '';
      return tb.localeCompare(ta);
    }).slice(0, 20);
    if (!ips.length) { container.innerHTML = '<div class="empty-hint">暂无检测记录</div>'; return; }
    container.innerHTML = ips.map(ip => `
      <div class="recent-item">
        <div class="dot dot-${ip.status === 'online' ? 'online' : 'offline'}"></div>
        <span class="recent-ip">${esc(ip.ip_addr)}</span>
        <span class="recent-comment">${esc(ip.comment || ip.cl_name || '—')}</span>
        <span class="recent-time">${esc(ip.last_check?.slice(11,16) || '')}${ip.ping_time != null ? ' · ' + ip.ping_time + 'ms' : ''}</span>
      </div>
    `).join('');
  }

  // ── Subnet View ───────────────────────────────────────────────────────────
  async function loadSubnets() {
    const r = await api('get_subnets');
    if (!r?.success) return;
    const panels = document.getElementById('subnet-panels');
    panels.innerHTML = r.data.map(sn => buildSubnetPanel(sn)).join('');
    // Bind add-IP-from-free buttons
    panels.querySelectorAll('[data-free-ip]').forEach(el => {
      el.addEventListener('click', () => {
        const ip = el.dataset.freeIp;
        openIPForm(); // pre-fill IP
        setTimeout(() => { document.getElementById('ip-form-ip').value = ip; }, 50);
      });
    });
  }

  function buildSubnetPanel(sn) {
    const s = sn.subnet;
    const usedByIP = {};
    sn.used.forEach(u => usedByIP[u.ip_addr] = u);

    const parts   = s.network.split('.');
    const base    = parts.slice(0, 3).join('.');
    const cells   = [];

    for (let i = s.range_start; i <= s.range_end; i++) {
      const ipStr  = base + '.' + i;
      const u      = usedByIP[ipStr];
      if (u) {
        const cls   = u.enabled === 'no' ? 'used-disabled' : `used-${u.status}`;
        const label = u.comment || u.cl_name || '';
        cells.push(`<div class="ip-cell-box ${cls}" title="${esc(ipStr)} ${esc(label)}">${i}<div class="ip-tooltip">${esc(ipStr)}<br>${esc(label) || '—'}</div></div>`);
      } else {
        cells.push(`<div class="ip-cell-box free" data-free-ip="${esc(ipStr)}" title="空闲: ${esc(ipStr)}" style="cursor:pointer">${i}<div class="ip-tooltip">${esc(ipStr)}<br>空闲（点击分配）</div></div>`);
      }
    }

    return `
<div class="subnet-panel">
  <div class="subnet-header">
    <span class="subnet-name">${esc(s.name)}</span>
    <span class="subnet-cidr">${esc(s.network)}/${s.prefix}</span>
    <div class="subnet-stats">
      <span class="sstat"><span class="sstat-dot" style="background:var(--green)"></span> 在线 ${sn.used.filter(u => u.status === 'online').length}</span>
      <span class="sstat"><span class="sstat-dot" style="background:var(--red)"></span> 离线 ${sn.used.filter(u => u.status === 'offline').length}</span>
      <span class="sstat"><span class="sstat-dot" style="background:var(--accent)"></span> 已分配 ${sn.used_count}</span>
      <span class="sstat"><span class="sstat-dot" style="background:var(--bg4)"></span> 空闲 ${sn.free_count}</span>
    </div>
  </div>
  <div class="subnet-grid">${cells.join('')}</div>
</div>`;
  }

  // ── Import / Export ────────────────────────────────────────────────────────
  let importContent = '';
  let importFile    = null;  // raw File object for binary upload

  function setupIO() {
    const dz   = document.getElementById('drop-zone');
    const file = document.getElementById('import-file');

    if (!dz) return;

    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
      e.preventDefault();
      dz.classList.remove('drag-over');
      const f = e.dataTransfer.files[0];
      if (f) readImportFile(f);
    });
    file.addEventListener('change', () => { if (file.files[0]) readImportFile(file.files[0]); });

    document.getElementById('btn-confirm-import')?.addEventListener('click', doImport);
    document.getElementById('btn-cancel-import')?.addEventListener('click', () => {
      document.getElementById('import-preview').classList.add('hidden');
      importContent = '';
      importFile    = null;
    });

    document.getElementById('btn-export')?.addEventListener('click', () => {
      const scope = document.getElementById('export-scope')?.value;
      const url   = `api.php?action=export_csv${scope === 'enabled' ? '&enabled_only=1' : ''}`;
      window.location.href = url;
    });

    document.getElementById('btn-copy-csv')?.addEventListener('click', async () => {
      const r = await fetch('api.php?action=get_ips&sort=ip_addr&dir=asc');
      const data = await r.json();
      if (!data.success) { toast('获取失败', 'error'); return; }
      // Simple CSV build client-side for copy
      const headers = ['id','enabled','interface','ip_addr','mac','cl_name','comment','gateway','dns1','dns2'];
      const lines   = [headers.join(',')];
      data.data.forEach(ip => {
        const row = headers.map(h => {
          let v = String(ip[h] ?? '');
          if (h === 'comment' || h === 'cl_name') v = v.replace(/ /g, '%20');
          return `"${v.replace(/"/g, '""')}"`;
        });
        lines.push(row.join(','));
      });
      await navigator.clipboard.writeText(lines.join('\r\n'));
      toast('已复制 CSV 内容', 'success');
    });
  }

  // Detect encoding from ArrayBuffer: UTF-8 BOM → UTF-8, valid UTF-8 → UTF-8, else GBK
  function detectAndDecodeBuffer(buf) {
    const bytes = new Uint8Array(buf, 0, 4);
    // UTF-8 BOM EF BB BF
    if (bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF) {
      return new TextDecoder('utf-8').decode(buf.slice(3));
    }
    try {
      // strict: throws if not valid UTF-8
      return new TextDecoder('utf-8', { fatal: true }).decode(buf);
    } catch (_) {
      return new TextDecoder('gbk').decode(buf);
    }
  }

  function readImportFile(file) {
    importFile = file;
    const reader = new FileReader();
    reader.onload = e => {
      importContent = detectAndDecodeBuffer(e.target.result);
      previewCSV(importContent);
    };
    reader.readAsArrayBuffer(file);
  }

  function previewCSV(content) {
    const lines   = content.split(/\r?\n/).filter(l => l.trim());
    const headers = lines[0] ? parseCSVLine(lines[0]) : [];
    const rows    = lines.slice(1, 6); // preview first 5 rows

    const thead = document.getElementById('preview-thead');
    const tbody = document.getElementById('preview-tbody');
    const info  = document.getElementById('preview-info');
    const wrap  = document.getElementById('import-preview');

    thead.innerHTML = headers.map(h => `<th>${esc(h)}</th>`).join('');
    tbody.innerHTML = rows.map(line => {
      const cols = parseCSVLine(line);
      return `<tr>${headers.map((_, i) => `<td>${esc(decodeURIComponent(cols[i] || '').replace(/\+/g, ' '))}</td>`).join('')}</tr>`;
    }).join('');
    info.textContent = `共检测到 ${lines.length - 1} 条记录（预览前 5 条）`;
    wrap.classList.remove('hidden');
  }

  function parseCSVLine(line) {
    const result = [];
    let cur = '', inQ = false;
    for (let i = 0; i < line.length; i++) {
      const c = line[i];
      if (c === '"') {
        if (inQ && line[i+1] === '"') { cur += '"'; i++; }
        else inQ = !inQ;
      } else if (c === ',' && !inQ) { result.push(cur.trim()); cur = ''; }
      else cur += c;
    }
    result.push(cur.trim());
    return result;
  }

  async function doImport() {
    if (!importFile && !importContent) return;
    const mode = document.getElementById('import-mode').value;
    const fd   = new FormData();
    fd.append('csrf', cfg.csrf);
    fd.append('mode', mode);
    // Send raw binary file so PHP can do its own encoding detection;
    // fall back to decoded text string when no File object is available.
    if (importFile) {
      fd.append('csv_file', importFile, importFile.name);
    } else {
      fd.append('csv_content', importContent);
    }

    const res  = await fetch('api.php?action=import_csv', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const r    = await res.json();
    if (r.success) {
      toast(`导入成功：新增 ${r.added}，更新 ${r.updated}`, 'success');
      document.getElementById('import-preview').classList.add('hidden');
      importContent = '';
      importFile    = null;
      loadStats();
      if (state.currentPage === 'iplist') loadIPs();
    } else toast(r.error || '导入失败', 'error');
  }

  // ── Settings ──────────────────────────────────────────────────────────────
  async function loadSettings() {
    let r;
    try { r = await api('get_settings'); } catch(e) { toast('加载设置失败', 'error'); return; }
    if (!r?.success) { toast(r?.error || '加载设置失败', 'error'); return; }
    state.settings = r.settings;
    const s = r.settings;
    const gatewayEl = document.getElementById('set-gateway');
    if (gatewayEl) gatewayEl.value = s.default_gateway ?? '';
    const ifaceEl = document.getElementById('set-iface');
    if (ifaceEl) ifaceEl.value = s.default_interface ?? '';
    const pingEl = document.getElementById('set-ping-timeout');
    if (pingEl) pingEl.value = String(s.ping_timeout ?? 1000);
    const macCacheEl = document.getElementById('set-mac-cache-months');
    if (macCacheEl) macCacheEl.value = String(s.mac_cache_months ?? 6);
    const dockerHostRangesEl = document.getElementById('set-docker-host-ranges');
    if (dockerHostRangesEl) dockerHostRangesEl.value = s.docker_host_ranges ?? '';
    const enableArpEl = document.getElementById('set-enable-arp');
    if (enableArpEl) enableArpEl.checked = !!(s.enable_arp);
    renderSubnetList(s.subnets || []);
    state.settingsLoaded = true;
  }

  document.getElementById('btn-save-settings')?.addEventListener('click', async () => {
    // If settings haven't been loaded yet, load them first to avoid overwriting subnets with empty array
    if (!state.settingsLoaded) {
      await loadSettings();
    }
    const payload = {
      default_gateway:   document.getElementById('set-gateway').value.trim(),
      default_interface: document.getElementById('set-iface').value.trim(),
      ping_timeout:      document.getElementById('set-ping-timeout').value,
      mac_cache_months:  document.getElementById('set-mac-cache-months')?.value || '6',
      docker_host_ranges:document.getElementById('set-docker-host-ranges')?.value.trim() || '',
      enable_arp:        document.getElementById('set-enable-arp')?.checked ? '1' : '0',
    };
    // Only include subnets after settings have been loaded to prevent accidental data loss
    if (state.settingsLoaded) {
      const subnets = state.settings.subnets || [];
      payload.subnets = JSON.stringify(subnets);
      // Signal intentional clear when user has deleted all subnets via the UI
      if (subnets.length === 0) payload.clear_subnets = '1';
    }
    const r = await api('save_settings', payload, 'POST');
    if (r?.success) { toast('设置已保存', 'success'); state.settings = r.settings; state.settingsLoaded = true; }
    else toast(r?.error || '保存失败', 'error');
  });

  document.getElementById('btn-clear-mac-cache')?.addEventListener('click', async () => {
    const r = await api('clear_mac_vendor_cache', {}, 'POST');
    if (r?.success) {
      state.vendorCache = {};
      toast('厂商缓存已清除，重新加载列表后将重新查询', 'success');
    } else toast('清除失败', 'error');
  });

  document.getElementById('btn-clear-ping-cache')?.addEventListener('click', async () => {
    const r = await api('clear_ping_cache', {}, 'POST');
    if (r?.success) { toast('Ping 缓存已清除', 'success'); loadIPs(); loadStats(); }
    else toast('清除失败', 'error');
  });

  async function saveSubnets() {
    const subnets = state.settings.subnets || [];
    const payload = { subnets: JSON.stringify(subnets) };
    if (subnets.length === 0) payload.clear_subnets = '1';
    const r = await api('save_settings', payload, 'POST');
    if (r?.success) {
      state.settings = r.settings;
      state.settingsLoaded = true;
      toast('网段已保存', 'success');
    } else {
      toast(r?.error || '保存失败', 'error');
    }
  }

  function renderSubnetList(subnets) {
    const el = document.getElementById('subnet-list');
    if (!el) return;
    if (!subnets.length) { el.innerHTML = '<div class="empty-hint" style="padding:1rem">暂无网段，点击「添加网段」</div>'; return; }
    el.innerHTML = subnets.map((s, i) => `
      <div class="subnet-item">
        <div class="subnet-item-info">
          <div class="subnet-item-name">${esc(s.name)}</div>
          <div class="subnet-item-cidr">${esc(s.network)}/${s.prefix} · 范围 .${s.range_start}–.${s.range_end} · GW ${esc(s.gateway || '—')}</div>
        </div>
        <button class="btn btn-ghost btn-xs" data-sn-edit="${i}" title="编辑">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:1rem;height:1rem"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="btn btn-ghost btn-xs" data-sn-del="${i}" title="删除" style="color:var(--red)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:1rem;height:1rem"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div>
    `).join('');
    el.querySelectorAll('[data-sn-del]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const i = parseInt(btn.dataset.snDel);
        const ok = await confirm('删除网段', `确定删除网段 ${state.settings.subnets[i]?.name} 吗？`);
        if (!ok) return;
        state.settings.subnets.splice(i, 1);
        renderSubnetList(state.settings.subnets);
        await saveSubnets();
      });
    });
    el.querySelectorAll('[data-sn-edit]').forEach(btn => {
      btn.addEventListener('click', () => openSubnetForm(parseInt(btn.dataset.snEdit)));
    });
  }

  document.getElementById('btn-add-subnet')?.addEventListener('click', () => openSubnetForm(null));

  function openSubnetForm(idx) {
    const sn = idx !== null ? state.settings.subnets[idx] : null;
    const vals = {
      name:        sn?.name        || '',
      network:     sn?.network     || '',
      prefix:      sn?.prefix      ?? 24,
      range_start: sn?.range_start ?? 2,
      range_end:   sn?.range_end   ?? 254,
      gateway:     sn?.gateway     || '',
    };
    const html = `
      <div class="form-group"><label>网段名称</label><input id="sn-name" class="input-full" value="${esc(vals.name)}"></div>
      <div class="form-row">
        <div class="form-group"><label>网络地址</label><input id="sn-net" class="input-full" value="${esc(vals.network)}" placeholder="192.168.2.0"></div>
        <div class="form-group"><label>前缀长度</label><input id="sn-prefix" type="number" class="input-full" value="${vals.prefix}" min="8" max="30"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>起始 IP 末段</label><input id="sn-start" type="number" class="input-full" value="${vals.range_start}" min="1" max="254"></div>
        <div class="form-group"><label>结束 IP 末段</label><input id="sn-end" type="number" class="input-full" value="${vals.range_end}" min="1" max="254"></div>
      </div>
      <div class="form-group"><label>网关</label><input id="sn-gw" class="input-full" value="${esc(vals.gateway)}"></div>
    `;
    const snModal = document.getElementById('modal-confirm');
    const snInner = snModal.querySelector('.modal');
    const snOkBtn = document.getElementById('btn-confirm-ok');
    document.getElementById('confirm-title').textContent = idx !== null ? '编辑网段' : '添加网段';
    document.getElementById('confirm-msg').innerHTML     = html;
    snOkBtn.textContent = '保存';
    snOkBtn.className   = 'btn btn-primary';
    snInner.classList.remove('modal-sm'); // wider for form fields
    openModal('modal-confirm');
    function snCleanup() {
      snOkBtn.removeEventListener('click', snOkHandler);
      snModal.removeEventListener('click', snCancelHandler);
      document.removeEventListener('keydown', snEscHandler);
      snInner.classList.add('modal-sm');
      snOkBtn.textContent = '确 认';
      snOkBtn.className   = 'btn btn-danger';
    }
    function snOkHandler() {
      const newSn = {
        id:          sn?.id ?? Date.now(),
        name:        document.getElementById('sn-name').value.trim(),
        network:     document.getElementById('sn-net').value.trim(),
        prefix:      parseInt(document.getElementById('sn-prefix').value),
        range_start: parseInt(document.getElementById('sn-start').value),
        range_end:   parseInt(document.getElementById('sn-end').value),
        gateway:     document.getElementById('sn-gw').value.trim(),
      };
      if (idx !== null) state.settings.subnets[idx] = newSn;
      else state.settings.subnets = [...(state.settings.subnets || []), newSn];
      closeModal('modal-confirm');
      snCleanup();
      renderSubnetList(state.settings.subnets);
      saveSubnets();
    }
    function snCancelHandler(e) {
      if (e.target === snModal || e.target.closest('[data-close="modal-confirm"]')) snCleanup();
    }
    function snEscHandler(e) { if (e.key === 'Escape') snCleanup(); }
    snOkBtn.addEventListener('click', snOkHandler);
    snModal.addEventListener('click', snCancelHandler);
    document.addEventListener('keydown', snEscHandler);
  }

  // Account settings (username + password, combined)
  document.getElementById('btn-save-account')?.addEventListener('click', async () => {
    const newName = document.getElementById('un-new').value.trim();
    const curPwd  = document.getElementById('pw-current').value;
    const newPwd  = document.getElementById('pw-new').value;
    const cfmPwd  = document.getElementById('pw-confirm').value;

    if (!curPwd) { toast('请填写当前密码', 'error'); return; }
    if (!newName && !newPwd) { toast('请至少修改用户名或密码之一', 'error'); return; }
    if (newPwd && newPwd !== cfmPwd) { toast('两次密码不一致', 'error'); return; }

    let relogin = false;
    if (newName) {
      const r = await api('change_username', { new_username: newName, current_password: curPwd }, 'POST');
      if (!r?.success) { toast(r?.error || '用户名修改失败', 'error'); return; }
      relogin = true;
    }

    if (newPwd) {
      const r = await api('change_password', {
        current_password: curPwd,
        new_password:     newPwd,
        confirm_password: cfmPwd,
      }, 'POST');
      if (!r?.success) { toast(r?.error || '密码修改失败', 'error'); return; }
    }

    toast('保存成功' + (relogin ? '，即将重新登录' : ''), 'success');
    ['un-new', 'pw-current', 'pw-new', 'pw-confirm'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    if (relogin) setTimeout(() => { window.location.href = 'logout.php'; }, 1500);
  });

  // ── Interface filter update ───────────────────────────────────────────────
  async function updateIfaceFilter() {
    const r = await api('get_stats');
    if (!r?.success) return;
    const sel = document.getElementById('filter-iface');
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = '<option value="">全部接口</option>';
    (r.interfaces || []).forEach(iface => {
      const opt = document.createElement('option');
      opt.value = iface;
      opt.textContent = iface;
      if (iface === cur) opt.selected = true;
      sel.appendChild(opt);
    });
  }

  // ── Utility ────────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ── Boot ──────────────────────────────────────────────────────────────────
  function openChangePassword() {
    showPage('settings');
    setTimeout(() => { document.getElementById('pw-new')?.focus(); }, 200);
  }

  function init() {
    setupIO();
    showPage('overview');
    updateIfaceFilter();
  }

  init();

  // Public API
  return { showPage, openChangePassword };

})();
