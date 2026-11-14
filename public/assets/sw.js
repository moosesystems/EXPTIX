'use strict';
// ExpTix service worker: offline shell and push notifications.
// Pushes are payloadless (no guest data passes through the push service); the worker fetches the latest notification.
const CACHE = 'exptix-v1';
const SCOPE = self.registration.scope.replace(/\/$/, '');
const SHELL = ['/assets/app.css', '/assets/app.js', '/assets/icons/icon.svg', '/assets/icons/icon-192.png'].map(p => SCOPE + p);

self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).catch(() => {})); self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x)))).then(() => self.clients.claim())); });

self.addEventListener('fetch', e => {
  const u = new URL(e.request.url);
  if (e.request.method !== 'GET' || u.origin !== location.origin) return;
  if (u.pathname.startsWith(new URL(SCOPE).pathname + '/api/')) return; // never cache API data
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => new Response('<!doctype html><meta name="viewport" content="width=device-width"><body style="font-family:sans-serif;padding:40px;text-align:center;background:#0F1E24;color:#fff"><h2>You are offline</h2><p>ExpTix needs a connection. Check Wi-Fi and try again.</p></body>', { headers: { 'Content-Type': 'text/html' } })));
    return;
  }
  if (u.pathname.includes('/assets/')) e.respondWith(fetch(e.request).then(r => { const c = r.clone(); caches.open(CACHE).then(x => x.put(e.request, c)); return r; }).catch(() => caches.match(e.request, { ignoreSearch: true })));
});

self.addEventListener('push', e => {
  e.waitUntil((async () => {
    let n = { title: 'ExpTix', body: 'You have a new notification', link: '#/' };
    try {
      const r = await fetch(SCOPE + '/api/notifications?unread=1&limit=1', { credentials: 'include' });
      if (r.ok) { const j = await r.json(); if (j.rows && j.rows[0]) n = j.rows[0]; }
    } catch (x) { /* show generic */ }
    await self.registration.showNotification(n.title, { body: n.body || '', icon: SCOPE + '/assets/icons/icon-192.png', badge: SCOPE + '/assets/icons/icon-192.png', tag: 'exptix-' + (n.id || Date.now()), data: { link: n.link || '#/' }, requireInteraction: n.level === 'alert' });
  })());
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = SCOPE + '/' + (e.notification.data && e.notification.data.link || '#/');
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const c of list) if (c.url.startsWith(SCOPE)) { c.navigate(url); return c.focus(); }
    return self.clients.openWindow(url);
  }));
});
