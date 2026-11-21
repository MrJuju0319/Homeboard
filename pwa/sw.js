/* Homeboard — service worker.
   Portée volontairement étroite : uniquement le shell de l'application et les
   ressources du plugin. Aucune donnée d'équipement n'est mise en cache.
   CacheStorage étant partagé par toute l'origine, on ne touche qu'aux caches
   nommés « homeboard-* » : jamais ceux de Jeedom ni d'une autre application. */
var CACHE = 'homeboard-shell-v3';
var PREFIX = 'homeboard-';
var ASSETS = [
	'./index.php',
	'./manifest.php',
	'./icons/icon-192.png',
	'./icons/icon-512.png'
];

/* Une réponse redirigée (session Jeedom expirée → page de login) ou en erreur
   ne doit JAMAIS entrer dans le cache. */
function putSafe(req, res) {
	if (!res || !res.ok || res.redirected || res.type === 'opaqueredirect') {
		return;
	}
	var copy = res.clone();
	caches.open(CACHE).then(function (c) { c.put(req, copy); });
}

function matchOwn(req) {
	return caches.open(CACHE).then(function (c) { return c.match(req); });
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
				if (k.indexOf(PREFIX) === 0 && k !== CACHE) {
					return caches.delete(k);
				}
				return null;
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

	var inPlugin = url.pathname.indexOf('/plugins/homeboard/') === 0;
	var isPluginAsset = url.pathname.indexOf('/core/php/getResource.php') === 0 &&
		url.search.indexOf('file=plugins/homeboard/') !== -1;

	/* Hors périmètre du plugin : on laisse passer sans intercepter. */
	if (!inPlugin && !isPluginAsset) { return; }

	/* CSS/JS du plugin, versionnés par ?v=filemtime : cache d'abord */
	if (isPluginAsset) {
		e.respondWith(
			matchOwn(e.request).then(function (m) {
				if (m) { return m; }
				return fetch(e.request).then(function (r) {
					putSafe(e.request, r);
					return r;
				});
			})
		);
		return;
	}

	/* Shell PHP du plugin : réseau d'abord, cache en secours */
	if (/\.php$/.test(url.pathname)) {
		e.respondWith(
			fetch(e.request).then(function (r) {
				putSafe(e.request, r);
				return r;
			}).catch(function () {
				return matchOwn(e.request).then(function (m) {
					return m || matchOwn(new Request('./index.php'));
				});
			})
		);
		return;
	}

	/* Statique du plugin : cache d'abord */
	e.respondWith(
		matchOwn(e.request).then(function (m) {
			if (m) { return m; }
			return fetch(e.request).then(function (r) {
				putSafe(e.request, r);
				return r;
			});
		})
	);
});
