const CACHE = 'upskill-v1';

const STATIC = ['/favicon.svg'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  const { request } = e;

  // Navigation: network-first, offline fallback
  if (request.mode === 'navigate') {
    e.respondWith(
      fetch(request).catch(() =>
        new Response(
          `<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
          <title>Offline – Upskill</title>
          <style>*{box-sizing:border-box;margin:0;padding:0}body{background:#0d0d14;color:#f0f0ff;font-family:'Sora',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:1rem;padding:2rem;text-align:center}
          .emoji{font-size:3.5rem}.h1{font-size:1.4rem;font-weight:700}.p{color:rgba(240,240,255,.55);font-size:.9rem;line-height:1.6}
          .btn{margin-top:1rem;padding:.75rem 1.5rem;background:#f59e0b;color:#0d0d14;border:none;border-radius:10px;font-family:'Sora',sans-serif;font-weight:700;font-size:.9rem;cursor:pointer}
          </style></head>
          <body><div class="emoji">📡</div><div class="h1">You're offline</div>
          <p class="p">Reconnect to access your dashboard.<br>Your data will sync when you're back online.</p>
          <button class="btn" onclick="location.reload()">Try again</button></body></html>`,
          { headers: { 'Content-Type': 'text/html' } }
        )
      )
    );
    return;
  }

  // Static assets: cache-first
  if (['style', 'script', 'image', 'font'].includes(request.destination)) {
    e.respondWith(
      caches.match(request).then(cached => cached || fetch(request).then(resp => {
        const clone = resp.clone();
        caches.open(CACHE).then(c => c.put(request, clone));
        return resp;
      }))
    );
    return;
  }

  // Everything else: network-only
  e.respondWith(fetch(request));
});
