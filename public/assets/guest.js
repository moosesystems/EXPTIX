'use strict';
(() => {
  const ROOT = document.getElementById('app');
  const BOOT = JSON.parse(ROOT.dataset.boot);
  const BASE = BOOT.base;
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const nl2br = v => esc(v).replace(/\n/g, '<br>');
  const fmt = s => s ? new Intl.DateTimeFormat(BOOT.locale || 'en-US', { timeZone: BOOT.timezone || 'UTC', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(String(s).replace(' ', 'T') + 'Z')) : '';
  async function api(method, path, body) {
    const r = await fetch(BASE + '/api' + path, { method, headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) }, body: body ? JSON.stringify(body) : undefined });
    let j = {}; try { j = await r.json(); } catch (e) { /* ignore */ }
    if (!r.ok) throw new Error(j.error || 'Something went wrong. Please call the front desk.');
    return j;
  }
  const shell = (title, sub, body) => {
    ROOT.innerHTML = `<div class="gwrap"><div class="ghead"><div class="small" style="color:var(--c-accent);letter-spacing:.08em;text-transform:uppercase">${esc(BOOT.property)}</div><h1>${esc(title)}</h1><p>${esc(sub)}</p></div>${body}<div class="foot">Need something urgently? Dial 0 from your room phone.<br>Powered by ${esc(BOOT.app)}</div></div>`;
  };

  async function portal() {
    let info;
    try { info = await api('GET', '/public/portal/' + BOOT.token); }
    catch (e) { shell('Sorry', e.message, ''); return; }
    const where = info.room ? `Room ${info.room}` : info.location;
    let cat = null;
    const tracked = (() => { try { return JSON.parse(localStorage.getItem('exptix-guest') || '[]'); } catch (e) { return []; } })();
    shell('How can we help?', `${where}. Tap a request and we will take care of it.`, `
      ${tracked.length ? `<div class="card mb"><b>Your requests</b>${tracked.slice(-5).reverse().map(t => `<div class="small mt"><a href="${BASE}/g/t/${esc(t.track)}">${esc(t.number)} ${esc(t.label || '')}</a></div>`).join('')}</div>` : ''}
      <div class="gcats" id="cats">${info.categories.map(c => `<button type="button" class="gcat" data-id="${c.id}"><span class="e">${esc(c.icon || '•')}</span><b>${esc(c.label)}</b><span class="small muted">${esc(c.department)}</span></button>`).join('')}</div>
      <form class="card mt" id="gf">
        <div class="field"><label for="d">Anything else we should know?</label><textarea id="d" name="details" maxlength="2000" placeholder="e.g. 2 extra pillows, or describe the problem"></textarea></div>
        <div class="field mt"><label for="w">When? (optional)</label><input id="w" type="datetime-local" name="when"></div>
        <div class="field mt"><label for="n">Your name (optional)</label><input id="n" type="text" name="name" maxlength="120" autocomplete="name"></div>
        <div class="field mt"><label for="c">Phone or email for updates (optional)</label><input id="c" type="text" name="contact" maxlength="190" autocomplete="email"></div>
        <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <p class="err-msg" id="err"></p>
        <button class="btn gold" style="width:100%;justify-content:center;margin-top:10px">Send request</button>
      </form>
      ${info.outlets.length ? `<div class="card mt"><b>Dining and services</b>${info.outlets.map(o => `<div class="small mt"><b>${esc(o.name)}</b>${o.hours ? ' · ' + esc(o.hours) : ''}${o.phone_ext ? ' · ext. ' + esc(o.phone_ext) : ''}</div>`).join('')}${info.eighty_six.length ? `<div class="small muted mt">Not available today: ${esc(info.eighty_six.join(', '))}</div>` : ''}</div>` : ''}`);
    const cats = document.getElementById('cats');
    cats.onclick = e => { const b = e.target.closest('.gcat'); if (!b) return; cat = cat === b.dataset.id ? null : b.dataset.id; [...cats.children].forEach(x => x.classList.toggle('on', x.dataset.id === cat)); if (cat) document.getElementById('d').focus({ preventScroll: true }); };
    document.getElementById('gf').onsubmit = async e => {
      e.preventDefault();
      const f = e.target, err = document.getElementById('err'); err.textContent = '';
      const btn = f.querySelector('button'); btn.disabled = true;
      try {
        const r = await api('POST', '/public/portal/' + BOOT.token, { category_id: cat, details: f.details.value, when: f.when.value, name: f.name.value, contact: f.contact.value, website: f.website.value });
        const label = cat ? (info.categories.find(c => String(c.id) === cat) || {}).label : '';
        try { tracked.push({ number: r.number, track: r.track, label }); localStorage.setItem('exptix-guest', JSON.stringify(tracked.slice(-10))); } catch (x) { /* private mode */ }
        location.href = `${BASE}/g/t/${r.track}`;
      } catch (x) { err.textContent = x.message; btn.disabled = false; }
    };
  }

  async function track() {
    let t;
    try { t = await api('GET', '/public/ticket/' + BOOT.token); }
    catch (e) { shell('Request not found', e.message, ''); return; }
    const order = ['new', 'acknowledged', 'in_progress', 'resolved'];
    const step = t.status === 'closed' ? 3 : t.status === 'on_hold' ? 1 : Math.max(0, order.indexOf(t.status));
    const done = ['resolved', 'closed'].includes(t.status);
    shell(t.title, `Request ${t.number}${t.room ? ' · Room ' + t.room : ''} · sent ${fmt(t.created_at)}`, `
      <div class="card"><div class="gstatus">${esc(t.status_text)}</div>${t.status !== 'cancelled' ? `<div class="steps">${order.map((_, i) => `<i class="${i <= step ? 'on' : ''}"></i>`).join('')}</div>` : ''}
        <div class="small muted">${done && t.resolved_at ? 'Completed ' + esc(fmt(t.resolved_at)) : 'This page updates automatically.'}</div></div>
      ${done && !t.satisfaction ? `<div class="card mt" id="rate"><b>How did we do?</b><div class="stars mt">${[1, 2, 3, 4, 5].map(n => `<button type="button" data-s="${n}" aria-label="${n} star${n > 1 ? 's' : ''}">★</button>`).join('')}</div><textarea id="rc" class="mt" placeholder="Tell us more (optional)" maxlength="1000"></textarea><button class="btn primary mt" id="rs" disabled>Send rating</button></div>` : ''}
      ${t.satisfaction ? `<div class="card mt">Thank you for rating us ${'★'.repeat(t.satisfaction)}</div>` : ''}
      <div class="card mt"><b>Messages</b><div class="timeline mt">${t.messages.map(m => `<div class="ev ${m.sender === 'guest' ? 'guest' : ''}"><div class="bubble"><div class="who-line">${m.sender === 'guest' ? 'You' : esc(t.property)} · ${esc(fmt(m.created_at))}</div>${nl2br(m.body)}</div></div>`).join('') || '<p class="small muted">No messages yet.</p>'}</div>
        ${t.status !== 'cancelled' ? '<form id="mf" class="mt"><textarea name="message" required maxlength="1000" placeholder="Send a message to the team"></textarea><button class="btn mt">Send</button></form>' : ''}</div>
      <p class="small mt" style="text-align:center"><a href="javascript:history.back()">Make another request</a></p>`);
    let score = 0;
    const rate = document.getElementById('rate');
    if (rate) {
      rate.querySelector('.stars').onclick = e => { const b = e.target.closest('[data-s]'); if (!b) return; score = +b.dataset.s; rate.querySelectorAll('[data-s]').forEach(x => x.classList.toggle('on', +x.dataset.s <= score)); document.getElementById('rs').disabled = false; };
      document.getElementById('rs').onclick = async () => { await api('POST', `/public/ticket/${BOOT.token}/rate`, { score, comment: document.getElementById('rc').value }); track(); };
    }
    const mf = document.getElementById('mf');
    if (mf) mf.onsubmit = async e => { e.preventDefault(); try { await api('POST', `/public/ticket/${BOOT.token}/message`, { message: mf.message.value }); track(); } catch (x) { alert(x.message); } };
    clearTimeout(window.__gt); if (!done) window.__gt = setTimeout(() => { if (!document.activeElement || document.activeElement.tagName !== 'TEXTAREA') track(); else window.__gt = setTimeout(track, 30000); }, 30000);
  }

  (BOOT.mode === 'track' ? track : portal)();
})();
