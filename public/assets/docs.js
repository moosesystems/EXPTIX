'use strict';
(async () => {
  const ROOT = document.getElementById('app');
  const BOOT = JSON.parse(ROOT.dataset.boot);
  const BASE = BOOT.base;
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const origin = location.origin + BASE;
  let spec = { paths: {} };
  try { spec = await (await fetch(BASE + '/api/openapi.json')).json(); } catch (e) { /* render without */ }
  const byTag = {};
  for (const [p, ops] of Object.entries(spec.paths || {})) for (const [m, op] of Object.entries(ops)) (byTag[(op.tags || ['Other'])[0]] ??= []).push({ p: p.startsWith('/../') ? p.slice(3) : '/api' + p, m: m.toUpperCase(), op });
  const tags = Object.keys(byTag);
  const pre = s => `<pre class="code">${esc(s)}</pre>`;
  document.body.className = 'page-app';
  ROOT.innerHTML = `<div class="content" style="max-width:1000px;margin:0 auto">
  <div class="head"><div class="brand" style="color:inherit"><div class="logo">X</div></div><h1>${esc(BOOT.app)} API, MCP and integrations</h1><a class="btn" href="${BASE}/">Open app</a><a class="btn" href="${BASE}/api/openapi.json">OpenAPI 3.1 JSON</a></div>
  <div class="card mb"><h3>Contents</h3><div class="row small"><a href="#auth">Authentication</a> · <a href="#mcp">MCP server</a> · <a href="#opera">Opera Cloud</a> · <a href="#canary">Canary</a> · <a href="#hooks">Inbound webhooks</a> · <a href="#out">Outbound webhooks</a> · ${tags.map(t => `<a href="#t-${esc(t.replace(/\W+/g, '-'))}">${esc(t)}</a>`).join(' · ')}</div></div>

  <div class="card mb" id="auth"><h3>Authentication</h3>
    <p>Create a token in the app under <b>Admin, API and MCP</b>. Tokens start with <span class="code">xtx_</span>, act with the chosen user's role and department access, carry scopes (<span class="code">read</span>, <span class="code">write</span>, <span class="code">mcp</span>, <span class="code">admin</span>) and are stored only as a SHA-256 hash. Every call is written to the hash-chained audit log.</p>
    ${pre(`curl -H "Authorization: Bearer xtx_..." ${origin}/api/tickets?status=open\n\ncurl -X POST -H "Authorization: Bearer xtx_..." -H "Content-Type: application/json" \\\n  -d '{"title":"Leaking tap","room":"514","priority":"high"}' ${origin}/api/tickets`)}
    <p class="small">Times are returned in UTC (<span class="code">YYYY-MM-DD HH:MM:SS</span>). Errors return JSON <span class="code">{"error": "..."}</span> with a 4xx or 5xx status. Lists accept <span class="code">limit</span> and <span class="code">offset</span>.</p></div>

  <div class="card mb" id="mcp"><h3>MCP server (AI assistants)</h3>
    <p>Endpoint <span class="code">${esc(origin)}/mcp</span>, Streamable HTTP transport, JSON-RPC 2.0, protocol versions 2025-06-18, 2025-03-26 and 2024-11-05. The token needs the <span class="code">mcp</span> scope; write tools also need <span class="code">write</span>.</p>
    ${pre(`claude mcp add --transport http exptix ${origin}/mcp \\\n  --header "Authorization: Bearer xtx_..."`)}
    <p class="small"><b>Tools:</b> operations_summary, search_tickets, get_ticket, create_ticket, update_ticket, add_comment, room_status, set_room_status, guest_profile, food_safety_status, log_temperature, logbook, logbook_add, lost_and_found, log_lost_item, report_incident, events_schedule, maintenance_outlook, list_reference.<br><b>Resources:</b> exptix://departments, exptix://categories, exptix://sla. <b>Prompts:</b> shift_briefing, triage_guest_message.</p></div>

  <div class="card mb" id="opera"><h3>Oracle Opera Cloud (OHIP)</h3>
    <p>ExpTix pulls room and reservation status from Opera Cloud through the Oracle Hospitality Integration Platform and pushes housekeeping status and minibar charges back. Business events (check-in, check-out, room move, room status) arrive at <span class="code">${esc(origin)}/hooks/opera</span>, signed with the events secret.</p>
    <p class="small">To go live: register an OHIP application for the property, then enter the gateway URL, client ID and secret, application key, enterprise ID, hotel code and events secret under <b>Admin, Integrations, Opera</b> and switch the mode to Live. API paths are configurable because OHIP versions differ by tenant.</p></div>

  <div class="card mb" id="canary"><h3>Canary Technologies</h3>
    <p>Canary guest messages and requests post to <span class="code">${esc(origin)}/hooks/canary</span> with an <span class="code">X-Signature</span> HMAC-SHA256 of the raw body. Messages thread by <span class="code">conversation_id</span> onto one ticket. Staff replies marked "Message guest" and status changes go back to the guest through the Canary API.</p></div>

  <div class="card mb" id="hooks"><h3>Inbound webhooks</h3>
    <p><b>Sensors</b> (temperature, leak, humidity, BMS): <span class="code">POST ${esc(origin)}/hooks/sensor</span> with header <span class="code">X-Sensor-Token</span>.</p>${pre('{"sensor_id":"WIC-01","kind":"temperature","value":5.8,"unit":"C"}')}
    <p><b>Zapier, Make, Power Automate</b>: <span class="code">POST ${esc(origin)}/hooks/inbound</span> with a Bearer token (write scope) and a ticket body.</p>
    <p><b>Email to ticket</b>: an IMAP mailbox is polled every two minutes; replies thread on the ticket number in the subject.</p></div>

  <div class="card mb" id="out"><h3>Outbound webhooks</h3>
    <p>Configure under <b>Webhooks</b>. Each event is POSTed as JSON with <span class="code">X-ExpTix-Event</span>, <span class="code">X-ExpTix-Timestamp</span> and <span class="code">X-ExpTix-Signature: sha256=HMAC(secret, timestamp + "." + body)</span>. Teams and Slack incoming webhooks are formatted natively.</p></div>

  ${tags.map(t => `<div class="card mb flush" id="t-${esc(t.replace(/\W+/g, '-'))}"><h3>${esc(t)}</h3><div class="table-wrap"><table class="t"><tbody>${byTag[t].map(x => `<tr><td style="width:80px"><span class="badge ${x.m === 'GET' ? 's-resolved' : x.m === 'DELETE' ? 'p-urgent' : 's-in_progress'}">${x.m}</span></td><td><span class="code">${esc(x.p)}</span><div class="small muted">${esc(x.op.summary || '')}</div></td></tr>`).join('')}</tbody></table></div></div>`).join('')}
  <p class="small muted" style="text-align:center">${esc(BOOT.app)} · ${esc(BOOT.property)} · ${esc(BOOT.operator)}</p></div>`;
})();
