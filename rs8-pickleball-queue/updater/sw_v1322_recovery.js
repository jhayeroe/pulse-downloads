const CACHE='rs8q-static-v1.3.22';
const STATIC_ASSETS=[
  'assets/app.css',
  'assets/app.js',
  'assets/icons/app-192.png',
  'assets/icons/app-512.png',
  'assets/icons/apple-touch-180.png',
  'assets/icons/favicon-64.png',
  'assets/icons/brand-128.png',
  'assets/icons/queue-128.png',
  'manifest.json'
];
self.addEventListener('install',event=>{
  self.skipWaiting();
  event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(STATIC_ASSETS)).catch(()=>{}));
});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim()));
});
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET') return;
  const url=new URL(event.request.url);
  const isDynamic=event.request.mode==='navigate'||url.pathname.endsWith('.php')||url.pathname.endsWith('/');
  if(isDynamic) return;
  if(url.origin!==self.location.origin) return;
  event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request).then(response=>{
    if(response.ok){const copy=response.clone();caches.open(CACHE).then(cache=>cache.put(event.request,copy));}
    return response;
  }).catch(()=>cached)));
});
