'use strict';
(() => {
  const ROOT = document.getElementById('app');
  const BOOT = JSON.parse(ROOT.dataset.boot);
  const BASE = BOOT.base;
  let S = null, CSRF = null, currentRoute = '', pollTimer = null, lastUnread = -1, hkTimer = null;
  const refCache = {};

  // ---------- helpers ----------
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const nl2br = v => esc(v).replace(/\n/g, '<br>');
  const tz = () => (S && S.app.timezone) || BOOT.timezone || 'UTC';
  const LOC = BOOT.locale || 'en-US', CUR = BOOT.currency || 'USD';
  const toDate = s => s ? new Date(String(s).replace(' ', 'T') + (String(s).length <= 19 ? 'Z' : '')) : null;
  const fmt = (s, o) => { const d = toDate(s); return d ? new Intl.DateTimeFormat(LOC, Object.assign({ timeZone: tz(), month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }, o || {})).format(d) : ''; };
  const fmtDate = s => s ? (String(s).length <= 10 ? new Intl.DateTimeFormat(LOC, { timeZone: 'UTC', month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(s + 'T12:00:00Z')) : fmt(s, { hour: undefined, minute: undefined, year: 'numeric' })) : '';
  const rel = s => {
    const d = toDate(s); if (!d) return '';
    const m = Math.round((d - Date.now()) / 60000), a = Math.abs(m);
    const t = a < 1 ? 'now' : a < 60 ? `${a} min` : a < 1440 ? `${Math.round(a / 60)} h` : `${Math.round(a / 1440)} d`;
    return t === 'now' ? 'just now' : m < 0 ? `${t} ago` : `in ${t}`;
  };
  const localInput = s => { // UTC storage to property-local datetime-local value
    const d = toDate(s); if (!d) return '';
    const p = Object.fromEntries(new Intl.DateTimeFormat(LOC, { timeZone: tz(), year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(d).map(x => [x.type, x.value]));
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
  };
  const money = n => n == null || n === '' ? '' : new Intl.NumberFormat(LOC, { style: 'currency', currency: CUR }).format(+n);
  const mins = n => n == null ? '–' : n < 60 ? `${Math.round(n)} min` : n < 1440 ? `${(n / 60).toFixed(1)} h` : `${(n / 1440).toFixed(1)} d`;
  const initials = n => String(n || '?').split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase();
  const rank = () => S.user.rank;
  const can = r => !S.user.read_only && S.user.rank >= r;
  const dept = id => S.departments.find(d => +d.id === +id);
  const deptChip = id => { const d = dept(id); return d ? `<span class="dept" style="border-left:3px solid ${esc(d.colour || '#4E9AA0')}">${esc(d.icon || '')} ${esc(d.name)}</span>` : ''; };
  const userName = id => (S.users.find(u => +u.id === +id) || {}).name || '';
  const roomNum = id => (S.rooms.find(r => +r.id === +id) || {}).number || '';
  const locName = id => (S.locations.find(l => +l.id === +id) || {}).name || '';
  const catName = id => (S.categories.find(c => +c.id === +id) || {}).name || '';
  const opt = (k, v) => (S.opt[k] || {})[v] || v || '';
  const statusBadge = s => `<span class="badge s-${esc(s)}">${esc(opt('ticket_status', s))}</span>`;
  const prioBadge = p => `<span class="badge p-${esc(p)}">${esc(opt('priority', p))}</span>`;
  const options = (map, sel, blank) => (blank !== undefined ? `<option value="">${esc(blank)}</option>` : '') + Object.entries(map).map(([k, v]) => `<option value="${esc(k)}"${String(sel) === String(k) ? ' selected' : ''}>${esc(v)}</option>`).join('');
  const listOptions = (rows, sel, blank, labelKey = 'name') => (blank !== undefined ? `<option value="">${esc(blank)}</option>` : '') + rows.map(r => `<option value="${esc(r.id)}"${String(sel) === String(r.id) ? ' selected' : ''}>${esc(r[labelKey] ?? r.label)}</option>`).join('');
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  function toast(msg, err) {
    let w = $('.toast-wrap'); if (!w) { w = document.createElement('div'); w.className = 'toast-wrap'; document.body.appendChild(w); }
    const t = document.createElement('div'); t.className = 'toast' + (err ? ' err' : ''); t.textContent = msg; w.appendChild(t);
    setTimeout(() => t.remove(), err ? 6000 : 3000);
  }

  async function api(method, path, body, opts = {}) {
    const headers = { Accept: 'application/json' };
    if (CSRF) headers['X-CSRF-Token'] = CSRF;
    let payload;
    if (body instanceof FormData) payload = body;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    const r = await fetch(BASE + '/api' + path, { method, headers, body: payload, credentials: 'same-origin' });
    let j = {};
    try { j = await r.json(); } catch (e) { /* non-JSON */ }
    if (r.status === 401 && !opts.quiet) { S = null; renderLogin('Your session ended. Sign in again.'); throw new Error('Signed out'); }
    if (r.status === 419) { toast('Security token expired, reloading', true); setTimeout(() => location.reload(), 900); throw new Error('CSRF'); }
    if (!r.ok) { const e = new Error(j.error || `Request failed (${r.status})`); e.status = r.status; e.data = j; throw e; }
    return j;
  }
  const run = async (fn, okMsg) => { try { const r = await fn(); if (okMsg) toast(okMsg); return r; } catch (e) { if (e.message !== 'Signed out') toast(e.message, true); throw e; } };

  function modal(title, html, onMount) {
    const bg = document.createElement('div'); bg.className = 'modal-bg';
    bg.innerHTML = `<div class="modal" role="dialog" aria-modal="true"><h2><span class="sp">${esc(title)}</span><button class="btn ghost sm" data-x aria-label="Close">✕</button></h2><div class="mb-body">${html}</div></div>`;
    document.body.appendChild(bg);
    const close = () => bg.remove();
    bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-x]')) close(); });
    if (onMount) onMount($('.mb-body', bg), close);
    return close;
  }

  async function refs(entity) {
    if (refCache[entity]) return refCache[entity];
    if (entity === 'users') return (refCache.users = S.users.map(u => ({ id: u.id, label: u.name })));
    if (entity === 'departments') return (refCache.departments = S.departments.map(d => ({ id: d.id, label: d.name })));
    if (entity === 'rooms') return (refCache.rooms = S.rooms.map(r => ({ id: r.id, label: r.number })));
    if (entity === 'locations') return (refCache.locations = S.locations.map(r => ({ id: r.id, label: r.name })));
    const j = await api('GET', '/refs/' + entity);
    return (refCache[entity] = j.rows);
  }
  const refLabel = (entity, id) => { const r = (refCache[entity] || []).find(x => +x.id === +id); return r ? r.label : (id ? '#' + id : ''); };

  // ---------- auth ----------
  function renderLogin(msg) {
    clearInterval(pollTimer);
    document.body.className = 'page-login';
    ROOT.innerHTML = `<div class="login"><form class="box" autocomplete="on">
      <div class="brand"><div class="logo">X</div><div><b>${esc(BOOT.app)}</b><small>${esc(BOOT.property)}</small></div></div>
      <div class="field"><label for="u">Username or email</label><input id="u" type="text" autocomplete="username" required autocapitalize="none"></div>
      <div class="field mt"><label for="p">Password</label><input id="p" type="password" autocomplete="current-password" required></div>
      <div class="field mt" id="mfa" hidden><label for="c">Authenticator code</label><input id="c" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="8"></div>
      <p class="err-msg" id="err">${esc(msg || '')}</p>
      <button class="btn primary" style="width:100%;justify-content:center" type="submit">Sign in</button>
      <p class="small muted mt">Authorised staff only. Sign-ins are logged. ${BOOT.operator ? 'Operated by ' + esc(BOOT.operator) + '. ' : ''}<a href="${BASE}/HowTo.html">How-to guide</a>${BOOT.source ? ' · <a href="' + esc(BOOT.source) + '" rel="noopener">Source code (AGPL)</a>' : ''}</p>
    </form></div>`;
    let mfa = false;
    $('form', ROOT).addEventListener('submit', async e => {
      e.preventDefault();
      const err = $('#err'); err.textContent = '';
      try {
        let r;
        if (!mfa) r = await api('POST', '/auth/login', { username: $('#u').value, password: $('#p').value }, { quiet: true });
        else r = await api('POST', '/auth/mfa', { code: $('#c').value }, { quiet: true });
        if (r.mfa_required) { mfa = true; $('#mfa').hidden = false; $('#c').focus(); err.textContent = 'Enter the 6-digit code from your authenticator app.'; return; }
        CSRF = r.csrf; await load();
      } catch (ex) { err.textContent = ex.message; }
    });
    $('#u').focus();
  }

  async function load() {
    try { S = await api('GET', '/bootstrap', undefined, { quiet: true }); }
    catch (e) { if (e.status === 401) return renderLogin(); ROOT.innerHTML = `<div class="empty">Could not load: ${esc(e.message)}</div>`; return; }
    CSRF = S.csrf;
    try { const th = localStorage.getItem('exptix-theme'); if (th) document.documentElement.dataset.theme = th; } catch (e) {}
    document.body.className = 'page-app';
    renderShell();
    route();
    pollNotifications();
    clearInterval(pollTimer); pollTimer = setInterval(pollNotifications, 30000);
    if ('serviceWorker' in navigator) navigator.serviceWorker.register(BASE + '/assets/sw.js', { scope: BASE + '/' }).catch(() => {});
  }

  // ---------- shell ----------
  function navItems() {
    const m = S.meta, e = n => m[n] ? `#/e/${n}` : null;
    const groups = [
      ['Overview', [['🏠', 'Dashboard', '#/'], ['✅', 'My work', '#/my'], ['🎫', 'Tickets', '#/t'], ['➕', 'New request', '#/new']]],
      ['Rooms', [['🛏️', 'Housekeeping board', '#/hk'], ['🚪', 'Rooms', e('rooms')], ['🧳', 'Lost and found', e('lost_found')], ['⭐', 'Guest preferences', e('guest_notes')], ['🍫', 'Minibar', e('minibar_postings')]]],
      ['Engineering', [['🛠️', 'Assets', e('assets')], ['📆', 'Preventive maintenance', e('pm_schedules')], ['📦', 'Parts and stock', e('parts')], ['🤝', 'Vendors', e('vendors')], ['🧾', 'Purchase requests', e('purchase_requests')]]],
      ['Food and beverage', [['🌡️', 'Food safety', '#/food'], ['🚫', '86 list', e('menu_86')], ['🥜', 'Allergy alerts', e('allergens')], ['🎉', 'Events and BEOs', e('events')], ['🍽️', 'Outlets', e('outlets')], ['📍', 'Temperature points', e('temp_points')]]],
      ['Safety and security', [['🛡️', 'Incident reports', e('incidents')], ['🔑', 'Key control', e('key_log')]]],
      ['Operations', [['📓', 'Shift logbook', '#/logbook'], ['📋', 'Inspections', '#/inspect'], ['🗂️', 'Checklist templates', e('checklist_templates')], ['📑', 'Completed checklists', e('checklist_runs')], ['👥', 'Staff schedule', e('staff_shifts')], ['📣', 'Announcements', e('announcements')], ['🗓️', 'Calendar', '#/calendar']]],
      ['Insights', rank() >= 50 || S.user.read_only ? [['📊', 'Reports', '#/reports']] : []],
      ['Admin', [
        ...(rank() >= 90 ? [['👤', 'Users', e('users')]] : []), ...(rank() >= 70 ? [['🏢', 'Departments', e('departments')], ['📌', 'Locations', e('locations')], ['🛌', 'Room types', e('room_types')], ['🏷️', 'Request categories', e('categories')], ['⏱️', 'SLA policies', e('sla_policies')], ['🔔', 'Notification rules', e('notification_rules')]] : []),
        ...(rank() >= 90 ? [['🔗', 'Integrations', '#/admin/integrations'], ['🧩', 'API and MCP', '#/admin/api'], ['📤', 'Webhooks', e('webhooks')]] : []),
        ...(rank() >= 90 || S.user.read_only ? [['🧾', 'Audit log', '#/admin/audit']] : []), ...(rank() >= 70 || S.user.read_only ? [['🔐', 'Security and compliance', '#/admin/security']] : []), ...(rank() >= 50 ? [['🔳', 'Room QR codes', '#/admin/qr']] : []),
      ]],
    ];
    return groups.map(([g, items]) => [g, items.filter(i => i[2])]).filter(([, items]) => items.length);
  }

  function renderShell() {
    const u = S.user;
    ROOT.innerHTML = `${S.app.demo ? '<div class="demo-banner">Demo mode · fictional hotel, guests and staff</div>' : ''}
    <div class="shell">
      <aside class="side" id="side">
        <div class="brand"><div class="logo">X</div><div><b>${esc(S.app.name)}</b><small>${esc(S.app.property)}</small></div></div>
        <nav class="nav">${navItems().map(([g, items]) => `<div class="nav-group">${esc(g)}</div>` + items.map(([i, l, h]) => `<a href="${h}" data-nav="${h}"><span class="ico">${i}</span>${esc(l)}</a>`).join('')).join('')}
          <div class="nav-group">Help</div><a href="${BASE}/HowTo.html" target="_blank" rel="noopener"><span class="ico">🧸</span>How-to guide</a><a href="${BASE}/docs" target="_blank" rel="noopener"><span class="ico">📘</span>API documentation</a></nav>
        <p class="small" style="color:#7FA4A8;padding:14px 10px 0">v${esc(S.app.version)} · ${esc(S.app.operator)}</p>
      </aside>
      <div class="main">
        <header class="top">
          <button class="iconbtn menu-btn" id="menuBtn" aria-label="Menu">☰</button>
          <div class="search"><input type="search" id="q" placeholder="Search tickets, rooms, guests, assets…" autocomplete="off" aria-label="Search"><div class="search-results" id="qr" hidden></div></div>
          <span class="sp hide-d"></span>
          <button class="iconbtn" id="bell" aria-label="Notifications">🔔<span class="dot" id="bellDot" hidden></span></button>
          <div class="who" id="who" title="Account"><div class="avatar">${esc(initials(u.name))}</div><div class="meta"><b>${esc(u.name)}</b><small>${esc(u.role_label)}${u.on_shift ? ' · on shift' : ''}</small></div></div>
        </header>
        <main class="content" id="view"></main>
      </div>
    </div>
    <nav class="tabbar"><a href="#/" data-tab="#/"><span class="i">🏠</span>Home</a><a href="#/my" data-tab="#/my"><span class="i">✅</span>My work</a><a href="#/new" class="plus"><span class="i">+</span>New</a><a href="#/hk" data-tab="#/hk"><span class="i">🛏️</span>Rooms</a><a href="#/t" data-tab="#/t"><span class="i">🎫</span>Tickets</a></nav>`;
    $('#menuBtn').onclick = () => { $('#side').classList.add('open'); const s = document.createElement('div'); s.className = 'scrim'; s.onclick = () => { $('#side').classList.remove('open'); s.remove(); }; document.body.appendChild(s); };
    $('#side').addEventListener('click', e => { if (e.target.closest('a')) { $('#side').classList.remove('open'); $('.scrim')?.remove(); } });
    $('#who').onclick = () => location.hash = '#/account';
    $('#bell').onclick = openNotifications;
    const q = $('#q'), qr = $('#qr');
    q.addEventListener('input', debounce(async () => {
      const v = q.value.trim(); if (v.length < 2) { qr.hidden = true; return; }
      const j = await api('GET', '/search?q=' + encodeURIComponent(v));
      qr.innerHTML = j.results.length ? j.results.map(r => `<a href="${esc(r.link)}"><small>${esc(r.type)}</small><span>${esc(r.label)}</span></a>`).join('') : '<div class="empty">No matches</div>';
      qr.hidden = false;
    }, 250));
    q.addEventListener('keydown', e => { if (e.key === 'Escape') { qr.hidden = true; q.blur(); } });
    document.addEventListener('click', e => { if (!e.target.closest('.search')) qr.hidden = true; });
    qr.addEventListener('click', () => { qr.hidden = true; q.value = ''; });
  }

  function setActive(h) {
    $$('[data-nav]').forEach(a => a.classList.toggle('on', h === a.dataset.nav || (a.dataset.nav !== '#/' && h.startsWith(a.dataset.nav + '/'))));
    $$('[data-tab]').forEach(a => a.classList.toggle('on', h === a.dataset.tab || (a.dataset.tab !== '#/' && h.startsWith(a.dataset.tab))));
  }

  // ---------- notifications ----------
  async function pollNotifications() {
    if (!S) return;
    try {
      const j = await api('GET', '/notifications?unread=1&limit=1');
      const d = $('#bellDot'); if (!d) return;
      d.hidden = !j.unread; d.textContent = j.unread > 99 ? '99+' : j.unread;
      if (lastUnread >= 0 && j.unread > lastUnread && j.rows[0]) toast('🔔 ' + j.rows[0].title);
      lastUnread = j.unread;
      document.title = (j.unread ? `(${j.unread}) ` : '') + `${S.app.name} · ${S.app.property}`;
    } catch (e) { /* offline */ }
  }
  async function openNotifications() {
    const j = await api('GET', '/notifications?limit=50');
    const d = document.createElement('div'); d.className = 'drawer';
    d.innerHTML = `<div class="dh"><b class="sp">Notifications</b><button class="btn sm" data-all>Mark all read</button><button class="btn ghost sm" data-x>✕</button></div>
      <div class="db">${j.rows.length ? j.rows.map(n => `<a class="notif ${n.read_at ? '' : 'unread'} ${n.level === 'alert' ? 'alert' : ''}" href="${esc(n.link || '#/')}" data-id="${n.id}"><b>${esc(n.title)}</b><div class="small">${nl2br(n.body)}</div><small>${esc(rel(n.created_at))}</small></a>`).join('') : '<div class="empty">Nothing yet.</div>'}</div>`;
    document.body.appendChild(d);
    const close = () => d.remove();
    d.querySelector('[data-x]').onclick = close;
    d.querySelector('[data-all]').onclick = async () => { await api('POST', '/notifications/read', {}); close(); pollNotifications(); };
    d.addEventListener('click', async e => { const a = e.target.closest('.notif'); if (a) { api('POST', '/notifications/read', { ids: [+a.dataset.id] }).then(pollNotifications); close(); } });
  }

  // ---------- router ----------
  window.addEventListener('hashchange', () => S && route());
  async function route() {
    const h = location.hash || '#/';
    currentRoute = h;
    setActive(h);
    clearInterval(hkTimer);
    const v = $('#view'); if (!v) return;
    window.scrollTo(0, 0);
    if (S.user.must_change_password && h !== '#/account') { location.hash = '#/account'; return; }
    const parts = h.replace(/^#\//, '').split('?')[0].split('/');
    const qs = new URLSearchParams(h.split('?')[1] || '');
    v.innerHTML = '<div class="empty"><div class="spinner" style="margin:auto"></div></div>';
    try {
      switch (parts[0]) {
        case '': return await viewDashboard(v);
        case 'my': return await viewMy(v);
        case 't': return parts[1] ? await viewTicket(v, +parts[1]) : await viewTickets(v, qs);
        case 'new': return await viewNew(v, qs);
        case 'hk': return await viewHk(v, qs);
        case 'room': return await viewHk(v, new URLSearchParams('room=' + parts[1]));
        case 'food': return await viewFood(v);
        case 'inspect': return parts[1] ? await viewInspectRun(v, +parts[1], qs) : await viewInspect(v);
        case 'logbook': return await viewLogbook(v);
        case 'reports': return await viewReports(v, qs);
        case 'calendar': return await viewCalendar(v);
        case 'account': return await viewAccount(v);
        case 'e': return parts[2] ? await viewRecord(v, parts[1], parts[2], qs) : await viewList(v, parts[1], qs);
        case 'admin': return await ({ integrations: viewIntegrations, api: viewApiAdmin, audit: viewAudit, security: viewSecurity, qr: viewQr }[parts[1]] || viewDashboard)(v);
        default: v.innerHTML = '<div class="empty">Page not found.</div>';
      }
    } catch (e) { if (e.message !== 'Signed out') v.innerHTML = `<div class="card empty">⚠️ ${esc(e.message)}</div>`; }
  }

  // ---------- ticket list rendering ----------
  function slaText(t) {
    if (['resolved', 'closed', 'cancelled'].includes(t.status)) return t.resolved_at ? `<span class="sla okk">Done ${esc(rel(t.resolved_at))}</span>` : '';
    if (!t.due_at) return '';
    const left = (toDate(t.due_at) - Date.now()) / 60000;
    const cls = left < 0 ? 'over' : left < 30 ? 'risk' : 'okk';
    return `<span class="sla ${cls}">${left < 0 ? 'Overdue ' + rel(t.due_at).replace(' ago', '') : 'Due ' + rel(t.due_at)}</span>`;
  }
  function ticketItem(t) {
    const room = t.room_number || roomNum(t.room_id);
    return `<a class="titem" href="#/t/${t.id}"><span class="pbar pb-${esc(t.priority)}"></span>
      <div><span class="muted small">${esc(t.number)}</span> <span class="ttl">${esc(t.title)}</span></div>
      <div class="row" style="justify-content:flex-end">${t.vip == 1 ? '<span class="badge b-vip">VIP</span>' : ''}${t.sla_breached == 1 ? '<span class="badge b-breach">SLA</span>' : ''}${prioBadge(t.priority)} ${statusBadge(t.status)}</div>
      <div class="meta">${deptChip(t.department_id)}${room ? `<span>🚪 ${esc(room)}</span>` : ''}${t.guest_name ? `<span>👤 ${esc(t.guest_name)}</span>` : ''}<span>${t.assignee_name ? '🧑‍🔧 ' + esc(t.assignee_name) : '<i>Unassigned</i>'}</span><span>🕑 ${esc(rel(t.created_at))}</span>${slaText(t)}${+t.checklist_total ? `<span>☑ ${t.checklist_done}/${t.checklist_total}</span>` : ''}${+t.repeat_count ? `<span title="Repeat issue">🔁 ${t.repeat_count}</span>` : ''}<span class="badge b-src">${esc(opt('source', t.source))}</span></div></a>`;
  }

  // ---------- dashboard ----------
  async function viewDashboard(v) {
    const d = await api('GET', '/dashboard');
    const k = d.kpi, r = d.rooms;
    const kpi = (val, label, cls, href) => `<a class="kpi ${cls || ''}" href="${href || '#/t'}"><div class="v">${val ?? '–'}</div><div class="l">${esc(label)}</div></a>`;
    v.innerHTML = `<div class="head"><h1>Good ${greeting()}, ${esc(S.user.name.split(' ')[0])}</h1><span class="sub">${esc(fmt(new Date().toISOString().slice(0, 19).replace('T', ' '), { weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' }))}</span><a class="btn gold" href="#/new">➕ New request</a></div>
      ${d.announcements.map(a => `<div class="ann"><b>${a.pinned == 1 ? '📌 ' : ''}${esc(a.title)}</b><div class="small">${nl2br(a.body)}</div></div>`).join('')}
      <div class="kpis">${kpi(k.open, 'Open tickets', '', '#/t?status=open')}${kpi(k.mine, 'Assigned to me', '', '#/my')}${kpi(k.unassigned, 'Unassigned', k.unassigned ? 'warn' : '', '#/t?status=open&assigned_to=none')}
        ${kpi(k.overdue, 'Overdue', k.overdue ? 'bad' : 'ok', '#/t?overdue=1')}${kpi(k.breached, 'SLA breached', k.breached ? 'bad' : 'ok', '#/t?breached=1&status=open')}${kpi(k.urgent, 'Urgent or critical', k.urgent ? 'warn' : '', '#/t?status=open&sort=priority')}
        ${kpi(k.guest_open, 'Open guest requests', '', '#/t?status=open&type=guest_request')}${kpi(k.created_today, 'Created today')}${kpi(k.resolved_today, 'Resolved today', 'ok', '#/t?status=done')}</div>
      <div class="card mb"><h3>🛏️ Rooms <span class="sp"></span><a class="btn sm" href="#/hk">Open board</a></h3>
        <div class="kpis" style="margin:0">${kpi(r.occupied + ' / ' + r.total, 'Occupied', '', '#/hk')}${kpi(r.dirty, 'Dirty', r.dirty ? 'bad' : '', '#/hk?hk=dirty')}${kpi(r.cleaning, 'Being cleaned', '', '#/hk?hk=in_progress')}${kpi(r.clean, 'Clean, to inspect', '', '#/hk?hk=clean')}${kpi(r.inspected, 'Inspected', 'ok', '#/hk?hk=inspected')}
        ${kpi(r.arrivals_ready + ' / ' + r.arrivals, 'Arrivals ready', r.arrivals_ready < r.arrivals ? 'warn' : 'ok', '#/hk?res=arrival')}${kpi(r.due_out, 'Due out', '', '#/hk?res=due_out')}${kpi(r.ooo, 'Out of order', r.ooo ? 'warn' : '', '#/hk?svc=1')}${kpi(r.vip_in_house, 'VIPs in house', '', '#/hk?vip=1')}</div></div>
      <div class="grid g2">
        <div class="card flush"><h3>🔥 Needs attention <span class="sp"></span><a class="small" href="#/t?status=open&sort=priority">All open</a></h3><div class="tlist mt">${d.hot.map(ticketItem).join('') || '<div class="empty">All clear.</div>'}</div></div>
        <div class="card flush"><h3>✅ My open work <span class="sp"></span><a class="small" href="#/my">My work</a></h3><div class="tlist mt">${d.my_tickets.map(ticketItem).join('') || '<div class="empty">Nothing assigned to you.</div>'}</div></div>
      </div>
      <div class="card mt"><h3>Departments</h3><div class="dept-tiles">${d.by_department.map(x => `<a class="dtile" style="border-left-color:${esc(x.colour || '#4E9AA0')}" href="#/t?status=open&department_id=${x.id}"><div>${esc(x.icon || '')} ${esc(x.name)}</div><b>${+x.open}</b> <span class="small">open</span><div class="small">${+x.urgent ? `<span style="color:var(--bad)">${x.urgent} urgent</span> · ` : ''}${+x.breached ? `<span style="color:var(--bad)">${x.breached} past SLA</span>` : 'on track'}</div></a>`).join('')}</div></div>
      <div class="grid g3 mt">
        <div class="card"><h3>🎉 Events today and tomorrow</h3>${d.events_today.map(e => `<div class="mb"><a href="#/e/events/${e.id}"><b>${esc(e.name)}</b></a><div class="small muted">${esc(fmt(e.start_at))} · ${esc(e.venue || '')} · ${+e.guests} guests</div></div>`).join('') || '<p class="muted">No events.</p>'}</div>
        <div class="card"><h3>🌡️ Food safety <span class="sp"></span><a class="small" href="#/food">Log</a></h3>${d.food_safety.map(p => `<div class="row small mb"><span class="sp">${esc(p.name)}</span>${!+p.last_in_range && p.last_at ? `<span class="badge p-urgent">${esc(p.last_reading)} °C</span>` : ''}${p.overdue ? '<span class="badge p-high">Check due</span>' : ''}</div>`).join('') || '<p class="muted">All temperatures logged and in range.</p>'}
          ${d.eighty_six.length ? `<h3 class="mt">🚫 86 list</h3>${d.eighty_six.map(x => `<div class="small">${esc(x.item)} <span class="muted">(${esc(x.outlet || '')})${x.substitute ? ' · try ' + esc(x.substitute) : ''}</span></div>`).join('')}` : ''}</div>
        <div class="card"><h3>📓 Logbook highlights <span class="sp"></span><a class="small" href="#/logbook">Logbook</a></h3>${d.logbook.map(l => `<div class="mb small"><span class="badge ${l.importance === 'action' ? 'p-urgent' : 'p-high'}">${esc(opt('importance', l.importance))}</span> <b>${esc(l.department || '')}</b> ${esc(l.entry)}<div class="muted">${esc(l.author || '')} · ${esc(rel(l.created_at))}</div></div>`).join('') || '<p class="muted">No important entries in the last day and a half.</p>'}</div>
      </div>
      <div class="card mt"><h3>Last 30 days</h3><div class="kpis" style="margin:0">${kpi(d.perf_30d.resolved, 'Resolved', '', '#/reports')}${kpi(mins(d.perf_30d.avg_response_min), 'Average first response', '', '#/reports')}${kpi(mins(d.perf_30d.avg_resolve_min), 'Average time to resolve', '', '#/reports')}${kpi(d.perf_30d.sla_pct != null ? d.perf_30d.sla_pct + '%' : '–', 'Within SLA', d.perf_30d.sla_pct >= 90 ? 'ok' : 'warn', '#/reports')}${kpi(d.perf_30d.csat != null ? d.perf_30d.csat + ' / 5' : '–', 'Guest rating', '', '#/reports')}</div></div>`;
  }
  const greeting = () => { const h = +new Intl.DateTimeFormat(LOC, { timeZone: tz(), hour: 'numeric', hourCycle: 'h23' }).format(new Date()); return h < 12 ? 'morning' : h < 18 ? 'afternoon' : 'evening'; };

  // ---------- my work ----------
  async function viewMy(v) {
    const [mine, created] = await Promise.all([api('GET', '/tickets?assigned_to=me&status=open&sort=priority&limit=100'), api('GET', `/tickets?created_by=${S.user.id}&status=open&limit=30`)]);
    const myDeps = S.user.departments.join(',');
    const pool = S.user.departments.length ? await api('GET', `/tickets?status=open&assigned_to=none&department_id=${S.user.department_id || ''}&sort=priority&limit=30`) : { rows: [] };
    v.innerHTML = `<div class="head"><h1>My work</h1><label class="check"><input type="checkbox" id="shift" ${S.user.on_shift ? 'checked' : ''}> On shift (receive new work)</label>${S.user.departments.includes(+(S.departments.find(d => d.code === 'HK') || {}).id) ? '<a class="btn" href="#/hk?mine=1">🛏️ My rooms</a>' : ''}</div>
      <div class="grid g2"><div class="card flush"><h3>Assigned to me (${mine.total})</h3><div class="tlist mt">${mine.rows.map(myItem).join('') || '<div class="empty">Nothing assigned. Pick something up from the queue.</div>'}</div></div>
      <div><div class="card flush"><h3>Unassigned in my department (${pool.rows.length})</h3><div class="tlist mt">${pool.rows.map(t => ticketItem(t).replace('</a>', `</a>`)).join('') || '<div class="empty">Queue is empty.</div>'}</div></div>
      <div class="card flush mt"><h3>Requests I raised</h3><div class="tlist mt">${created.rows.map(ticketItem).join('') || '<div class="empty">None open.</div>'}</div></div></div></div>`;
    $('#shift').onchange = async e => { S.user = await run(() => api('PATCH', '/account', { on_shift: e.target.checked }), e.target.checked ? 'You are on shift' : 'You are off shift'); };
    v.addEventListener('click', async e => {
      const b = e.target.closest('[data-qs]'); if (!b) return;
      e.preventDefault(); e.stopPropagation();
      await run(() => api('PATCH', '/tickets/' + b.dataset.id, { status: b.dataset.qs, force: 1 }), 'Updated'); route();
    });
    void myDeps;
  }
  function myItem(t) {
    const next = t.status === 'new' || t.status === 'acknowledged' ? ['in_progress', '▶ Start'] : t.status === 'in_progress' ? ['resolved', '✔ Done'] : t.status === 'on_hold' ? ['in_progress', '▶ Resume'] : null;
    return ticketItem(t).replace('</a>', '') + (next ? `<div style="grid-column:1/-1;text-align:right"><button class="btn sm primary" data-qs="${next[0]}" data-id="${t.id}">${next[1]}</button></div>` : '') + '</a>';
  }

  // ---------- tickets ----------
  async function viewTickets(v, qs) {
    const f = Object.fromEntries(qs.entries());
    if (!f.status && !f.overdue) f.status = 'open';
    const render = async (offset = 0, append = false) => {
      const p = new URLSearchParams({ ...f, limit: 50, offset }); for (const [kk, vv] of [...p]) if (vv === '') p.delete(kk);
      const j = await api('GET', '/tickets?' + p);
      const list = $('#tl');
      list.insertAdjacentHTML(append ? 'beforeend' : 'afterbegin', j.rows.map(ticketItem).join('') || (append ? '' : '<div class="empty">No tickets match.</div>'));
      if (!append) list.innerHTML = list.innerHTML; // keep
      $('#cnt').textContent = `${j.total} ticket${j.total === 1 ? '' : 's'}`;
      $('#more').hidden = offset + j.rows.length >= j.total;
      $('#more').onclick = () => render(offset + 50, true);
    };
    v.innerHTML = `<div class="head"><h1>Tickets</h1><span class="sub" id="cnt"></span><a class="btn" id="csv" href="#">⬇ CSV</a><a class="btn gold" href="#/new">➕ New</a></div>
      <div class="filters">
        <div class="seg" id="st">${[['open', 'Open'], ['', 'All'], ['done', 'Done']].map(([k, l]) => `<button data-v="${k}" class="${(f.status || '') === k ? 'on' : ''}">${l}</button>`).join('')}</div>
        <select id="fd" aria-label="Department">${listOptions(S.departments, f.department_id, 'All departments')}</select>
        <select id="fp" aria-label="Priority">${options(S.opt.priority, f.priority, 'Any priority')}</select>
        <select id="ft" aria-label="Type">${options(S.opt.ticket_type, f.type, 'Any type')}</select>
        <select id="fa" aria-label="Assignee"><option value="">Anyone</option><option value="me"${f.assigned_to === 'me' ? ' selected' : ''}>Me</option><option value="none"${f.assigned_to === 'none' ? ' selected' : ''}>Unassigned</option>${listOptions(S.users, f.assigned_to)}</select>
        <select id="fs" aria-label="Sort">${options({ '': 'Newest', priority: 'Priority', due: 'Due soonest', updated: 'Recently updated', oldest: 'Oldest' }, f.sort)}</select>
        <input type="search" id="fq" placeholder="Search" value="${esc(f.q || '')}">
        ${f.overdue ? '<span class="badge b-breach">Overdue only</span>' : ''}${f.breached ? '<span class="badge b-breach">SLA breached</span>' : ''}${f.vip ? '<span class="badge b-vip">VIP</span>' : ''}
      </div>
      <div class="card flush"><div class="tlist" id="tl"></div><div class="empty" style="padding:12px"><button class="btn" id="more" hidden>Load more</button></div></div>`;
    const go = () => { const p = new URLSearchParams(); for (const [kk, vv] of Object.entries(f)) if (vv) p.set(kk, vv); location.hash = '#/t?' + p; };
    $('#st').onclick = e => { const b = e.target.closest('button'); if (!b) return; f.status = b.dataset.v; delete f.overdue; go(); };
    [['#fd', 'department_id'], ['#fp', 'priority'], ['#ft', 'type'], ['#fa', 'assigned_to'], ['#fs', 'sort']].forEach(([s, k]) => $(s).onchange = e => { f[k] = e.target.value; go(); });
    $('#fq').onchange = e => { f.q = e.target.value; go(); };
    $('#csv').onclick = e => { e.preventDefault(); const p = new URLSearchParams(f); location.href = BASE + '/api/reports/tickets.csv?' + p; };
    await render();
  }

  async function viewNew(v, qs) {
    const pre = Object.fromEntries(qs.entries());
    const assets = await refs('assets').catch(() => []);
    v.innerHTML = `<div class="head"><h1>New request</h1><span class="sub">Leave department and category blank and ${esc(S.app.name)} routes it from the description.</span></div>
      <div class="card"><div class="mb"><div class="small muted mb">Quick pick</div><div class="row" id="quick">${S.categories.filter(c => c.guest_visible == 1 || ['Elevator', 'Fire and life safety', 'Kitchen equipment', 'Snow and ice', 'Spill or stain'].includes(c.name)).slice(0, 24).map(c => `<button type="button" class="btn sm" data-cat="${c.id}">${esc(c.icon || '')} ${esc(c.name)}</button>`).join('')}</div></div>
      <form class="form" id="nf">
        <div class="field full"><label>What is needed? <span class="req">*</span></label><input type="text" name="title" required maxlength="255" value="${esc(pre.title || '')}" placeholder="e.g. Toilet running in 512, guest asked for extra towels"></div>
        <div class="field full"><label>Details</label><textarea name="description" placeholder="Anything that helps the team: what the guest said, what you checked, access notes"></textarea></div>
        <div class="field"><label>Department</label><select name="department_id">${listOptions(S.departments.filter(d => d.active == 1), pre.department_id, 'Auto-route')}</select></div>
        <div class="field"><label>Category</label><select name="category_id"><option value="">Auto-detect</option></select></div>
        <div class="field"><label>Priority</label><select name="priority">${options(S.opt.priority, pre.priority, 'Default for category')}</select></div>
        <div class="field"><label>Type</label><select name="type">${options(S.opt.ticket_type, pre.type, 'Default for category')}</select></div>
        <div class="field"><label>Room</label><input type="text" name="room" list="roomlist" value="${esc(pre.room || '')}" inputmode="numeric" placeholder="e.g. 514"><datalist id="roomlist">${S.rooms.map(r => `<option value="${esc(r.number)}">`).join('')}</datalist></div>
        <div class="field"><label>Area or location</label><select name="location_id">${listOptions(S.locations, pre.location_id, '(none)')}</select></div>
        <div class="field"><label>Asset or equipment</label><select name="asset_id">${listOptions(assets, pre.asset_id, '(none)', 'label')}</select></div>
        <div class="field"><label>Guest name</label><input type="text" name="guest_name" value="${esc(pre.guest_name || '')}"></div>
        <div class="field"><label>Assign to</label><select name="assigned_to"><option value="">Auto / department queue</option>${listOptions(S.users.filter(u => u.role !== 'auditor'), pre.assigned_to)}</select></div>
        <div class="field"><label>Due (optional)</label><input type="datetime-local" name="due_at"></div>
        <div class="field"><label>Source</label><select name="source">${options({ staff: 'Staff', phone: 'Phone call', front_desk: 'Front desk' }, 'staff')}</select></div>
        <div class="field"><label>Photo</label><input type="file" name="photo" accept="image/*" capture="environment"></div>
        <label class="check full"><input type="checkbox" name="set_room_ooo"> Take the room out of order until this is fixed</label>
        <label class="check full"><input type="checkbox" name="vip"> VIP guest</label>
        <div class="full row"><button class="btn primary" type="submit">Create request</button><a class="btn" href="#/t">Cancel</a></div>
      </form></div>`;
    const form = $('#nf'), ds = form.department_id, cs = form.category_id;
    const fillCats = () => { cs.innerHTML = '<option value="">Auto-detect</option>' + S.categories.filter(c => !ds.value || +c.department_id === +ds.value).map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join(''); };
    ds.onchange = fillCats; fillCats();
    if (pre.category_id) { const c = S.categories.find(x => +x.id === +pre.category_id); if (c) { ds.value = c.department_id; fillCats(); cs.value = c.id; } }
    $('#quick').onclick = e => { const b = e.target.closest('[data-cat]'); if (!b) return; const c = S.categories.find(x => +x.id === +b.dataset.cat); ds.value = c.department_id; fillCats(); cs.value = c.id; if (!form.title.value) form.title.value = c.name; form.title.focus(); $$('#quick .btn').forEach(x => x.classList.toggle('primary', x === b)); };
    form.onsubmit = async e => {
      e.preventDefault();
      const fd = new FormData(form), body = {};
      for (const [k, val] of fd.entries()) if (k !== 'photo' && val !== '') body[k] = val;
      body.set_room_ooo = form.set_room_ooo.checked; body.vip = form.vip.checked;
      const btn = $('button[type=submit]', form); btn.disabled = true;
      try {
        const t = await api('POST', '/tickets', body);
        const ph = form.photo.files[0];
        if (ph) { const u = new FormData(); u.append('file', ph); await api('POST', `/tickets/${t.id}/attachments`, u).catch(x => toast('Photo upload failed: ' + x.message, true)); }
        toast(`Created ${t.number} for ${(dept(t.department_id) || {}).name || 'triage'}`);
        location.hash = '#/t/' + t.id;
      } catch (x) { toast(x.message, true); btn.disabled = false; }
    };
  }

  async function viewTicket(v, id) {
    const t = await api('GET', '/tickets/' + id);
    const trans = { acknowledged: ['👍 Acknowledge', ''], in_progress: [t.status === 'new' || t.status === 'acknowledged' ? '▶ Start' : '▶ Resume / reopen', 'primary'], on_hold: ['⏸ On hold', ''], resolved: ['✔ Resolve', 'primary'], closed: ['🔒 Close', ''], cancelled: ['✕ Cancel', 'danger'], new: ['↺ Restore', ''] };
    const ro = !can(20);
    const deptUsers = S.users.filter(u => u.role !== 'auditor' && (!t.department_id || +u.department_id === +t.department_id || JSON.parse(u.extra_departments || '[]').map(Number).includes(+t.department_id)));
    const others = S.users.filter(u => !deptUsers.includes(u) && u.role !== 'auditor');
    const evHtml = t.events.map(e => {
      const sys = ['system', 'status', 'assign', 'priority', 'time', 'parts', 'attachment'].includes(e.kind);
      return `<div class="ev ${sys ? 'system' : ''} ${e.kind === 'guest_message' ? 'guest' : ''} ${e.visibility === 'guest' && e.kind !== 'guest_message' ? 'guestvis' : ''}"><div class="avatar" style="width:28px;height:28px;font-size:.7rem;${sys ? 'background:var(--surface2);color:var(--muted)' : ''}">${e.kind === 'guest_message' ? '👤' : esc(initials(e.user_name || 'S'))}</div><div class="bubble"><div class="who-line">${esc(e.kind === 'guest_message' ? 'Guest' : e.user_name || 'System')} · ${esc(fmt(e.created_at))}${e.visibility === 'guest' && e.kind !== 'guest_message' ? ' · <b>visible to guest</b>' : ''}</div>${nl2br(e.body)}</div></div>`;
    }).join('');
    const kvs = [['Department', deptChip(t.department_id)], ['Category', esc(catName(t.category_id))], ['Type', esc(opt('ticket_type', t.type))], ['Source', esc(opt('source', t.source))],
      t.room_id ? ['Room', `<a href="#/room/${t.room_id}">🚪 ${esc(t.room?.number)}</a> <span class="muted small">${esc(opt('fo_status', t.room?.fo_status))}, ${esc(opt('hk_status', t.room?.hk_status))}${t.room?.service_status !== 'in_service' ? ', ' + esc(opt('service_status', t.room?.service_status)) : ''}</span>`] : null,
      t.location_id ? ['Location', esc(locName(t.location_id))] : null, t.asset ? ['Asset', `<a href="#/e/assets/${t.asset.id}">${esc(t.asset.tag)} ${esc(t.asset.name)}</a>`] : null,
      t.guest_name ? ['Guest', `${esc(t.guest_name)}${t.vip == 1 ? ' <span class="badge b-vip">VIP</span>' : ''}${t.reservation_ref ? ` <span class="muted small">#${esc(t.reservation_ref)}</span>` : ''}`] : null, t.guest_contact ? ['Contact', esc(t.guest_contact)] : null,
      ['Requested by', esc(t.requester_name || userName(t.created_by))], ['Created', `${esc(fmt(t.created_at))} <span class="muted small">(${esc(rel(t.created_at))})</span>`], ['Respond by', t.response_due_at ? esc(fmt(t.response_due_at)) + (t.first_response_at ? ` <span class="muted small">✓ ${esc(fmt(t.first_response_at))}</span>` : '') : '–'],
      ['Resolve by', t.due_at ? `${esc(fmt(t.due_at))} ${slaText(t)}` : '–'], t.scheduled_for ? ['Scheduled', esc(fmt(t.scheduled_for))] : null, t.resolved_at ? ['Resolved', esc(fmt(t.resolved_at))] : null,
      ['Labour', mins(+t.labour_minutes)], ['Parts', money(t.parts_cost)], t.external_ref ? ['External ref', `<span class="code">${esc(t.external_system || '')} ${esc(t.external_ref)}</span>`] : null,
      t.satisfaction ? ['Guest rating', '★'.repeat(+t.satisfaction) + '☆'.repeat(5 - t.satisfaction) + (t.satisfaction_comment ? ` <span class="small">${esc(t.satisfaction_comment)}</span>` : '')] : null,
      t.parent_id ? ['Parent', `<a href="#/t/${t.parent_id}">Ticket #${t.parent_id}</a>`] : null, t.event_id ? ['Event', `<a href="#/e/events/${t.event_id}">View BEO</a>`] : null].filter(Boolean);
    v.innerHTML = `<div class="head"><a class="btn sm" href="#/t">←</a><h1><span class="muted" style="font-size:.9rem">${esc(t.number)}</span><br>${esc(t.title)}</h1>
      <div class="row">${t.vip == 1 ? '<span class="badge b-vip">VIP</span>' : ''}${t.sla_breached == 1 ? `<span class="badge b-breach">SLA missed${t.escalation_level > 1 ? ', escalated' : ''}</span>` : ''}${prioBadge(t.priority)} ${statusBadge(t.status)}</div></div>
      ${ro ? '' : `<div class="row mb" id="acts">${t.transitions.map(s => `<button class="btn ${trans[s]?.[1] || ''}" data-st="${s}">${trans[s]?.[0] || s}</button>`).join('')}
        ${!t.assigned_to || +t.assigned_to !== +S.user.id ? '<button class="btn" data-take>🙋 Take it</button>' : ''}
        <select id="asg" style="width:auto" ${rank() < 50 ? 'disabled title="Supervisors assign"' : ''}><option value="">Unassigned</option><optgroup label="${esc((dept(t.department_id) || {}).name || 'Department')}">${listOptions(deptUsers, t.assigned_to)}</optgroup><optgroup label="Everyone else">${listOptions(others, t.assigned_to)}</optgroup></select>
        <select id="pri" style="width:auto">${options(S.opt.priority, t.priority)}</select>
        <button class="btn" data-follow>➕ Follow-up for another team</button></div>`}
      ${t.guest_notes.length ? `<div class="ann">${t.guest_notes.map(g => `<div><b>${esc(opt('guest_note_kind', g.kind))}:</b> ${esc(g.note)}</div>`).join('')}</div>` : ''}
      <div class="tdetail">
        <div>
          <div class="card mb">${t.description ? `<div>${nl2br(t.description)}</div>` : '<p class="muted">No details.</p>'}
            ${t.attachments.length ? `<div class="attach mt">${t.attachments.map(a => `<a href="${BASE}/api/files/${a.id}" target="_blank" rel="noopener">${a.mime.startsWith('image/') ? `<img src="${BASE}/api/files/${a.id}" alt="${esc(a.filename)}" loading="lazy">` : '📄 ' + esc(a.filename)}</a>`).join('')}</div>` : ''}
            ${ro ? '' : `<label class="btn sm mt">📷 Add photo or file<input type="file" id="up" accept="image/*,application/pdf" capture="environment" hidden></label>`}</div>
          ${t.checklist.length || !ro ? `<div class="card mb"><h3>Checklist <span class="sp"></span><span class="small muted">${t.checklist.filter(c => c.done == 1).length}/${t.checklist.length}</span></h3>
            ${t.checklist.map(c => `<label class="cl-item ${c.done == 1 ? 'done' : ''}"><input type="checkbox" data-cl="${c.id}" ${c.done == 1 ? 'checked' : ''} ${ro ? 'disabled' : ''}><span class="sp">${esc(c.item)}</span>${c.done == 1 ? `<small class="muted">${esc(c.done_by_name || '')}</small>` : ''}</label>`).join('')}
            ${ro ? '' : '<form class="row mt" id="cla"><input type="text" placeholder="Add a step" style="flex:1"><button class="btn sm">Add</button></form>'}</div>` : ''}
          ${['complaint', 'guest_request'].includes(t.type) || t.resolution ? `<div class="card mb"><h3>Resolution and service recovery</h3><form class="form" id="resf">
            <div class="field full"><label>Resolution notes</label><textarea name="resolution" ${ro ? 'disabled' : ''}>${esc(t.resolution || '')}</textarea></div>
            <div class="field"><label>Recovery action</label><input type="text" name="recovery_action" value="${esc(t.recovery_action || '')}" placeholder="e.g. Comp breakfast, room move, amenity" ${ro ? 'disabled' : ''}></div>
            <div class="field"><label>Recovery value</label><input type="number" step="0.01" name="recovery_value" value="${esc(t.recovery_value || '')}" ${ro ? 'disabled' : ''}></div>
            ${ro ? '' : '<div class="full"><button class="btn sm">Save</button></div>'}</form></div>` : ''}
          <div class="card"><h3>Activity</h3><div class="timeline">${evHtml}</div>
            ${ro ? '' : `<form id="cf" class="mt"><textarea name="body" placeholder="Add an update. @mention by name if needed." required></textarea>
              <div class="row mt"><div class="seg" id="vis"><button type="button" data-v="internal" class="on">Internal note</button>${t.guest_token || t.source === 'canary' ? '<button type="button" data-v="guest">Message guest</button>' : ''}</div><span class="sp"></span><button class="btn primary">Post</button></div></form>`}</div>
        </div>
        <div>
          <div class="card mb"><dl class="kv">${kvs.map(([a, b]) => `<dt>${esc(a)}</dt><dd>${b}</dd>`).join('')}</dl>
            ${t.guest_link ? `<hr><div class="small">Guest tracking link</div><div class="row"><span class="code" style="flex:1">${esc(t.guest_link)}</span><button class="btn sm" data-copy="${esc(t.guest_link)}">Copy</button></div>` : ''}</div>
          ${ro ? '' : `<div class="card mb"><h3>Log time and parts</h3><form class="row" id="tf"><input type="number" name="minutes" min="1" max="1440" placeholder="Minutes" style="width:100px"><input type="text" name="note" placeholder="Note" style="flex:1;min-width:100px"><button class="btn sm">Log</button></form>
            <form class="row mt" id="pf"><select name="part_id" style="flex:1"><option value="">Choose part…</option></select><input type="number" name="qty" value="1" min="0.1" step="0.1" style="width:70px"><button class="btn sm">Use</button></form>
            ${t.time.length ? `<div class="small mt">${t.time.map(x => `<div>${esc(x.user_name)}: ${x.minutes} min ${esc(x.note || '')}</div>`).join('')}</div>` : ''}
            ${t.parts.length ? `<div class="small mt">${t.parts.map(x => `<div>${esc(x.qty)} × ${esc(x.part_name)} (${money(x.unit_cost * x.qty)})</div>`).join('')}</div>` : ''}</div>`}
          ${t.children.length ? `<div class="card mb"><h3>Follow-up tasks</h3>${t.children.map(c => `<div class="small"><a href="#/t/${c.id}">${esc(c.number)}</a> ${esc(c.title)} ${statusBadge(c.status)}</div>`).join('')}</div>` : ''}
          ${t.room_history?.length ? `<div class="card mb"><h3>Room ${esc(t.room?.number)} history</h3>${t.room_history.map(c => `<div class="small mb"><a href="#/t/${c.id}">${esc(c.number)}</a> ${esc(c.title)} <span class="muted">${esc(fmtDate(c.created_at))}</span></div>`).join('')}</div>` : ''}
          ${t.asset_history?.length ? `<div class="card mb"><h3>Asset history</h3>${t.asset_history.map(c => `<div class="small mb"><a href="#/t/${c.id}">${esc(c.number)}</a> ${esc(c.title)} <span class="muted">${esc(fmtDate(c.created_at))}</span></div>`).join('')}</div>` : ''}
        </div></div>`;
    if (ro) return;
    const reload = () => viewTicket(v, id);
    $('#acts').addEventListener('click', async e => {
      const b = e.target.closest('button'); if (!b) return;
      if (b.dataset.take !== undefined) { await run(() => api('PATCH', '/tickets/' + id, { assigned_to: S.user.id, ...(t.status === 'new' ? { status: 'acknowledged' } : {}) }), 'It is yours'); return reload(); }
      if (b.dataset.follow !== undefined) return followUp(t);
      const st = b.dataset.st; if (!st) return;
      if (st === 'resolved' || st === 'on_hold' || st === 'cancelled') {
        const isOoo = t.room && t.room.service_status !== 'in_service';
        modal(trans[st][0], `<form class="form" id="sf"><div class="field full"><label>${st === 'resolved' ? 'What was done?' : 'Reason'}</label><textarea name="note" ${st !== 'resolved' ? 'required' : ''}></textarea></div>
          ${st === 'resolved' && isOoo ? '<label class="check full"><input type="checkbox" name="rts" checked> Return room to service (housekeeping will clean it)</label>' : ''}
          ${st === 'resolved' && t.checklist.some(c => c.done != 1) ? '<p class="full small" style="color:var(--warn)">Some checklist steps are not ticked. Resolving anyway records that.</p>' : ''}
          <div class="full"><button class="btn primary">${trans[st][0]}</button></div></form>`, (el, close) => {
          $('#sf', el).onsubmit = async ev => {
            ev.preventDefault();
            const f = ev.target;
            await run(() => api('PATCH', '/tickets/' + id, { status: st, status_note: f.note.value, force: 1, return_room_to_service: f.rts?.checked, ...(st === 'resolved' && f.note.value ? { resolution: f.note.value } : {}) }), 'Updated');
            close(); reload();
          };
        });
        return;
      }
      await run(() => api('PATCH', '/tickets/' + id, { status: st }), 'Updated'); reload();
    });
    $('#asg').onchange = async e => { await run(() => api('PATCH', '/tickets/' + id, { assigned_to: e.target.value || null }), 'Assigned'); reload(); };
    $('#pri').onchange = async e => { await run(() => api('PATCH', '/tickets/' + id, { priority: e.target.value }), 'Priority changed'); reload(); };
    $$('[data-cl]').forEach(c => c.onchange = async () => { await run(() => api('PATCH', `/tickets/${id}/checklist/${c.dataset.cl}`, { done: c.checked })); c.closest('.cl-item').classList.toggle('done', c.checked); });
    $('#cla') && ($('#cla').onsubmit = async e => { e.preventDefault(); const i = $('input', e.target); if (!i.value.trim()) return; await run(() => api('POST', `/tickets/${id}/checklist`, { item: i.value })); reload(); });
    $('#resf') && ($('#resf').onsubmit = async e => { e.preventDefault(); const f = e.target; await run(() => api('PATCH', '/tickets/' + id, { resolution: f.resolution.value, recovery_action: f.recovery_action.value, recovery_value: f.recovery_value.value || 0 }), 'Saved'); });
    let vis = 'internal';
    $('#vis').onclick = e => { const b = e.target.closest('button'); if (!b) return; vis = b.dataset.v; $$('#vis button').forEach(x => x.classList.toggle('on', x === b)); };
    $('#cf').onsubmit = async e => { e.preventDefault(); await run(() => api('POST', `/tickets/${id}/comments`, { body: e.target.body.value, visibility: vis }), vis === 'guest' ? 'Sent to guest' : 'Posted'); reload(); };
    $('#tf').onsubmit = async e => { e.preventDefault(); const f = e.target; await run(() => api('POST', `/tickets/${id}/time`, { minutes: +f.minutes.value, note: f.note.value }), 'Time logged'); reload(); };
    refs('parts').then(p => { $('#pf select').insertAdjacentHTML('beforeend', p.map(x => `<option value="${x.id}">${esc(x.label)}</option>`).join('')); }).catch(() => {});
    $('#pf').onsubmit = async e => { e.preventDefault(); const f = e.target; if (!f.part_id.value) return; await run(() => api('POST', `/tickets/${id}/parts`, { part_id: +f.part_id.value, qty: +f.qty.value }), 'Stock updated'); reload(); };
    $('#up') && ($('#up').onchange = async e => { const file = e.target.files[0]; if (!file) return; const fd = new FormData(); fd.append('file', file); await run(() => api('POST', `/tickets/${id}/attachments`, fd), 'Uploaded'); reload(); });
    $$('[data-copy]').forEach(b => b.onclick = () => { navigator.clipboard?.writeText(b.dataset.copy); toast('Copied'); });
  }

  function followUp(t) {
    modal('Follow-up task for another team', `<form class="form" id="ff"><div class="field full"><label>Department</label><select name="department_id" required>${listOptions(S.departments.filter(d => +d.id !== +t.department_id), '', 'Choose…')}</select></div>
      <div class="field full"><label>What do they need to do?</label><input type="text" name="title" required></div><div class="field full"><label>Details</label><textarea name="description"></textarea></div>
      <div class="field"><label>Priority</label><select name="priority">${options(S.opt.priority, t.priority)}</select></div><div class="full"><button class="btn primary">Create linked task</button></div></form>`, (el, close) => {
      $('#ff', el).onsubmit = async e => { e.preventDefault(); const f = e.target; const c = await run(() => api('POST', `/tickets/${t.id}/children`, { department_id: +f.department_id.value, title: f.title.value, description: f.description.value, priority: f.priority.value, type: 'task' }), 'Task created'); close(); location.hash = '#/t/' + c.id; };
    });
  }

  // ---------- housekeeping board ----------
  async function viewHk(v, qs) {
    let board = await api('GET', '/hk/board');
    const f = { floor: qs.get('floor') || '', hk: qs.get('hk') || '', res: qs.get('res') || '', att: qs.get('mine') ? String(S.user.id) : (qs.get('att') || ''), svc: qs.get('svc') || '', vip: qs.get('vip') || '' };
    let selecting = false; const sel = new Set();
    const isSup = can(50);
    const floors = [...new Set(board.rooms.map(r => r.floor))];
    const draw = () => {
      const rooms = board.rooms.filter(r => (!f.floor || r.floor === f.floor) && (!f.hk || r.hk_status === f.hk) && (!f.res || r.reservation_status === f.res) && (!f.att || String(r.attendant_id) === f.att) && (!f.svc || r.service_status !== 'in_service') && (!f.vip || r.vip_code));
      const count = s => board.rooms.filter(r => r.hk_status === s && r.service_status === 'in_service').length;
      $('#hkfloors').innerHTML = floors.filter(fl => rooms.some(r => r.floor === fl)).map(fl => `<div class="floor"><h3>Floor ${esc(fl)}</h3><div class="rooms">${rooms.filter(r => r.floor === fl).map(r => {
        const flags = [r.vip_code ? '⭐' : '', r.dnd == 1 ? '🚫' : '', r.make_up_room == 1 ? '🧹' : '', r.rush == 1 ? '⚡' : '', r.eco_skip == 1 ? '🌿' : '', +r.open_tickets ? '🔧' : ''].join('');
        const res = { arrival: 'Due in', due_out: 'Due out', stayover: 'Stay', arrived: 'Arrived', departed: 'Departed' }[r.reservation_status] || '';
        return `<div class="room hk-${esc(r.hk_status)} svc-${esc(r.service_status)} ${r.fo_status === 'occupied' ? 'occ' : ''} ${r.reservation_status === 'arrival' ? 'arr' : ''} ${sel.has(+r.id) ? 'sel' : ''}" data-room="${r.id}" title="${esc(r.type_name || '')} · ${esc(r.guest_name || '')}">
          <div class="n">${esc(r.number)}</div><div class="st">${r.service_status !== 'in_service' ? esc(opt('service_status', r.service_status)) : esc(opt('hk_status', r.hk_status))}<br>${r.fo_status === 'occupied' ? 'Occ' : 'Vac'}${res ? ' · ' + res : ''}</div><div class="flags">${flags}</div></div>`;
      }).join('')}</div></div>`).join('') || '<div class="empty">No rooms match.</div>';
      $('#hksum').innerHTML = `<span><b>${count('dirty')}</b> dirty</span> · <span><b>${count('in_progress')}</b> cleaning</span> · <span><b>${count('clean')}</b> clean</span> · <span><b>${count('inspected')}</b> inspected</span> · <span><b>${board.rooms.filter(r => r.reservation_status === 'arrival' && r.hk_status === 'inspected').length}/${board.rooms.filter(r => r.reservation_status === 'arrival').length}</b> arrivals ready</span>`;
      $('#atts').innerHTML = board.attendants.map(a => { const cap = +a.hk_credit_capacity || 16, pct = Math.min(100, Math.round(100 * a.credits / cap)); return `<div class="att ${f.att === String(a.id) ? 'on' : ''}" data-att="${a.id}"><div class="avatar" style="width:30px;height:30px;font-size:.72rem;${a.on_shift == 1 ? '' : 'opacity:.4'}">${esc(initials(a.name))}</div><div style="flex:1"><div class="row"><b class="sp small">${esc(a.name)}</b><span class="small muted">${a.done}/${a.rooms} done</span></div><div class="meter ${pct >= 100 ? 'full' : ''}"><i style="width:${pct}%"></i></div><div class="small muted">${(+a.credits).toFixed(1)} of ${cap} credits assigned${a.on_shift == 1 ? '' : ' · off shift'}</div></div></div>`; }).join('');
      $('#selbar').hidden = !selecting;
      $('#selcount').textContent = sel.size;
    };
    v.innerHTML = `<div class="head"><h1>Housekeeping board</h1><span class="sub" id="hksum"></span>${isSup ? '<button class="btn" id="selmode">☑ Select rooms</button><button class="btn gold" id="auto">⚖ Auto-assign</button>' : ''}<a class="btn" href="#/inspect">📋 Inspect</a></div>
      <div class="filters">
        <select id="ffl">${options(Object.fromEntries(floors.map(x => [x, 'Floor ' + x])), f.floor, 'All floors')}</select>
        <select id="fhk">${options(S.opt.hk_status, f.hk, 'Any status')}</select>
        <select id="fres">${options(S.opt.reservation_status, f.res, 'Any reservation')}</select>
        <select id="fatt"><option value="">All attendants</option>${listOptions(board.attendants, f.att)}</select>
        <label class="check"><input type="checkbox" id="fsvc" ${f.svc ? 'checked' : ''}> Out of order</label><label class="check"><input type="checkbox" id="fvip" ${f.vip ? 'checked' : ''}> VIP</label>
      </div>
      <div class="hk-legend"><span><i class="hk-dirty"></i>Dirty</span><span><i class="hk-in_progress"></i>Cleaning</span><span><i class="hk-clean"></i>Clean</span><span><i class="hk-inspected"></i>Inspected</span><span><i class="hk-pickup"></i>Pickup</span><span>⭐ VIP</span><span>🚫 DND</span><span>🧹 Service requested</span><span>⚡ Rush</span><span>🌿 Declined service</span><span>🔧 Open ticket</span><span>Gold border: arrival · dark base: occupied</span></div>
      <div class="card mb" id="selbar" hidden><div class="row"><b><span id="selcount">0</span> selected</b><select id="selatt" style="width:auto">${listOptions(board.attendants, '', 'Assign to…')}</select><button class="btn primary sm" id="doassign">Assign</button><button class="btn sm" data-bulk="dirty">Mark dirty</button><button class="btn sm" data-bulk="inspected">Mark inspected</button><button class="btn sm" id="selclear">Clear</button></div></div>
      <div class="grid" style="grid-template-columns:minmax(0,1fr) 300px" id="hkgrid"><div id="hkfloors"></div><div class="hide-m"><div class="card"><h3>Attendants</h3><div class="att-list" id="atts"></div></div></div></div>`;
    if (matchMedia('(max-width:980px)').matches) $('#hkgrid').style.gridTemplateColumns = '1fr';
    const reload = async () => { board = await api('GET', '/hk/board'); draw(); };
    [['#ffl', 'floor'], ['#fhk', 'hk'], ['#fres', 'res'], ['#fatt', 'att']].forEach(([s, k]) => $(s).onchange = e => { f[k] = e.target.value; draw(); });
    $('#fsvc').onchange = e => { f.svc = e.target.checked ? '1' : ''; draw(); };
    $('#fvip').onchange = e => { f.vip = e.target.checked ? '1' : ''; draw(); };
    $('#atts').onclick = e => { const a = e.target.closest('[data-att]'); if (!a) return; f.att = f.att === a.dataset.att ? '' : a.dataset.att; $('#fatt').value = f.att; draw(); };
    if (isSup) {
      $('#selmode').onclick = () => { selecting = !selecting; sel.clear(); $('#selmode').classList.toggle('primary', selecting); draw(); };
      $('#selclear').onclick = () => { sel.clear(); draw(); };
      $('#doassign').onclick = async () => { if (!sel.size) return; await run(() => api('POST', '/hk/assign', { room_ids: [...sel], attendant_id: $('#selatt').value || null }), 'Assigned'); sel.clear(); reload(); };
      $$('[data-bulk]').forEach(b => b.onclick = async () => { for (const id of sel) await api('POST', '/hk/rooms/' + id, { hk_status: b.dataset.bulk }).catch(x => toast(x.message, true)); toast('Updated ' + sel.size + ' rooms'); sel.clear(); reload(); });
      $('#auto').onclick = async () => { const r = await run(() => api('POST', '/hk/auto-assign', {})); toast(`Assigned ${r.assigned} rooms across ${Object.keys(r.load).length} attendants`); reload(); };
    }
    $('#hkfloors').onclick = e => {
      const el = e.target.closest('[data-room]'); if (!el) return;
      const id = +el.dataset.room;
      if (selecting) { sel.has(id) ? sel.delete(id) : sel.add(id); draw(); return; }
      roomDrawer(board.rooms.find(r => +r.id === id), board, reload);
    };
    draw();
    if (qs.get('room')) { const r = board.rooms.find(x => +x.id === +qs.get('room')); if (r) roomDrawer(r, board, reload); }
    hkTimer = setInterval(() => { if (currentRoute.startsWith('#/hk') && !$('.drawer')) reload().catch(() => {}); }, 60000);
  }

  function roomDrawer(r, board, reload) {
    $('.drawer')?.remove();
    const d = document.createElement('div'); d.className = 'drawer';
    const isSup = can(50), isFd = can(40);
    const btn = (s, label) => `<button class="btn ${r.hk_status === s ? 'primary' : ''}" data-hk="${s}">${label}</button>`;
    d.innerHTML = `<div class="dh"><b class="sp">Room ${esc(r.number)} <span class="muted small">${esc(r.type_name || '')}</span></b><button class="btn ghost sm" data-x>✕</button></div><div class="db" style="padding:14px 16px">
      <dl class="kv mb"><dt>Status</dt><dd>${esc(opt('fo_status', r.fo_status))}, ${esc(opt('hk_status', r.hk_status))}${r.service_status !== 'in_service' ? `, <b style="color:var(--bad)">${esc(opt('service_status', r.service_status))}</b> ${esc(r.ooo_reason || '')}` : ''}</dd>
        <dt>Reservation</dt><dd>${esc(opt('reservation_status', r.reservation_status))}${r.guest_name ? ' · ' + esc(r.guest_name) : ''} ${r.vip_code ? `<span class="badge b-vip">${esc(r.vip_code)}</span>` : ''}</dd>
        ${r.arrival_date ? `<dt>Stay</dt><dd>${esc(fmtDate(r.arrival_date))} to ${esc(fmtDate(r.departure_date))} · ${+r.adults} adult${+r.adults === 1 ? '' : 's'}</dd>` : ''}
        <dt>Attendant</dt><dd>${esc(r.attendant_name || 'Unassigned')}</dd><dt>Last cleaned</dt><dd>${esc(r.last_cleaned_at ? rel(r.last_cleaned_at) : '–')}</dd><dt>Inspected</dt><dd>${esc(r.last_inspected_at ? rel(r.last_inspected_at) : '–')}</dd><dt>Credits</dt><dd>${esc(r.hk_credits)} (${esc(r.clean_minutes)} min standard)</dd></dl>
      ${can(20) ? `<div class="small muted mb">Housekeeping status</div><div class="row mb">${btn('dirty', 'Dirty')}${btn('in_progress', '▶ Start cleaning')}${btn('clean', '✔ Clean')}${isSup ? btn('inspected', '★ Inspected') : ''}${btn('pickup', 'Pickup')}</div>
      <div class="row mb"><label class="check"><input type="checkbox" data-flag="dnd" ${r.dnd == 1 ? 'checked' : ''}> DND</label><label class="check"><input type="checkbox" data-flag="make_up_room" ${r.make_up_room == 1 ? 'checked' : ''}> Make up room</label>${isFd ? `<label class="check"><input type="checkbox" data-flag="rush" ${r.rush == 1 ? 'checked' : ''}> Rush</label>` : ''}<label class="check"><input type="checkbox" data-flag="eco_skip" ${r.eco_skip == 1 ? 'checked' : ''}> Declined service</label></div>
      ${isSup ? `<div class="row mb"><select id="ratt" style="flex:1">${listOptions(board.attendants, r.attendant_id, 'Unassigned')}</select><button class="btn sm" id="rattgo">Assign</button></div>` : ''}
      ${isFd ? (r.service_status === 'in_service' ? `<form class="row mb" id="ooo"><input type="text" name="reason" placeholder="Out of order reason" style="flex:1" required><input type="date" name="until" style="width:150px"><button class="btn danger sm">Set OOO</button></form>` : '<button class="btn mb" id="rts">↩ Return to service</button>') : ''}
      <div class="row mb"><a class="btn" href="#/new?room=${encodeURIComponent(r.number)}">🔧 Report a problem</a><a class="btn" href="#/new?room=${encodeURIComponent(r.number)}&category_id=${(S.categories.find(c => c.name === 'Extra towels or linen') || {}).id || ''}">🛏️ Guest request</a><a class="btn" href="#/inspect?room=${r.id}">📋 Inspect</a>${isFd && r.fo_status === 'occupied' ? '<button class="btn" id="mb">🍫 Minibar</button>' : ''}</div>` : ''}
      <h3 class="mt">Open tickets</h3><div id="rt" class="small muted">Loading…</div><h3 class="mt">Recent changes</h3><div id="rh" class="small"></div></div>`;
    document.body.appendChild(d);
    const close = () => d.remove();
    d.querySelector('[data-x]').onclick = close;
    const set = async body => { await run(() => api('POST', '/hk/rooms/' + r.id, body), 'Room ' + r.number + ' updated'); close(); reload(); };
    $$('[data-hk]', d).forEach(b => b.onclick = () => set({ hk_status: b.dataset.hk }));
    $$('[data-flag]', d).forEach(c => c.onchange = () => api('POST', '/hk/rooms/' + r.id, { [c.dataset.flag]: c.checked ? 1 : 0 }).then(() => { toast('Saved'); reload(); }).catch(x => toast(x.message, true)));
    $('#rattgo', d) && ($('#rattgo', d).onclick = () => api('POST', '/hk/assign', { room_ids: [r.id], attendant_id: $('#ratt', d).value || null }).then(() => { toast('Assigned'); close(); reload(); }));
    $('#ooo', d) && ($('#ooo', d).onsubmit = e => { e.preventDefault(); set({ service_status: 'ooo', ooo_reason: e.target.reason.value, ooo_until: e.target.until.value || null }); });
    $('#rts', d) && ($('#rts', d).onclick = () => set({ service_status: 'in_service', hk_status: 'dirty' }));
    $('#mb', d) && ($('#mb', d).onclick = () => modal('Minibar posting · room ' + r.number, `<form class="form" id="mbf"><div class="field"><label>Item</label><input name="item" required list="mbl"><datalist id="mbl"><option value="Sparkling water"><option value="Chips"><option value="Chocolate bar"><option value="Local craft beer"><option value="Wine (half bottle)"></datalist></div><div class="field"><label>Qty</label><input type="number" name="qty" value="1" min="1"></div><div class="field"><label>Charge</label><input type="number" step="0.01" name="amount" required></div><div class="full"><button class="btn primary">Post to folio</button></div></form>`, (el, c2) => {
      $('#mbf', el).onsubmit = async e => { e.preventDefault(); const f = e.target; const p = await run(() => api('POST', '/minibar', { room_id: r.id, item: f.item.value, qty: +f.qty.value, amount: +f.amount.value })); toast(p.pms_posted == 1 ? `Posted to Opera (${p.pms_reference})` : 'Recorded'); c2(); };
    }));
    api('GET', `/rooms/${r.id}/history`).then(h => {
      const open = h.tickets.filter(t => ['new', 'acknowledged', 'in_progress', 'on_hold'].includes(t.status));
      $('#rt', d).innerHTML = open.map(t => `<div class="mb"><a href="#/t/${t.id}">${esc(t.number)}</a> ${esc(t.title)} ${statusBadge(t.status)}</div>`).join('') || 'None.';
      $('#rh', d).innerHTML = h.log.slice(0, 12).map(l => `<div class="muted">${esc(rel(l.created_at))}: ${esc(l.field.replace('_', ' '))} ${esc(l.old_value)} → <b>${esc(l.new_value)}</b> ${esc(l.user_name || l.source)}</div>`).join('');
      d.addEventListener('click', e => { if (e.target.closest('a[href^="#/"]')) close(); });
    });
  }

  // ---------- food safety ----------
  async function viewFood(v) {
    const [pts, logs] = await Promise.all([api('GET', '/food/points'), api('GET', '/e/temp_logs?limit=40')]);
    await refs('temp_points');
    v.innerHTML = `<div class="head"><h1>Food safety (HACCP)</h1><span class="sub">Tap a unit to log a reading. Out-of-range readings open an urgent kitchen ticket.</span></div>
      <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">${pts.points.map(p => `<button class="temp ${p.last_at && !+p.last_in_range ? 'over' : p.overdue ? 'due' : ''}" data-pt="${p.id}" style="text-align:left;cursor:pointer;font:inherit;color:inherit">
        <div><b>${esc(p.name)}</b><div class="small muted">${esc(opt('temp_kind', p.kind))} · ${esc(p.min_c)} to ${esc(p.max_c)} °C${p.sensor_id ? ' · 📡 sensor' : ''}</div><div class="small ${p.overdue ? '' : 'muted'}" style="${p.overdue ? 'color:var(--warn);font-weight:700' : ''}">${p.last_at ? 'Last ' + esc(rel(p.last_at)) : 'Never logged'}${p.overdue ? ' · check due' : ''}</div></div>
        <div class="r" style="color:${p.last_at && !+p.last_in_range ? 'var(--bad)' : 'inherit'}">${p.last_reading != null ? esc((+p.last_reading).toFixed(1)) + '°' : '–'}</div></button>`).join('')}</div>
      <div class="card mt flush"><h3>Recent readings</h3><div class="table-wrap"><table class="t"><thead><tr><th>When</th><th>Point</th><th>°C</th><th>In range</th><th>Source</th><th>By</th><th>Corrective action</th></tr></thead><tbody>
      ${logs.rows.map(l => `<tr><td>${esc(fmt(l.created_at))}</td><td>${esc(refLabel('temp_points', l.point_id))}</td><td><b>${esc((+l.reading_c).toFixed(1))}</b></td><td>${+l.in_range ? '✓' : `<span class="badge p-urgent">No</span>${l.ticket_id ? ` <a href="#/t/${l.ticket_id}">ticket</a>` : ''}`}</td><td>${esc(l.source)}</td><td>${esc(userName(l.recorded_by))}</td><td class="small">${esc(l.corrective_action || '')}</td></tr>`).join('')}</tbody></table></div></div>`;
    v.querySelector('.grid').onclick = e => {
      const b = e.target.closest('[data-pt]'); if (!b || !can(20)) return;
      const p = pts.points.find(x => +x.id === +b.dataset.pt);
      modal('Log ' + p.name, `<form class="form" id="rf"><div class="field"><label>Reading (°C)</label><input type="number" step="0.1" name="reading_c" required inputmode="decimal" autofocus></div><div class="field"><label>Safe range</label><div style="padding-top:8px">${esc(p.min_c)} to ${esc(p.max_c)} °C</div></div><div class="field full"><label>Corrective action (if out of range)</label><textarea name="corrective_action"></textarea></div><div class="full"><button class="btn primary">Save reading</button></div></form>`, (el, close) => {
        $('#rf', el).onsubmit = async e2 => { e2.preventDefault(); const r = await run(() => api('POST', '/food/readings', { point_id: p.id, reading_c: +e2.target.reading_c.value, corrective_action: e2.target.corrective_action.value })); toast(r.in_range ? 'In range ✓' : 'Out of range: urgent ticket opened', !r.in_range); close(); route(); };
      });
    };
  }

  // ---------- inspections ----------
  async function viewInspect(v) {
    const t = await api('GET', '/e/checklist_templates?f[active]=1&limit=200');
    const runs = await api('GET', '/e/checklist_runs?limit=15');
    await refs('checklist_templates');
    const room = new URLSearchParams(location.hash.split('?')[1] || '').get('room');
    v.innerHTML = `<div class="head"><h1>Inspections and checklists</h1></div><div class="grid g2"><div class="card"><h3>Start a checklist</h3>${t.rows.map(x => `<a class="titem" href="#/inspect/${x.id}${room ? '?room=' + room : ''}" style="grid-template-columns:1fr auto"><div><b>${esc(x.name)}</b><div class="small muted">${esc(opt('checklist_kind', x.kind))} · pass ${x.pass_mark}%</div></div>${deptChip(x.department_id)}</a>`).join('')}</div>
      <div class="card"><h3>Recently completed</h3>${runs.rows.map(r => `<div class="row small mb"><span class="sp"><a href="#/e/checklist_runs/${r.id}">${esc(refLabel('checklist_templates', r.template_id))}</a>${r.room_id ? ' · room ' + esc(roomNum(r.room_id)) : ''}<br><span class="muted">${esc(userName(r.performed_by))} · ${esc(rel(r.created_at))}</span></span><span class="badge ${+r.passed ? 's-resolved' : 'p-urgent'}">${r.score}%</span></div>`).join('') || '<p class="muted">None yet.</p>'}</div></div>`;
  }
  async function viewInspectRun(v, tid, qs) {
    const t = await api('GET', '/e/checklist_templates/' + tid);
    const lines = String(t.items || '').split(/\r?\n/).map(x => x.trim()).filter(Boolean);
    const res = {};
    v.innerHTML = `<div class="head"><a class="btn sm" href="#/inspect">←</a><h1>${esc(t.name)}</h1><span class="sub">Pass mark ${t.pass_mark}%${+t.create_tickets_on_fail ? ' · failed items raise tickets automatically' : ''}</span></div>
      <div class="card"><div class="form mb"><div class="field"><label>Room</label><select id="iroom">${listOptions(S.rooms, qs.get('room'), '(not a room)', 'number')}</select></div><div class="field"><label>Location</label><select id="iloc">${listOptions(S.locations, '', '(none)')}</select></div></div>
      ${lines.map((l, i) => l.startsWith('##') ? `<div class="sect">${esc(l.replace(/^##\s*/, ''))}</div>` : `<div class="inspect-item" data-i="${i}"><div>${esc(l)}<input type="text" class="note mt" placeholder="What is wrong?" hidden></div><div class="seg"><button data-r="pass">✓</button><button data-r="fail">✕</button><button data-r="na">N/A</button></div></div>`).join('')}
      <div class="field mt"><label>Notes</label><textarea id="inotes"></textarea></div><div class="row mt"><button class="btn primary" id="isub">Submit</button><span class="sub" id="iprog"></span></div></div>`;
    const prog = () => { const n = lines.filter(l => !l.startsWith('##')).length; $('#iprog').textContent = `${Object.keys(res).length} of ${n} answered`; };
    v.addEventListener('click', e => {
      const b = e.target.closest('[data-r]'); if (!b) return;
      const row = b.closest('[data-i]'); const i = row.dataset.i;
      res[i] = b.dataset.r; $$('button', row).forEach(x => x.classList.toggle('on', x === b));
      row.classList.toggle('fail', b.dataset.r === 'fail'); $('.note', row).hidden = b.dataset.r !== 'fail'; if (b.dataset.r === 'fail') $('.note', row).focus();
      prog();
    });
    prog();
    $('#isub').onclick = async () => {
      const results = lines.map((l, i) => l.startsWith('##') ? null : { item: l, result: res[i] || 'na', note: $(`[data-i="${i}"] .note`)?.value || '' }).filter(Boolean);
      const r = await run(() => api('POST', '/checklists/run', { template_id: tid, room_id: $('#iroom').value || null, location_id: $('#iloc').value || null, results, notes: $('#inotes').value }));
      modal(r.passed ? 'Passed ✓' : 'Below pass mark', `<p style="font-size:2rem;font-family:var(--display);margin:0">${r.score}%</p><p>${r.tickets_created ? `${r.tickets_created} ticket(s) opened for failed items.` : 'No tickets needed.'}</p><a class="btn primary" href="#/inspect">Done</a>`, (el, close) => el.addEventListener('click', e => { if (e.target.closest('a')) close(); }));
    };
  }

  // ---------- logbook ----------
  async function viewLogbook(v) {
    const myDept = S.user.department_id || '';
    const f = { dept: new URLSearchParams(location.hash.split('?')[1] || '').get('d') ?? (S.user.sees_all ? '' : String(myDept)) };
    const j = await api('GET', '/e/logbook?limit=100' + (f.dept ? '&f[department_id]=' + f.dept : ''));
    const shiftNow = (() => { const h = +new Intl.DateTimeFormat(LOC, { timeZone: tz(), hour: 'numeric', hourCycle: 'h23' }).format(new Date()); return h >= 7 && h < 15 ? 'day' : h >= 15 && h < 23 ? 'swing' : 'night'; })();
    v.innerHTML = `<div class="head"><h1>Shift logbook</h1><select id="ld" style="width:auto">${listOptions(S.departments, f.dept, 'All departments')}</select></div>
      ${can(30) ? `<div class="card mb"><form class="form" id="lf"><div class="field full"><label>Pass-on note</label><textarea name="entry" required placeholder="What does the next shift need to know?"></textarea></div>
        <div class="field"><label>Department</label><select name="department_id" required>${listOptions(S.departments, myDept)}</select></div><div class="field"><label>Importance</label><select name="importance">${options(S.opt.importance, 'info')}</select></div>
        <div class="field"><label>Shift</label><select name="shift">${options(S.opt.shift, shiftNow)}</select></div><div class="field"><label>Room</label><select name="room_id">${listOptions(S.rooms, '', '(none)', 'number')}</select></div>
        <div class="full"><button class="btn primary">Add to log</button></div></form></div>` : ''}
      <div class="card flush">${j.rows.map(l => { const acks = l.acknowledged_by ? Object.values(JSON.parse(l.acknowledged_by || '{}')) : []; const mine = acks.some(a => a.name === S.user.name); return `<div class="titem" style="grid-template-columns:auto 1fr auto"><span class="pbar ${l.importance === 'action' ? 'pb-urgent' : l.importance === 'important' ? 'pb-high' : 'pb-normal'}"></span><div>${nl2br(l.entry)}</div><div>${mine ? '<span class="small muted">✓ Read</span>' : `<button class="btn sm" data-ack="${l.id}">Mark read</button>`}</div><div class="meta">${deptChip(l.department_id)}<span>${esc(opt('shift', l.shift))} shift</span><span>${esc(userName(l.author_id))}</span><span>${esc(fmt(l.created_at))}</span>${l.room_id ? `<span>🚪 ${esc(roomNum(l.room_id))}</span>` : ''}${acks.length ? `<span>Read by ${esc(acks.map(a => a.name).join(', '))}</span>` : ''}</div></div>`; }).join('') || '<div class="empty">No entries.</div>'}</div>`;
    $('#ld').onchange = e => { location.hash = '#/logbook?d=' + e.target.value; };
    $('#lf') && ($('#lf').onsubmit = async e => { e.preventDefault(); const body = Object.fromEntries(new FormData(e.target)); await run(() => api('POST', '/e/logbook', body), 'Logged'); route(); });
    v.addEventListener('click', async e => { const b = e.target.closest('[data-ack]'); if (!b) return; await api('POST', `/logbook/${b.dataset.ack}/ack`, {}); b.outerHTML = '<span class="small muted">✓ Read</span>'; });
  }

  // ---------- reports ----------
  async function viewReports(v, qs) {
    const days = qs.get('days') || '30', dep = qs.get('department_id') || '';
    const r = await api('GET', `/reports?days=${days}${dep ? '&department_id=' + dep : ''}`);
    const max = Math.max(1, ...r.volume.map(x => +x.n), ...r.resolved.map(x => +x.n));
    const resMap = Object.fromEntries(r.resolved.map(x => [x.d, +x.n]));
    const hb = (rows, label, val, fmtv = x => x) => { const m = Math.max(1, ...rows.map(x => +x[val] || 0)); return rows.map(x => `<div class="hbar"><span title="${esc(x[label])}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(x[label])}</span><span class="track"><i style="width:${100 * (+x[val] || 0) / m}%"></i></span><span class="right">${esc(fmtv(x[val]))}</span></div>`).join('') || '<p class="muted">No data.</p>'; };
    const g = r.guest || {};
    v.innerHTML = `<div class="head"><h1>Reports</h1><select id="rd" style="width:auto">${options({ 7: 'Last 7 days', 30: 'Last 30 days', 90: 'Last 90 days', 365: 'Last 12 months' }, days)}</select><select id="rdep" style="width:auto">${listOptions(S.departments, dep, 'All departments')}</select><a class="btn" href="${BASE}/api/reports/tickets.csv?status=" download>⬇ Export tickets</a><button class="btn no-print" onclick="print()">🖨 Print</button></div>
      <div class="card mb"><h3>Ticket volume <span class="sp"></span><span class="small"><span style="color:var(--c-accent2)">■</span> created <span style="color:var(--c-accent)">■</span> resolved</span></h3><div class="bars">${r.volume.map(x => `<div style="height:${100 * x.n / max}%" title="${esc(x.d)}: ${x.n} created"></div><div class="b2" style="height:${100 * (resMap[x.d] || 0) / max}%" title="${esc(x.d)}: ${resMap[x.d] || 0} resolved"></div>`).join('')}</div></div>
      <div class="kpis"><div class="kpi"><div class="v">${+g.requests || 0}</div><div class="l">Guest requests</div></div><div class="kpi"><div class="v">${+g.complaints || 0}</div><div class="l">Complaints</div></div><div class="kpi"><div class="v">${g.csat ? (+g.csat).toFixed(1) : '–'}</div><div class="l">Guest rating / 5</div></div><div class="kpi"><div class="v">${mins(g.avg_resolve)}</div><div class="l">Guest request turnaround</div></div><div class="kpi"><div class="v">${money(g.recovery || 0)}</div><div class="l">Service recovery</div></div>
        <div class="kpi"><div class="v">${r.pm.pm_generated ? Math.round(100 * (+r.pm.on_time || 0) / r.pm.pm_generated) + '%' : '–'}</div><div class="l">PM on time (${+r.pm.completed || 0}/${+r.pm.pm_generated || 0})</div></div><div class="kpi"><div class="v">${r.inspections.avg_score ? Math.round(r.inspections.avg_score) + '%' : '–'}</div><div class="l">Inspection average (${+r.inspections.runs || 0})</div></div><div class="kpi ${+r.haccp.out_of_range ? 'warn' : 'ok'}"><div class="v">${+r.haccp.out_of_range || 0}</div><div class="l">HACCP out of range (of ${+r.haccp.readings || 0})</div></div></div>
      <div class="card mb flush"><h3>By department</h3><div class="table-wrap"><table class="t"><thead><tr><th>Department</th><th class="right">Created</th><th class="right">Resolved</th><th class="right">Avg response</th><th class="right">Avg resolve</th><th class="right">SLA missed</th><th class="right">Labour</th><th class="right">Parts</th></tr></thead><tbody>${r.by_department.map(x => `<tr><td>${esc(x.name)}</td><td class="right">${x.created}</td><td class="right">${x.resolved}</td><td class="right">${mins(x.avg_response)}</td><td class="right">${mins(x.avg_resolve)}</td><td class="right">${+x.breached ? `<b style="color:var(--bad)">${x.breached}</b>` : 0}</td><td class="right">${mins(+x.labour)}</td><td class="right">${money(x.parts)}</td></tr>`).join('')}</tbody></table></div></div>
      <div class="grid g2"><div class="card"><h3>Top categories</h3>${hb(r.by_category, 'name', 'n')}</div><div class="card"><h3>Where requests come from</h3>${hb(r.by_source.map(x => ({ ...x, label: opt('source', x.source) })), 'label', 'n')}</div>
        <div class="card"><h3>Repeat-problem rooms</h3>${r.repeat_rooms.map(x => `<div class="row small mb"><b style="width:60px">🚪 ${esc(x.number)}</b><span class="sp muted">${esc(x.issues || '')}</span><span class="badge p-high">${x.n}×</span></div>`).join('') || '<p class="muted">None.</p>'}</div>
        <div class="card"><h3>Assets with most work</h3>${r.top_assets.map(x => `<div class="row small mb"><b>${esc(x.tag)}</b><span class="sp">${esc(x.name)}</span><span>${x.n} tickets · ${mins(+x.labour)} · ${money(x.parts)}</span></div>`).join('') || '<p class="muted">None.</p>'}</div>
        <div class="card"><h3>Staff resolved</h3>${hb(r.staff, 'name', 'resolved')}</div><div class="card"><h3>Rooms cleaned by attendant</h3>${hb(r.housekeeping, 'name', 'rooms')}</div>
        <div class="card"><h3>By priority</h3>${r.by_priority.map(x => `<div class="row small mb">${prioBadge(x.priority)}<span class="sp"></span><span>${x.n} tickets · avg ${mins(x.avg_resolve)} · ${x.breached} missed SLA</span></div>`).join('')}</div></div>`;
    $('#rd').onchange = e => { location.hash = `#/reports?days=${e.target.value}&department_id=${dep}`; };
    $('#rdep').onchange = e => { location.hash = `#/reports?days=${days}&department_id=${e.target.value}`; };
  }

  async function viewCalendar(v) {
    const c = await api('GET', '/calendar');
    const items = [...c.pm.map(x => ({ d: x.next_due, t: `🛠 PM: ${x.name}`, s: x.department, h: `#/e/pm_schedules/${x.id}` })), ...c.events.map(x => ({ d: localInput(x.start_at).slice(0, 10), t: `🎉 ${x.name}`, s: `${fmt(x.start_at, { month: undefined, day: undefined })} · ${x.guests} guests`, h: `#/e/events/${x.id}` })), ...c.scheduled.map(x => ({ d: localInput(x.scheduled_for).slice(0, 10), t: `🎫 ${x.number} ${x.title}`, s: fmt(x.scheduled_for, { month: undefined, day: undefined }), h: `#/t/${x.id}` }))].sort((a, b) => a.d < b.d ? -1 : 1);
    const days = [...new Set(items.map(i => i.d))];
    v.innerHTML = `<div class="head"><h1>Calendar</h1><span class="sub">Next 31 days: preventive maintenance, events and scheduled work</span></div>${days.map(d => `<div class="card mb"><h3>${esc(fmtDate(d))}</h3>${items.filter(i => i.d === d).map(i => `<div class="row small mb"><a href="${i.h}" class="sp">${esc(i.t)}</a><span class="muted">${esc(i.s || '')}</span></div>`).join('')}</div>`).join('') || '<div class="empty">Nothing scheduled.</div>'}`;
  }

  // ---------- generic records ----------
  async function cellValue(f, val, row) {
    if (val === null || val === undefined || val === '') return '';
    switch (f.type) {
      case 'ref': case 'readonly_ref': return esc(f.ref === 'users' ? userName(val) : f.ref === 'departments' ? (dept(val) || {}).name : f.ref === 'rooms' ? roomNum(val) : f.ref === 'locations' ? locName(val) : refLabel(f.ref, val));
      case 'select': return esc((f.options || {})[val] ?? val);
      case 'bool': return +val ? '✓' : '';
      case 'datetime': return esc(fmt(val));
      case 'date': return esc(fmtDate(val));
      case 'decimal': return /cost|amount|price|value/i.test(f.label) ? money(val) : esc(val);
      case 'colour': return `<span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:${esc(val)}"></span>`;
      case 'readonly': return /_at$/.test(f.key || '') ? esc(fmt(val)) : esc(val);
      default: return esc(String(val).length > 90 ? String(val).slice(0, 90) + '…' : val);
    }
  }
  async function preloadRefs(meta) {
    const needed = [...new Set(Object.values(meta.fields).filter(f => (f.type === 'ref' || f.type === 'readonly_ref' || f.type === 'multiref') && f.ref).map(f => f.ref))];
    await Promise.all(needed.map(r => refs(r).catch(() => [])));
  }

  async function viewList(v, e, qs) {
    const m = S.meta[e]; if (!m) { v.innerHTML = '<div class="empty">Not available.</div>'; return; }
    await preloadRefs(m);
    const q = qs.get('q') || '';
    const filters = {}; for (const [k, val] of qs.entries()) if (k.startsWith('f.')) filters[k.slice(2)] = val;
    const p = new URLSearchParams({ limit: 200 }); if (q) p.set('q', q); for (const [k, val] of Object.entries(filters)) p.set(`f[${k}]`, val); if (qs.get('all')) p.set('all', 1);
    const j = await api('GET', `/e/${e}?` + p);
    const selFields = Object.entries(m.fields).filter(([, f]) => f.type === 'select' && m.list.includes(Object.keys(m.fields).find(k => m.fields[k] === f))).slice(0, 2);
    const rows = await Promise.all(j.rows.map(async r => `<tr class="click" data-id="${r.id}">${(await Promise.all(m.list.map(async c => `<td>${await cellValue({ ...(m.fields[c] || { type: c.endsWith('_at') ? 'datetime' : 'text', label: c }), key: c }, r[c], r)}</td>`))).join('')}</tr>`));
    v.innerHTML = `<div class="head"><h1>${esc(m.label)}</h1><span class="sub">${j.total} record${j.total === 1 ? '' : 's'}</span>${e === 'menu_86' ? `<a class="btn sm" href="#/e/menu_86?all=1">Show history</a>` : ''}${m.can_write ? `<a class="btn gold" href="#/e/${e}/new">➕ New ${esc(m.one.toLowerCase())}</a>` : ''}</div>
      <div class="filters">${m.search ? `<input type="search" id="lq" placeholder="Search ${esc(m.label.toLowerCase())}" value="${esc(q)}">` : ''}${selFields.map(([k, f]) => `<select data-ff="${k}">${options(f.options, filters[k], 'Any ' + f.label.toLowerCase())}</select>`).join('')}</div>
      <div class="card flush"><div class="table-wrap"><table class="t"><thead><tr>${m.list.map(c => `<th>${esc((m.fields[c] || {}).label || c.replace(/_/g, ' '))}</th>`).join('')}</tr></thead><tbody>${rows.join('') || `<tr><td colspan="${m.list.length}" class="empty">Nothing here yet.</td></tr>`}</tbody></table></div></div>`;
    const go = () => { const n = new URLSearchParams(); const qq = $('#lq')?.value; if (qq) n.set('q', qq); $$('[data-ff]').forEach(s => s.value && n.set('f.' + s.dataset.ff, s.value)); location.hash = `#/e/${e}?` + n; };
    $('#lq') && ($('#lq').onchange = go); $$('[data-ff]').forEach(s => s.onchange = go);
    v.querySelector('tbody').onclick = ev => { const tr = ev.target.closest('tr[data-id]'); if (tr) location.hash = `#/e/${e}/${tr.dataset.id}`; };
  }

  function fieldInput(k, f, val) {
    const req = f.required ? ' required' : '', dis = '';
    switch (f.type) {
      case 'textarea': case 'lines': return `<textarea name="${k}"${req} ${f.type === 'lines' ? 'rows="10"' : ''}>${esc(val)}</textarea>`;
      case 'number': return `<input type="number" name="${k}" value="${esc(val)}"${req}>`;
      case 'decimal': return `<input type="number" step="0.01" name="${k}" value="${esc(val)}"${req}>`;
      case 'bool': return `<label class="check"><input type="checkbox" name="${k}" ${+val ? 'checked' : ''}> Yes</label>`;
      case 'date': return `<input type="date" name="${k}" value="${esc(val ? String(val).slice(0, 10) : '')}"${req}>`;
      case 'datetime': return `<input type="datetime-local" name="${k}" value="${esc(localInput(val))}"${req}>`;
      case 'select': return `<select name="${k}"${req}>${options(f.options, val, f.required ? undefined : '')}</select>`;
      case 'ref': return `<select name="${k}"${req}>${listOptions(refCache[f.ref] || [], val, f.required ? 'Choose…' : '(none)', 'label')}</select>`;
      case 'multiref': { const sel = JSON.parse(val || '[]').map(Number); return `<select name="${k}" multiple size="5">${(refCache[f.ref] || []).map(r => `<option value="${r.id}" ${sel.includes(+r.id) ? 'selected' : ''}>${esc(r.label)}</option>`).join('')}</select>`; }
      case 'colour': return `<input type="color" name="${k}" value="${esc(val || '#4E9AA0')}" style="height:40px;width:80px">`;
      case 'email': return `<input type="email" name="${k}" value="${esc(val)}"${req}>`;
      case 'tel': return `<input type="tel" name="${k}" value="${esc(val)}"${req}>`;
      case 'readonly': return `<div style="padding-top:8px">${esc(/_at$/.test(k) ? fmt(val) : val ?? '–')}</div>`;
      case 'readonly_ref': return `<div style="padding-top:8px">${esc(f.ref === 'users' ? userName(val) : refLabel(f.ref, val)) || '–'}</div>`;
      default: return `<input type="text" name="${k}" value="${esc(val)}"${req}${dis}>`;
    }
  }

  async function viewRecord(v, e, idOrNew, qs) {
    const m = S.meta[e]; if (!m) { v.innerHTML = '<div class="empty">Not available.</div>'; return; }
    await preloadRefs(m);
    const isNew = idOrNew === 'new';
    const r = isNew ? Object.fromEntries([...qs.entries()]) : await api('GET', `/e/${e}/${idOrNew}`);
    const editable = m.can_write || (e === 'purchase_requests' && can(30));
    const wide = ['textarea', 'lines', 'multiref'];
    let extra = '';
    if (!isNew) {
      if (r._tickets?.length) extra += `<div class="card mt flush"><h3>Tickets</h3><div class="tlist mt">${r._tickets.map(t => `<a class="titem" href="#/t/${t.id}" style="grid-template-columns:1fr auto"><div><span class="muted small">${esc(t.number)}</span> ${esc(t.title)}</div><div>${statusBadge(t.status)}</div></a>`).join('')}</div></div>`;
      if (r._pm?.length) extra += `<div class="card mt"><h3>PM schedules</h3>${r._pm.map(p => `<div class="small mb"><a href="#/e/pm_schedules/${p.id}">${esc(p.name)}</a> · ${esc(opt('frequency', p.frequency))} · next ${esc(fmtDate(p.next_due))}</div>`).join('')}</div>`;
      if (r._movements?.length) extra += `<div class="card mt"><h3>Stock movements</h3>${r._movements.map(x => `<div class="small mb">${esc(fmt(x.created_at))}: <b>${+x.qty > 0 ? '+' : ''}${esc(x.qty)}</b> ${esc(x.reason)} ${esc(x.user_name || '')} ${x.ticket_id ? `<a href="#/t/${x.ticket_id}">ticket</a>` : ''}</div>`).join('')}</div>`;
      if (e === 'checklist_runs' && Array.isArray(r.results)) extra += `<div class="card mt"><h3>Results</h3>${r.results.map(x => `<div class="row small mb"><span class="sp">${esc(x.item)}${x.note ? ` <i class="muted">${esc(x.note)}</i>` : ''}</span><span class="badge ${x.result === 'pass' ? 's-resolved' : x.result === 'fail' ? 'p-urgent' : 's-on_hold'}">${esc(x.result)}</span></div>`).join('')}</div>`;
      if (r._attachments?.length) extra += `<div class="card mt"><h3>Files</h3><div class="attach">${r._attachments.map(a => `<a href="${BASE}/api/files/${a.id}" target="_blank" rel="noopener">${a.mime.startsWith('image/') ? `<img src="${BASE}/api/files/${a.id}" alt="">` : '📄 ' + esc(a.filename)}</a>`).join('')}</div></div>`;
    }
    const actions = [];
    if (!isNew) {
      if (e === 'assets') actions.push(`<a class="btn" href="#/new?asset_id=${r.id}&location_id=${r.location_id || ''}">🔧 Work order</a>`);
      if (e === 'rooms') actions.push(`<a class="btn" href="#/room/${r.id}">🛏️ Housekeeping</a>`, `<a class="btn" href="#/new?room=${encodeURIComponent(r.number)}">🔧 Report problem</a>`);
      if (e === 'parts' && can(30)) actions.push('<button class="btn" data-act="adjust">± Adjust stock</button>');
      if (e === 'purchase_requests' && can(70)) actions.push(...['approve', 'reject', 'ordered'].map(a => `<button class="btn" data-act="pr-${a}">${{ approve: '✔ Approve', reject: '✕ Reject', ordered: '📦 Ordered' }[a]}</button>`));
      if (e === 'purchase_requests' && can(30)) actions.push('<button class="btn" data-act="pr-received">📥 Received</button>');
      if (e === 'users' && can(90)) actions.push('<button class="btn" data-act="reset-password">🔑 Reset password</button><button class="btn" data-act="unlock">🔓 Unlock</button><button class="btn" data-act="reset-mfa">↺ Reset MFA</button>');
      if (e === 'events') actions.push(`<a class="btn" href="#/new?type=event_task&title=${encodeURIComponent('Task for ' + r.name)}">➕ Event task</a>`);
      if (e === 'incidents' || e === 'lost_found') actions.push('<button class="btn" onclick="print()">🖨 Print</button>');
    }
    const fieldsHtml = Object.entries(m.fields).filter(([k, f]) => !(isNew && f.type.startsWith('readonly'))).map(([k, f]) => `<div class="field ${wide.includes(f.type) ? 'full' : ''}"><label>${esc(f.label)}${f.required ? ' <span class="req">*</span>' : ''}</label>${fieldInput(k, f, r[k])}</div>`).join('');
    v.innerHTML = `<div class="head"><a class="btn sm" href="#/e/${e}">←</a><h1>${isNew ? 'New ' + esc(m.one.toLowerCase()) : esc(r[m.title] ?? m.one)}</h1>${actions.join('')}</div>
      ${e === 'users' && isNew ? '<div class="ann">A temporary password is generated. Share it with the person directly; they must change it at first sign-in.</div>' : ''}
      <div class="card"><form class="form" id="rf">${fieldsHtml}<div class="full row">${editable ? `<button class="btn primary">${isNew ? 'Create' : 'Save changes'}</button>` : ''}${!isNew && can(70) && editable ? '<span class="sp"></span><button type="button" class="btn danger" id="del">Delete</button>' : ''}${!isNew && can(20) ? '<label class="btn sm">📎 Attach file<input type="file" id="att" hidden></label>' : ''}</div></form></div>${extra}`;
    if (!editable) $$('#rf input,#rf select,#rf textarea').forEach(x => x.disabled = true);
    $('#rf').onsubmit = async ev => {
      ev.preventDefault();
      const body = {};
      for (const [k, f] of Object.entries(m.fields)) {
        const el = ev.target.elements[k]; if (!el || f.type.startsWith('readonly')) continue;
        if (f.type === 'bool') body[k] = el.checked;
        else if (f.type === 'multiref') body[k] = [...el.selectedOptions].map(o => +o.value);
        else body[k] = el.value;
      }
      try {
        if (e === 'users' && isNew) {
          const res = await api('POST', '/admin/users', body);
          modal('User created', `<p>Temporary password for <b>${esc(body.username)}</b>:</p><p class="code" style="font-size:1.1rem">${esc(res.temporary_password)}</p><p class="small muted">Shown once. They will be asked to change it.</p>`);
          location.hash = `#/e/users/${res.id}`; return;
        }
        const res = await api(isNew ? 'POST' : 'PATCH', isNew ? `/e/${e}` : `/e/${e}/${r.id}`, body);
        toast(isNew ? 'Created' : 'Saved');
        if (isNew) location.hash = `#/e/${e}/${res.id}`; else route();
        if (['departments', 'rooms', 'locations', 'categories', 'users'].includes(e)) { const b = await api('GET', '/bootstrap'); Object.assign(S, b); Object.keys(refCache).forEach(x => delete refCache[x]); }
      } catch (x) { toast(x.message, true); }
    };
    $('#del') && ($('#del').onclick = async () => { if (!confirm(`Delete this ${m.one.toLowerCase()}? This is recorded in the audit log.`)) return; await run(() => api('DELETE', `/e/${e}/${r.id}`), 'Deleted'); location.hash = `#/e/${e}`; });
    $('#att') && ($('#att').onchange = async ev => { const fd = new FormData(); fd.append('file', ev.target.files[0]); await run(() => api('POST', `/e/${e}/${r.id}/attachments`, fd), 'Attached'); route(); });
    v.addEventListener('click', async ev => {
      const b = ev.target.closest('[data-act]'); if (!b) return;
      const a = b.dataset.act;
      if (a === 'adjust') return modal('Adjust stock: ' + r.name, `<form class="form" id="af"><div class="field"><label>Change (+ received, - used or lost)</label><input type="number" step="0.1" name="qty" required></div><div class="field"><label>Reason</label><select name="reason">${options({ received: 'Received', count: 'Stock count correction', used: 'Used (no ticket)', damaged: 'Damaged / discarded', transfer: 'Transfer' }, 'received')}</select></div><div class="field full"><label>Note</label><input type="text" name="note"></div><div class="full"><button class="btn primary">Save</button></div></form>`, (el, close) => { $('#af', el).onsubmit = async x => { x.preventDefault(); const f = x.target; await run(() => api('POST', `/parts/${r.id}/adjust`, { qty: +f.qty.value, reason: f.reason.value, note: f.note.value }), 'Stock updated'); close(); route(); }; });
      if (a.startsWith('pr-')) { await run(() => api('POST', `/pr/${r.id}/${a.slice(3)}`, {}), 'Updated'); return route(); }
      if (['reset-password', 'unlock', 'reset-mfa'].includes(a)) { if (!confirm('Are you sure?')) return; const res = await run(() => api('POST', `/admin/users/${r.id}/${a}`, {}), 'Done'); if (res.temporary_password) modal('Temporary password', `<p class="code" style="font-size:1.1rem">${esc(res.temporary_password)}</p><p class="small muted">Shown once.</p>`); }
    });
  }

  // ---------- account ----------
  async function viewAccount(v) {
    const u = S.user;
    let theme = 'auto'; try { theme = localStorage.getItem('exptix-theme') || 'auto'; } catch (e) {}
    v.innerHTML = `<div class="head"><h1>My account</h1><button class="btn" id="out">Sign out</button></div>
      ${u.must_change_password ? '<div class="ann">Set a new password to continue.</div>' : ''}
      <div class="grid g2"><div class="card"><h3>${esc(u.name)}</h3><dl class="kv"><dt>Username</dt><dd>${esc(u.username)}</dd><dt>Role</dt><dd>${esc(u.role_label)}</dd><dt>Department</dt><dd>${deptChip(u.department_id) || '–'}</dd><dt>Title</dt><dd>${esc(u.title || '')}</dd></dl>
        <form class="form mt" id="pf"><div class="field"><label>Email</label><input type="email" name="email" value="${esc(u.email || '')}"></div><div class="field"><label>Mobile (SMS alerts)</label><input type="tel" name="phone" value="${esc(u.phone || '')}"></div>
        <label class="check"><input type="checkbox" name="on_shift" ${u.on_shift ? 'checked' : ''}> On shift</label><label class="check"><input type="checkbox" name="notify_push" ${u.notify_push ? 'checked' : ''}> Push alerts</label><label class="check"><input type="checkbox" name="notify_email" ${u.notify_email ? 'checked' : ''}> Email alerts</label><label class="check"><input type="checkbox" name="notify_sms" ${u.notify_sms ? 'checked' : ''}> SMS for urgent alerts</label>
        <div class="field"><label>Theme</label><select id="theme">${options({ auto: 'Match device', light: 'Light', dark: 'Dark' }, theme)}</select></div>
        <div class="full"><button class="btn primary">Save</button></div></form></div>
      <div><div class="card mb"><h3>📱 This device</h3><p class="small muted">Get alerts on this phone or computer even when ${esc(S.app.name)} is closed. On iPhone, add ${esc(S.app.name)} to the Home Screen first (Share, Add to Home Screen).</p><div class="row"><button class="btn primary" id="push">Enable push alerts</button><button class="btn" id="ptest">Send a test</button></div></div>
        <div class="card mb"><h3>🔐 Two-step sign-in</h3>${u.mfa_enabled ? '<p>Enabled with an authenticator app.</p><form class="row" id="mfaoff"><input type="text" name="code" placeholder="Current code" inputmode="numeric" style="width:140px"><button class="btn danger sm">Turn off</button></form>' : '<p class="small muted">Use Microsoft Authenticator, Google Authenticator or 1Password.</p><button class="btn primary" id="mfaon">Set up</button>'}</div>
        <div class="card"><h3>Change password</h3><form class="form" id="cpw"><div class="field full"><label>Current password</label><input type="password" name="current" autocomplete="current-password" required></div><div class="field full"><label>New password (12+ characters; a phrase works well)</label><input type="password" name="new" autocomplete="new-password" minlength="12" required></div><div class="full"><button class="btn primary">Change password</button></div></form></div></div></div>`;
    $('#out').onclick = async () => { await api('POST', '/auth/logout', {}); S = null; CSRF = null; renderLogin('Signed out.'); };
    $('#theme').onchange = e => { try { localStorage.setItem('exptix-theme', e.target.value === 'auto' ? '' : e.target.value); } catch (x) {} if (e.target.value === 'auto') delete document.documentElement.dataset.theme; else document.documentElement.dataset.theme = e.target.value; };
    $('#pf').onsubmit = async e => { e.preventDefault(); const f = e.target; S.user = await run(() => api('PATCH', '/account', { email: f.email.value, phone: f.phone.value, on_shift: f.on_shift.checked, notify_push: f.notify_push.checked, notify_email: f.notify_email.checked, notify_sms: f.notify_sms.checked }), 'Saved'); };
    $('#cpw').onsubmit = async e => { e.preventDefault(); const f = e.target; await run(() => api('POST', '/account/password', { current: f.current.value, new: f.new.value }), 'Password changed'); S.user.must_change_password = false; f.reset(); };
    $('#push').onclick = enablePush;
    $('#ptest').onclick = () => run(() => api('POST', '/push/test', {}), 'Test sent');
    $('#mfaon') && ($('#mfaon').onclick = async () => {
      const s = await api('POST', '/account/mfa/setup', {});
      modal('Set up two-step sign-in', `<p class="small">Scan with your authenticator app, then enter the 6-digit code.</p><div id="qrc" style="background:#fff;padding:10px;display:inline-block;border-radius:8px"></div><p class="small">Or enter this key: <span class="code">${esc(s.secret.replace(/(.{4})/g, '$1 '))}</span></p><form class="row" id="mfc"><input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="123456" style="width:140px" required><button class="btn primary">Turn on</button></form>`, async (el, close) => {
        await loadQr(); new window.QRCode($('#qrc', el), { text: s.uri, width: 180, height: 180 });
        $('#mfc', el).onsubmit = async e => { e.preventDefault(); await run(() => api('POST', '/account/mfa/enable', { code: e.target.code.value }), 'Two-step sign-in is on'); S.user.mfa_enabled = true; close(); route(); };
      });
    });
    $('#mfaoff') && ($('#mfaoff').onsubmit = async e => { e.preventDefault(); await run(() => api('POST', '/account/mfa/disable', { code: e.target.code.value }), 'Turned off'); S.user.mfa_enabled = false; route(); });
  }
  async function enablePush() {
    try {
      if (!('serviceWorker' in navigator) || !('PushManager' in window)) throw new Error('This browser does not support push. On iPhone, add to Home Screen first.');
      const perm = await Notification.requestPermission(); if (perm !== 'granted') throw new Error('Notifications are blocked for this site.');
      const reg = await navigator.serviceWorker.register(BASE + '/assets/sw.js', { scope: BASE + '/' }); await navigator.serviceWorker.ready;
      const { key } = await api('GET', '/push/key');
      const raw = atob(key.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((key.length + 3) % 4));
      const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: Uint8Array.from(raw, c => c.charCodeAt(0)) });
      await api('POST', '/push/subscribe', sub.toJSON());
      toast('Push alerts enabled on this device');
    } catch (e) { toast(e.message, true); }
  }
  let qrLoading = null;
  function loadQr() {
    if (window.QRCode) return Promise.resolve();
    return qrLoading ??= new Promise((res, rej) => { const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'; s.onload = res; s.onerror = () => rej(new Error('Could not load QR library')); document.head.appendChild(s); });
  }

  // ---------- admin ----------
  async function viewIntegrations(v) {
    const j = await api('GET', '/admin/integrations');
    v.innerHTML = `<div class="head"><h1>Integrations</h1><span class="sub">Mock mode simulates the partner system so the flow can be shown before credentials exist. Secrets are encrypted at rest.</span></div>
      <div class="card mb"><h3>Endpoints to give partners</h3><dl class="kv">${Object.entries(j.endpoints).map(([k, u]) => `<dt>${esc(k.replace('_', ' '))}</dt><dd><span class="code">${esc(u)}</span></dd>`).join('')}</dl></div>
      <div class="grid g2">${j.rows.map(i => `<div class="card"><h3>${esc(i.label)} <span class="sp"></span><span class="badge ${i.enabled ? 's-resolved' : 's-on_hold'}">${i.enabled ? (i.mode === 'live' ? 'Live' : 'Mock') : 'Off'}</span></h3>
        <form class="form" data-p="${esc(i.provider)}"><label class="check"><input type="checkbox" name="enabled" ${i.enabled ? 'checked' : ''}> Enabled</label><div class="field"><select name="mode">${options({ mock: 'Mock / demo', live: 'Live' }, i.mode)}</select></div>
        ${Object.entries(i.fields).map(([k, label]) => k === 'note' ? `<p class="full small muted">${esc(label)}</p>` : `<div class="field full"><label>${esc(label)}</label><input type="${i.secrets.includes(k) ? 'password' : 'text'}" name="${esc(k)}" value="${esc(i.config[k] || '')}" autocomplete="off"></div>`).join('')}
        <div class="full row"><button class="btn primary sm">Save</button>${i.provider === 'opera' ? '<button type="button" class="btn sm" data-sync>⟳ Sync now</button>' : ''}<span class="sp"></span><span class="small muted">${i.last_sync_at ? 'Last ' + esc(rel(i.last_sync_at)) + ': ' + esc(i.last_status || '') : ''}</span></div></form>
        ${i.log.length ? `<details class="mt"><summary class="small">Recent activity</summary>${i.log.map(l => `<div class="small mb"><span class="muted">${esc(fmt(l.created_at))}</span> ${esc(l.direction)} ${esc(l.action)} <b>${esc(l.status)}</b> ${esc(l.summary || '')}</div>`).join('')}</details>` : ''}</div>`).join('')}</div>`;
    v.addEventListener('submit', async e => {
      const f = e.target.closest('[data-p]'); if (!f) return; e.preventDefault();
      const cfg = {}; $$('input[type=text],input[type=password]', f).forEach(i => cfg[i.name] = i.value);
      await run(() => api('PUT', '/admin/integrations/' + f.dataset.p, { enabled: f.enabled.checked, mode: f.mode.value, config: cfg }), 'Saved'); route();
    });
    v.addEventListener('click', async e => { if (e.target.closest('[data-sync]')) { const r = await run(() => api('POST', '/admin/integrations/opera/sync', {})); toast(r.events ? `Mock sync: ${r.events.length} change(s)` : r.error || `Synced ${r.rooms} rooms`, !!r.error); route(); } });
  }

  async function viewApiAdmin(v) {
    const t = await api('GET', '/admin/tokens');
    const mcpUrl = S.app.base_url + '/mcp';
    v.innerHTML = `<div class="head"><h1>API and MCP</h1><a class="btn" href="${BASE}/docs" target="_blank">📘 API docs</a><a class="btn" href="${BASE}/api/openapi.json" target="_blank">OpenAPI 3.1</a></div>
      <div class="grid g2"><div class="card"><h3>Connect an AI assistant (MCP)</h3><p class="small">ExpTix is a Model Context Protocol server, so Claude, Copilot and other MCP clients can read the queue, triage guest messages, update rooms and log temperatures, with every action audited.</p>
        <div class="small muted">Claude Code</div><pre class="code">claude mcp add --transport http exptix ${esc(mcpUrl)} \\\n  --header "Authorization: Bearer YOUR_TOKEN"</pre>
        <div class="small muted">Claude Desktop / other clients (JSON)</div><pre class="code">${esc(JSON.stringify({ mcpServers: { exptix: { type: 'http', url: mcpUrl, headers: { Authorization: 'Bearer YOUR_TOKEN' } } } }, null, 2))}</pre>
        <p class="small">19 tools: operations summary, search, create and update tickets, comments, room status, guest profile, food safety, logbook, lost and found, incidents, events, maintenance outlook. Prompts: shift briefing, triage guest message.</p></div>
      <div class="card"><h3>Create a token</h3><form class="form" id="tk"><div class="field full"><label>Name</label><input type="text" name="name" required placeholder="e.g. Claude for front office, Zapier, BMS gateway"></div><div class="field"><label>Acts as</label><select name="user_id">${listOptions(S.users, S.user.id)}</select></div><div class="field"><label>Expires in (days, blank for never)</label><input type="number" name="expires_days" value="90"></div>
        <div class="full row">${['read', 'write', 'mcp', 'admin'].map(s => `<label class="check"><input type="checkbox" name="scope" value="${s}" ${s !== 'admin' ? 'checked' : ''}> ${s}</label>`).join('')}</div><div class="full"><button class="btn primary">Create token</button></div></form>
        <p class="small muted mt">Tokens act with the chosen user's role and department access. Only a hash is stored; the token is shown once.</p></div></div>
      <div class="card flush mt"><h3>Tokens</h3><div class="table-wrap"><table class="t"><thead><tr><th>Name</th><th>Prefix</th><th>User</th><th>Scopes</th><th>Last used</th><th>Expires</th><th></th></tr></thead><tbody>${t.rows.map(x => `<tr><td>${esc(x.name)}</td><td class="code">${esc(x.prefix)}…</td><td>${esc(x.user_name)}</td><td>${esc(x.scopes)}</td><td>${esc(x.last_used_at ? rel(x.last_used_at) + ' · ' + (x.last_used_ip || '') : 'never')}</td><td>${esc(x.expires_at ? fmtDate(x.expires_at) : 'never')}</td><td>${+x.revoked ? '<span class="muted">revoked</span>' : `<button class="btn sm danger" data-rv="${x.id}">Revoke</button>`}</td></tr>`).join('')}</tbody></table></div></div>
      <div class="card mt"><h3>Outbound webhooks</h3><p class="small">Send ticket, room, temperature and incident events to Teams, Slack, a SIEM, a data warehouse or any HTTPS endpoint. Each POST carries <span class="code">X-ExpTix-Signature: sha256=HMAC(secret, timestamp.body)</span>. <a href="#/e/webhooks">Manage webhooks</a>.</p></div>`;
    $('#tk').onsubmit = async e => { e.preventDefault(); const f = e.target; const scopes = $$('input[name=scope]:checked', f).map(i => i.value); const r = await run(() => api('POST', '/admin/tokens', { name: f.name.value, user_id: +f.user_id.value, expires_days: f.expires_days.value ? +f.expires_days.value : null, scopes }));
      modal('Token created', `<p>Copy it now. It will not be shown again.</p><p class="code" style="font-size:.95rem">${esc(r.token)}</p><button class="btn primary" data-copy="${esc(r.token)}">Copy</button>`, el => { $('[data-copy]', el).onclick = () => { navigator.clipboard?.writeText(r.token); toast('Copied'); }; }); route(); };
    v.addEventListener('click', async e => { const b = e.target.closest('[data-rv]'); if (!b || !confirm('Revoke this token? Anything using it stops working.')) return; await run(() => api('DELETE', '/admin/tokens/' + b.dataset.rv), 'Revoked'); route(); });
  }

  async function viewAudit(v) {
    const qs = new URLSearchParams(location.hash.split('?')[1] || '');
    const p = new URLSearchParams(); ['entity', 'action', 'username'].forEach(k => qs.get(k) && p.set(k, qs.get(k)));
    const j = await api('GET', '/admin/audit?' + p);
    v.innerHTML = `<div class="head"><h1>Audit log</h1><span class="sub">Append-only, SHA-256 hash-chained</span><button class="btn" id="vfy">Verify integrity</button></div>
      <div class="filters"><input type="text" id="aa" placeholder="Action (e.g. auth.)" value="${esc(qs.get('action') || '')}"><input type="text" id="ae" placeholder="Record type (e.g. tickets)" value="${esc(qs.get('entity') || '')}"><input type="text" id="au" placeholder="Username" value="${esc(qs.get('username') || '')}"><button class="btn" id="ago">Filter</button></div>
      <div class="card flush"><div class="table-wrap"><table class="t"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>IP</th><th>Detail</th></tr></thead><tbody>${j.rows.map(r => `<tr><td class="small">${esc(fmt(r.at, { second: '2-digit' }))}</td><td>${esc(r.username)}</td><td><span class="code">${esc(r.action)}</span></td><td>${esc(r.entity || '')} ${r.entity_id ? '#' + r.entity_id : ''}</td><td class="small">${esc(r.ip)}</td><td class="small" style="max-width:420px;overflow-wrap:anywhere">${esc((r.detail || '').slice(0, 300))}</td></tr>`).join('')}</tbody></table></div></div>`;
    $('#ago').onclick = () => { const n = new URLSearchParams(); [['aa', 'action'], ['ae', 'entity'], ['au', 'username']].forEach(([i, k]) => $('#' + i).value && n.set(k, $('#' + i).value)); location.hash = '#/admin/audit?' + n; };
    $('#vfy').onclick = async () => { const r = await api('GET', '/admin/audit/verify'); toast(r.intact ? `Chain intact across ${r.records} records ✓` : `Chain broken at ${r.broken_at.join(', ')}`, !r.intact); };
  }

  async function viewSecurity(v) {
    const s = await api('GET', '/admin/security');
    v.innerHTML = `<div class="head"><h1>Security and compliance</h1><span class="sub">Control mapping to NIST SP 800-53 Rev. 5 and SP 800-63B</span><button class="btn no-print" onclick="print()">🖨 Print</button></div>
      <div class="kpis"><div class="kpi"><div class="v">${s.users.total}</div><div class="l">Active accounts</div></div><div class="kpi ${+s.users.mfa < +s.users.total ? 'warn' : 'ok'}"><div class="v">${+s.users.mfa}</div><div class="l">Using MFA</div></div><div class="kpi ${+s.users.privileged_mfa < +s.users.privileged ? 'bad' : 'ok'}"><div class="v">${+s.users.privileged_mfa}/${+s.users.privileged}</div><div class="l">Privileged with MFA</div></div><div class="kpi"><div class="v">${+s.users.stale}</div><div class="l">Unused 90+ days</div></div><div class="kpi ${s.audit.intact ? 'ok' : 'bad'}"><div class="v">${s.audit.intact ? '✓' : '✕'}</div><div class="l">Audit chain (${s.audit.records})</div></div></div>
      <div class="card">${s.controls.map(c => `<div class="ctl"><div><b>${esc(c.id)}</b><div class="small muted">${esc(c.family)}</div></div><div><b>${esc(c.name)}</b><div class="small">${esc(c.detail)}</div></div><div class="st-${esc(c.status)}">${esc(c.status === 'implemented' ? 'Implemented' : c.status === 'partial' ? 'Partial / production task' : 'Needs attention')}</div></div>`).join('')}</div>`;
  }

  async function viewQr(v) {
    const j = await api('GET', '/admin/qr');
    v.innerHTML = `<div class="head"><h1>Guest QR codes</h1><span class="sub">Print and place in each room (desk card or bathroom mirror) and public areas. Guests scan to make a request; no app or sign-in.</span><button class="btn primary no-print" onclick="print()">🖨 Print</button></div>
      <div class="filters no-print"><select id="qf">${options(Object.fromEntries([...new Set(j.rooms.map(r => r.floor))].map(f => [f, 'Floor ' + f])), '2', 'All floors')}</select><label class="check"><input type="checkbox" id="ql" checked> Include public areas</label></div><div class="qr-grid" id="qg"></div>`;
    await loadQr();
    const draw = () => {
      const fl = $('#qf').value, g = $('#qg'); g.innerHTML = '';
      const items = [...j.rooms.filter(r => !fl || r.floor === fl).map(r => ({ label: 'Room ' + r.number, url: j.base + r.qr_token })), ...($('#ql').checked ? j.locations.map(l => ({ label: l.name, url: j.base + l.qr_token })) : [])];
      items.forEach(it => { const c = document.createElement('div'); c.className = 'qr-card'; c.innerHTML = `<div class="q"></div><b>${esc(it.label)}</b><div style="font-size:.72rem">Scan for towels, repairs, dining and more</div><a href="${esc(it.url)}" target="_blank" class="no-print" style="font-size:.7rem">Open</a>`; g.appendChild(c); new window.QRCode($('.q', c), { text: it.url, width: 140, height: 140, correctLevel: window.QRCode.CorrectLevel.M }); });
    };
    $('#qf').onchange = draw; $('#ql').onchange = draw; draw();
  }

  load();
})();
