/* Vholar Virtual Airlines — Service Worker (PWA)
 * =============================================================================
 * Objetivo: que la web instalada arranque y se comporte como una app. NO es un
 * sistema de caché offline completo, y eso es deliberado.
 *
 * Dos reglas que no se deben relajar:
 *
 *  1. NUNCA se cachea HTML. Las páginas van autenticadas con sesión, y un PC de
 *     cabina compartido entre pilotos podría servir el dashboard cacheado de un
 *     piloto al siguiente. Solo se cachean imágenes estáticas (allowlist de
 *     rutas + extensión) y la página /offline.html.
 *  2. Solo se interceptan GET del mismo origen. Quedan fuera por completo /api,
 *     /admin, /install y /update, y todo lo que venga de un CDN (Bootstrap,
 *     Leaflet, flag-icons, Google Fonts...), que sigue usando la caché HTTP.
 *
 * Consecuencias a tener en cuenta al mantener esto:
 *  - Cambiar una imagen que viva en /images/, /assets/, /disposable/ o /uploads/
 *    conservando la URL no se verá en clientes ya instalados hasta que se suba
 *    VERSION (abajo). Las imágenes con hash o con ?v= en la URL no sufren esto.
 *  - /assets/themes/vholar/css/*.css se sirve con ?v=<timestamp>, así que no se
 *    cachea: un cambio de CSS o JS se ve al recargar, sin tocar el SW.
 */

const VERSION = 'vholar-v2';
const SHELL_CACHE = VERSION + '-shell';
const IMAGE_CACHE = VERSION + '-img';

const OFFLINE_URL = '/offline.html';

// Tope del PRIMER intento de una navegacion. No es el veredicto: si expira se
// reintenta sin tope artificial (ver networkFirstNavigation). Estaba en 8s y con esa
// cifra cualquier pagina lenta caia a "sin conexion": es el fallo que estaban viendo
// pilotos con la web perfectamente accesible.
const NAV_TIMEOUT_MS = 20000;
// Tope de entradas en la caché de imágenes (evita crecimiento sin límite).
const IMAGE_CACHE_MAX = 80;

// Rutas que el SW no debe interceptar jamás.
const BYPASS = [
  /^\/api\//,
  /^\/admin(?:\/|$)/,
  /^\/install(?:\/|$)/,
  /^\/update(?:\/|$)/,
  /^\/cron(?:\/|$)/,
  /^\/storage\//,
];

// Rutas estáticas cuyas imágenes sí merece la pena cachear.
const IMAGE_PREFIXES = ['/images/', '/assets/', '/disposable/', '/uploads/'];
const IMAGE_EXT = /\.(?:png|jpe?g|webp|gif|svg|ico|avif)$/i;

// Assets que deben estar disponibles sin red para pintar la pantalla offline.
const PRECACHE = [
  OFFLINE_URL,
  '/images/logo.png',
  '/images/vholar_logoweb.png',
  '/images/pwa/icon-192.png',
  '/images/pwa/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(SHELL_CACHE);
      // add() uno a uno: si un asset falta, la instalación del SW no se cae.
      await Promise.all(
        PRECACHE.map(async (url) => {
          try {
            await cache.add(new Request(url, { cache: 'reload' }));
          } catch (err) {
            /* asset ausente: no es motivo para no instalar el SW */
          }
        })
      );
      await self.skipWaiting();
    })()
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys
          .filter((key) => key !== SHELL_CACHE && key !== IMAGE_CACHE)
          .map((key) => caches.delete(key))
      );
      await self.clients.claim();
    })()
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  let url;
  try {
    url = new URL(request.url);
  } catch (err) {
    return;
  }

  if (url.origin !== self.location.origin) return;
  if (BYPASS.some((re) => re.test(url.pathname))) return;

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstNavigation(request));
    return;
  }

  if (isCacheableImage(url.pathname)) {
    event.respondWith(imageCacheFirst(request));
  }
  // Cualquier otra cosa (CSS, JS, descargas, XHR) la resuelve el navegador.
});

async function networkFirstNavigation(request) {
  // 1) Intento rapido. Se devuelve la respuesta del servidor SIN guardarla (nunca
  //    cacheamos HTML autenticado) y sea cual sea su estado: un 4xx/5xx de Laravel es
  //    una respuesta del servidor, no una falta de conexion.
  try {
    const response = await fetchWithTimeout(request, NAV_TIMEOUT_MS);
    if (response) {
      return response;
    }
  } catch (err) {
    // Sin respuesta en el tope: puede ser lentitud, no caida. Se reintenta abajo.
  }

  // 2) Si el navegador dice que NO hay red, ahi si es offline.
  if (self.navigator && self.navigator.onLine === false) {
    return offlineFallback();
  }

  // 3) Reintento sin tope artificial: una conexion lenta no es "sin conexion".
  try {
    const response = await fetch(request);
    if (response) {
      return response;
    }
  } catch (err) {
    // Red caida de verdad.
  }

  return offlineFallback();
}

async function offlineFallback() {
  const cache = await caches.open(SHELL_CACHE);
  const offline = await cache.match(OFFLINE_URL);
  if (offline) return offline;
  return new Response('Sin conexión', {
    status: 503,
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
}

async function imageCacheFirst(request) {
  const cache = await caches.open(IMAGE_CACHE);
  const hit = await cache.match(request);
  if (hit) return hit;

  const response = await fetch(request);
  // 'basic' = mismo origen y respuesta legible; las opacas no se inspeccionan.
  if (response && response.ok && response.type === 'basic') {
    try {
      await cache.put(request, response.clone());
      await trimCache(cache, IMAGE_CACHE_MAX);
    } catch (err) {
      /* cuota agotada o respuesta no cacheable: se sirve igual */
    }
  }
  return response;
}

function isCacheableImage(pathname) {
  if (!IMAGE_EXT.test(pathname)) return false;
  return IMAGE_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

async function fetchWithTimeout(request, ms) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), ms);
  try {
    return await fetch(request, { signal: controller.signal });
  } finally {
    clearTimeout(timer);
  }
}

async function trimCache(cache, max) {
  const keys = await cache.keys();
  if (keys.length <= max) return;
  const excess = keys.slice(0, keys.length - max);
  await Promise.all(excess.map((key) => cache.delete(key)));
}
