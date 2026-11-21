/* Homeboard — service worker.
   Portée volontairement étroite : uniquement les ressources du plugin. Aucune
   donnée d'équipement n'est mise en cache.
   CacheStorage étant partagé par toute l'origine, on ne touche qu'aux caches
   nommés « homeboard-* » : jamais ceux de Jeedom ni d'une autre application. */
var CACHE = 'homeboard-shell-v4';
var PREFIX = 'homeboard-';

/* Seules des ressources statiques sont précachées. Le shell PHP n'est jamais
   précaché : une réponse de page de connexion (session expirée) ou une erreur
   PHP se retrouverait sinon servie hors ligne à la place du tableau de bord. */
var ASSETS = [
	'./icons/icon-192.png',
	'./icons/icon-512.png'
];

/* Une réponse redirigée (session Jeedom expirée → page de login), en erreur, ou
   d'un autre type que celui attendu ne doit JAMAIS entrer dans le cache.
   La promesse est retournée : l'appelant la passe à waitUntil(), sinon le
   service worker peut être arrêté avant que l'écriture soit terminée. */
function putSafe(req, res, wantHtml) {
	if (!res || !res.ok || res.redirected || res.type === 'opaqueredirect') {
		return Promise.resolve(false);
	}
	if (wantHtml) {
		var ct = (res.headers.get('content-type') || '').toLowerCase();
		if (ct.indexOf('text/html') === -1) {
			return Promise.resolve(false);
		}
	}
	var copy = res.clone();
	return caches.open(CACHE)
		.then(function (c) { return c.put(req, copy); })
		.then(function () { return true; })
		.catch(function () { return false; });
}

function matchOwn(req) {
	return caches.open(CACHE).then(function (c) { return c.match(req); });
}

self.addEventListener('install', function (e) {
	e.waitUntil(
		caches.open(CACHE).then(function (c) {
			return Promise.all(ASSETS.map(function (u) {
				return c.add(u).catch(function () { return null; });
			}));
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
					e.waitUntil(putSafe(e.request, r));
					return r;
				});
			})
		);
		return;
	}

	/* Shell PHP du plugin : réseau d'abord, cache en secours. On ne garde que
	   la page réellement demandée et seulement si c'est du HTML non redirigé. */
	if (/\.php$/.test(url.pathname)) {
		e.respondWith(
			fetch(e.request).then(function (r) {
				e.waitUntil(putSafe(e.request, r, true));
				return r;
			}).catch(function () {
				return matchOwn(e.request).then(function (m) {
					return m || Response.error();
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
				e.waitUntil(putSafe(e.request, r));
				return r;
			});
		})
	);
});
