/* ============================================
   SENTRIX — Service Worker (app instalável / PWA)
   - Guarda os arquivos visuais (CSS, JS, ícones) para abrir rápido.
   - Páginas e dados sempre vêm do servidor (informação atualizada e segura).
   - Sem internet/servidor, mostra a tela offline.html.
   Ao mudar CSS/JS, troque a VERSAO para os aparelhos baixarem de novo.
   ============================================ */
const VERSAO = 'sentrix-v2-2';
const ARQUIVOS = [
    'offline.html',
    'manifest.webmanifest',
    'assets/css/style.css',
    'assets/js/main.js',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
    'assets/icons/favicon-32.png',
];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSAO).then((c) => c.addAll(ARQUIVOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((nomes) => Promise.all(nomes.filter((n) => n !== VERSAO).map((n) => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== self.location.origin) return; // mapas, fontes etc. seguem direto

    // Páginas: sempre do servidor; se não houver conexão, tela offline
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
        return;
    }

    // Arquivos visuais: responde do cache e atualiza em segundo plano
    if (url.pathname.includes('/assets/')) {
        e.respondWith(
            caches.open(VERSAO).then(async (cache) => {
                const emCache = await cache.match(req);
                const daRede = fetch(req).then((resp) => {
                    if (resp.ok) cache.put(req, resp.clone());
                    return resp;
                }).catch(() => emCache);
                return emCache || daRede;
            })
        );
    }
    // Demais requisições (dados JSON, exportações): direto do servidor, sem cache
});
