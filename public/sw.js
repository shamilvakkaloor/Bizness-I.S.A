// Online-only: never cache authenticated pages, API responses or financial data.
self.addEventListener('install',()=>self.skipWaiting());
self.addEventListener('activate',event=>event.waitUntil(self.clients.claim()));
self.addEventListener('fetch',event=>{if(event.request.method==='GET'&&event.request.mode==='navigate')event.respondWith(fetch(event.request).catch(()=>new Response('<h1>You are offline</h1><p>Connect to the internet and reload. No transaction has been saved by this page.</p>',{headers:{'Content-Type':'text/html; charset=utf-8'}})));});
