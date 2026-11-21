/* Homeboard — service worker (app shell uniquement, aucune donnée en cache) */
var CACHE = 'homeboard-shell-v2';
var ASSETS = [
	'./index.php',
	'./manifest.php',
	'./icons/icon-192.png',
	'./icons/icon-512.png'
];

/* Une réponse redirigée (session Jeedom expirée → page de login) ou en erreur
   ne doit JAMAIS entrer dans le cache : sinon l'écran de connexion remplace
   l'application jusqu'à la prochaine purge du cache. */
function putSafe(req, res) {
	if (!res || !res.ok || res.redirected || res.type === 'opaqueredirect') {
		return;
	}
	var copy = res.clone();
	caches.open(CACHE).then(function (c) { c.put(req, copy); });
}

self.addEventListener('install', function (e) {
	e.waitUntil(
		caches.open(CACHE).then(function (c) {
			return c.addAll(ASSETS).catch(function () {});
		}).then(function () { return self.skipWaiting(); })
	);
});

self.addEventListener('activate', function (e) {
	e.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.map(function (k) {
				return k === CACHE ? null : caches.delete(k);
			}));
		}).then(function () { return self.clients.claim(); })
	);
});

self.addEventListener('fetch', function (e) {
	if (e.request.method !== 'GET') { return; }
	var url = new URL(e.request.url);
	if (url.origin !== self.location.origin) { return; }

	/* Données dynamiques : jamais de cache */
	if (url.pathname.indexOf('/core/ajax/') !== -1) { return; }

	/* getResource (css/js du plugin, versionnés par ?v=filemtime) : cache d'abord */
	if (url.pathname.indexOf('/core/php/getResource.php') !== -1) {
		e.respondWith(
			caches.match(e.request).then(function (m) {
				if (m) { return m; }
				return fetch(e.request).then(function (r) {
					putSafe(e.request, r);
					return r;
				});
			})
		);
		return;
	}

	/* Shell PHP : réseau d'abord, cache en secours */
	if (/\.php$/.test(url.pathname)) {
		e.respondWith(
			fetch(e.request).then(function (r) {
				putSafe(e.request, r);
				return r;
			}).catch(function () {
				return caches.match(e.request).then(function (m) {
					return m || caches.match('./index.php');
				});
			})
		);
		return;
	}

	/* Statique : cache d'abord */
	e.respondWith(
		caches.match(e.request).then(function (m) {
			if (m) { return m; }
			return fetch(e.request).then(function (r) {
				putSafe(e.request, r);
				return r;
			});
		})
	);
});
