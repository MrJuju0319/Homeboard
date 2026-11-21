/* =========================================================================
   Homeboard — application (vanilla JS, aucun build, aucune dépendance)
   ========================================================================= */
(function () {
	'use strict';

	var root = null;
	var API = null;
	var CFG_URL = '';
	var EXIT_URL = '';

	/* ------------------------------------------------------------------ */
	/*  Icônes (viewBox 24x24, trait unique => alignement constant)        */
	/* ------------------------------------------------------------------ */

	var ICONS = {
		home: '<path d="M3 10.2 12 3l9 7.2V21a1 1 0 0 1-1 1h-5.5v-6.5h-5V22H4a1 1 0 0 1-1-1z"/>',
		sofa: '<path d="M5 11V7.5A1.5 1.5 0 0 1 6.5 6h11A1.5 1.5 0 0 1 19 7.5V11"/><path d="M3 11.5a2 2 0 0 1 2 2V15h14v-1.5a2 2 0 1 1 4 0V18a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-4.5a2 2 0 0 1 2-2z"/>',
		bed: '<path d="M3 20V8"/><path d="M3 12h18v8"/><path d="M21 20v-4"/><path d="M7 12V9.5h5V12"/>',
		kitchen: '<path d="M6 3v8a2 2 0 0 0 4 0V3"/><path d="M8 11v10"/><path d="M17 3c-1.4 2-2 4-2 6s.6 3.5 2 3.5 2-1.5 2-3.5-.6-4-2-6z"/><path d="M17 12.5V21"/>',
		bath: '<path d="M3 12h18v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4z"/><path d="M6 12V6.5A2.5 2.5 0 0 1 11 6.5"/><path d="M7 19.5 6 22"/><path d="M17 19.5 18 22"/>',
		garage: '<path d="M3 21V9.5L12 3l9 6.5V21"/><path d="M7 21v-7h10v7"/><path d="M7 17.5h10"/>',
		garden: '<path d="M12 21v-7"/><path d="M12 14c-4.4 0-8-3.6-8-8 4.4 0 8 3.6 8 8z"/><path d="M12 14c0-4.4 3.6-8 8-8 0 4.4-3.6 8-8 8z"/>',
		light: '<path d="M9 17h6"/><path d="M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9V17h7v-3.1A6 6 0 0 0 12 3z"/>',
		lamp: '<path d="M8 3h8l3 8H5z"/><path d="M12 11v7"/><path d="M9 21h6"/>',
		plug: '<path d="M9 2v5"/><path d="M15 2v5"/><path d="M6 7h12v4a6 6 0 0 1-6 6 6 6 0 0 1-6-6z"/><path d="M12 17v5"/>',
		thermometer: '<path d="M14 14.8V5a2 2 0 1 0-4 0v9.8a4 4 0 1 0 4 0z"/>',
		shutter: '<path d="M3 4h18"/><path d="M4 4v16"/><path d="M20 4v16"/><path d="M4 9h16"/><path d="M4 14h16"/>',
		camera: '<path d="M3 8.5A1.5 1.5 0 0 1 4.5 7h3L9.5 4h5L16.5 7h3A1.5 1.5 0 0 1 21 8.5v10A1.5 1.5 0 0 1 19.5 20h-15A1.5 1.5 0 0 1 3 18.5z"/><circle cx="12" cy="13" r="3.6"/>',
		sensor: '<path d="M20.5 16.5a9 9 0 1 0-17 0"/><path d="M12 14l3.8-3.8"/>',
		power: '<path d="M18.4 7.2a8.5 8.5 0 1 1-12.8 0"/><path d="M12 3v8"/>',
		water: '<path d="M12 3s6 6.4 6 10.5a6 6 0 0 1-12 0C6 9.4 12 3 12 3z"/>',
		tv: '<rect x="2" y="7" width="20" height="12" rx="2"/><path d="M8 3l4 4 4-4"/>',
		office: '<path d="M3 8h18"/><path d="M6 8v13"/><path d="M18 8v13"/><path d="M3 13h18"/>',
		child: '<circle cx="12" cy="11" r="8"/><path d="M9 10h.01"/><path d="M15 10h.01"/><path d="M9.5 14.5s1 1.3 2.5 1.3 2.5-1.3 2.5-1.3"/>',
		stairs: '<path d="M3 20h4v-4h4v-4h4V8h4V4"/>',
		pool: '<path d="M2 15c2 0 2 2 4 2s2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M2 20c2 0 2 2 4 2s2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M8 11V6a2 2 0 0 1 4 0"/><path d="M16 11V6a2 2 0 0 1 4 0"/>',
		scene: '<path d="M12 3l1.9 4.7L18.6 9.6l-4.7 1.9L12 16.2l-1.9-4.7L5.4 9.6l4.7-1.9z"/><path d="M18 16l.9 2.2 2.1.8-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.8z"/>',
		fan: '<circle cx="12" cy="12" r="2"/><path d="M12 10c0-3 1-7 4-7 2 0 3 2 3 4 0 2-2 3-5 3z"/><path d="M14 12c3 0 7 1 7 4 0 2-2 3-4 3-2 0-3-2-3-5z"/><path d="M12 14c0 3-1 7-4 7-2 0-3-2-3-4 0-2 2-3 5-3z"/><path d="M10 12c-3 0-7-1-7-4 0-2 2-3 4-3 2 0 3 2 3 5z"/>',
		car: '<path d="M5 17h14"/><path d="M6.5 17V13l1.8-4.2A2 2 0 0 1 10.1 7.5h3.8a2 2 0 0 1 1.8 1.3L17.5 13v4"/><path d="M4 13h16"/><circle cx="8" cy="17.5" r="1.5"/><circle cx="16" cy="17.5" r="1.5"/>',
		chart: '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
		play: '<path d="M7 4.5l12 7.5-12 7.5z"/>',
		gear: '<path d="M4 7h9M17 7h3M4 17h4M12 17h8"/><circle cx="15" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
		shrink: '<path d="M9 3v6H3"/><path d="M3 9l6-6"/><path d="M15 21v-6h6"/><path d="M21 15l-6 6"/>',
		close: '<path d="M6 6l12 12"/><path d="M18 6L6 18"/>',
		install: '<path d="M12 3v11"/><path d="M8 10l4 4 4-4"/><path d="M4 17v3h16v-3"/>',
		sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>'
	};

	var MODE_LABELS = {
		heat: 'Chauffage', cool: 'Clim', arret: 'Arrêt', off: 'Arrêt', auto: 'Auto',
		eco: 'Éco', comfort: 'Confort', confort: 'Confort', absence: 'Absence',
		frost: 'Hors gel', boost: 'Boost', dry: 'Déshumidifier', fan: 'Ventilation'
	};

	/* ------------------------------------------------------------------ */
	/*  État local                                                         */
	/* ------------------------------------------------------------------ */

	var S = {
		rooms: [],
		favorites: [],
		charts: [],
		home: { homeName: 'Maison', showClock: 1, refresh: 3 },
		active: 'all',
		values: {},
		tiles: {},
		pending: {},
		dragging: false,
		polling: false,
		timer: null,
		installEvent: null,
		sheet: null,
		sparks: {},
		favById: {}
	};

	/* ------------------------------------------------------------------ */
	/*  Utilitaires                                                        */
	/* ------------------------------------------------------------------ */

	function esc(s) {
		return String(s === null || s === undefined ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	function svg(name, cls) {
		var d = ICONS[name] || ICONS.sensor;
		return '<svg class="hb-i ' + (cls || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
			'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
	}

	function api(params, method) {
		var qs = Object.keys(params).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
		}).join('&');
		var opts = { credentials: 'same-origin', cache: 'no-store' };
		var url = API;
		if ((method || 'GET').toUpperCase() === 'POST') {
			/* Les actions d'écriture passent en POST : Jeedom (ajax::init)
			   refuse une mutation demandée en GET. */
			opts.method = 'POST';
			opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' };
			opts.body = qs;
		} else {
			url = API + '?' + qs;
		}
		return fetch(url, opts)
			.then(function (r) {
				if (r.status === 401 || r.status === 403) {
					var e1 = new Error('Session expirée ou droits insuffisants — recharge la page');
					e1.session = true;
					throw e1;
				}
				if (!r.ok) { throw new Error('Erreur HTTP ' + r.status); }
				var ct = r.headers.get('Content-Type') || '';
				if (ct.indexOf('json') === -1) {
					/* Jeedom renvoie la page de login en HTML quand la session
					   a expiré : le dire clairement plutôt qu'une erreur JSON. */
					var e2 = new Error('Réponse inattendue (session expirée ?) — recharge la page');
					e2.session = true;
					throw e2;
				}
				return r.json();
			})
			.then(function (j) {
				if (!j || j.state !== 'ok') { throw new Error((j && j.result) ? j.result : 'Erreur API'); }
				return j.result;
			});
	}

	function toast(msg) {
		var t = document.createElement('div');
		t.className = 'hb-toast';
		t.textContent = msg;
		document.body.appendChild(t);
		setTimeout(function () { t.classList.add('is-out'); }, 2200);
		setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 2700);
	}

	function val(id) { return id && S.values[id] ? S.values[id] : null; }

	function num(v) {
		if (v === null || v === undefined || v === '') { return null; }
		var n = parseFloat(String(v).replace(',', '.'));
		return isNaN(n) ? null : n;
	}

	function fmt(n, dec) {
		if (n === null || n === undefined) { return '—'; }
		var d = (dec === undefined) ? ((Math.abs(n - Math.round(n)) < 0.05) ? 0 : 1) : dec;
		return Number(n).toFixed(d).replace('.', ',');
	}

	function tileState(t) {
		var c = t.caps || {};
		var id = c.state || (t.state && t.state.id);
		var v = val(id);
		return v || t.state || null;
	}

	function isOn(t) {
		if (t.kind === 'light' || t.kind === 'switch' || t.kind === 'shutter') {
			var st = tileState(t);
			var n = num(st ? st.value : null);
			return n !== null && n > 0;
		}
		return false;
	}

	function modeLabel(name) {
		var n = String(name || '').trim();
		if (!n) { return ''; }
		/* On essaie le nom complet avant de couper : « Mode Hors Gel » ne doit
		   pas devenir « Gel ». */
		var full = MODE_LABELS[n.toLowerCase()];
		if (full) { return full; }
		var w = n.split(/\s+/);
		var k = w[w.length - 1].toLowerCase();
		return MODE_LABELS[k] || w[w.length - 1];
	}

	/* Comparaison tolérante des libellés de mode (accents, casse, préfixes
	   « Mode » / « État ») : la commande peut s'appeler « Mode Hors Gel » alors
	   que l'état affiché est « Hors gel ». */
	function normTxt(s) {
		return String(s === null || s === undefined ? '' : s)
			.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.replace(/\b(mode|state|etat)\b/g, '')
			.replace(/[^a-z0-9]+/g, ' ')
			.trim();
	}

	function hhmm(ts) {
		return new Date(ts).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
	}

	/* Retour haptique léger (Android ; ignoré silencieusement ailleurs) */
	function haptic(ms) {
		if (navigator.vibrate) {
			try { navigator.vibrate(ms || 10); } catch (e) { /* noop */ }
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Interface : structure                                              */
	/* ------------------------------------------------------------------ */

	var navEl, navList, headEl, sectionsEl;

	function buildShell() {
		root.innerHTML = '<div class="hb-app">' +
			'<aside class="hb-nav" id="hb-nav" role="tablist"></aside>' +
			'<main class="hb-main">' +
			'<div class="hb-head" id="hb-head"></div>' +
			'<div id="hb-sections"></div>' +
			'<div class="hb-foot"><span class="hb-dot" id="hb-dot"></span><span id="hb-foot-txt">Synchronisé</span></div>' +
			'</main></div>';
		navEl = document.getElementById('hb-nav');
		headEl = document.getElementById('hb-head');
		sectionsEl = document.getElementById('hb-sections');
	}

	function paintNav() {
		var html = '<button class="hb-nav__item hb-nav__item--all" data-room="all" role="tab">' +
			svg('home') + '<span>Tout</span></button>' +
			'<div class="hb-nav__list" id="hb-nav-list">';
		S.rooms.forEach(function (r) {
			html += '<button class="hb-nav__item" data-room="' + r.id + '" role="tab">' +
				svg(r.icon || 'home') + '<span>' + esc(r.name) + '</span></button>';
		});
		html += '</div><button class="hb-nav__item hb-nav__item--charts" data-room="charts" role="tab">' +
			svg('chart') + '<span>Graphiques</span></button>';
		navEl.innerHTML = html;
		navList = document.getElementById('hb-nav-list');
		markActive();
	}

	function markActive() {
		var items = navEl.querySelectorAll('.hb-nav__item');
		for (var i = 0; i < items.length; i++) {
			var isActive = String(items[i].getAttribute('data-room')) === String(S.active);
			items[i].classList.toggle('is-active', isActive);
			items[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
		}
	}

	function summary() {
		var lightsOn = 0, lights = 0, others = 0, temps = [];
		S.rooms.forEach(function (r) {
			r.tiles.forEach(function (t) {
				if (t.kind === 'light') {
					lights++;
					if (isOn(t)) { lightsOn++; }
				} else if (t.kind === 'switch' && isOn(t)) {
					others++;
				} else if (t.kind === 'sensor' && /°\s*c/i.test(String(t.state.unit || ''))) {
					var n = num(tileState(t) ? tileState(t).value : null);
					if (n !== null) { temps.push(n); }
				} else if (t.kind === 'thermostat' && t.extra && t.extra.temp) {
					var nt = num(t.extra.temp.value);
					if (nt !== null) { temps.push(nt); }
				}
			});
		});
		var parts = [];
		if (temps.length) {
			var sum = 0;
			temps.forEach(function (v) { sum += v; });
			parts.push(fmt(sum / temps.length, 1) + ' °C de moyenne');
		}
		if (lights) {
			parts.push(lightsOn === 0 ? 'aucune lumière allumée' :
				lightsOn + (lightsOn > 1 ? ' lumières allumées' : ' lumière allumée'));
		}
		if (others) {
			parts.push(others + (others > 1 ? ' appareils actifs' : ' appareil actif'));
		}
		return parts.join(' · ');
	}

	/* ------------------------------------------------------------------ */
	/*  Mode mur                                                           */
	/* ------------------------------------------------------------------ */

	var wall = { timer: null, clockTimer: null, driftTimer: null, asleep: false, lock: null, el: null, bound: false, delay: 120000 };

	/* Widgets du voile : conso, température, météo… n'importe quelle commande
	   d'information choisie dans la configuration. Les valeurs viennent du
	   cache de polling (elles sont ajoutées aux identifiants interrogés). */
	function wallWidgetsInner() {
		var list = (S.home && S.home.wallWidgets) || [];
		var html = '';
		list.forEach(function (w) {
			var v = val(w.cmd);
			var unit = (v && v.unit) ? v.unit : (w.unit || '');
			html += '<div class="hb-sleep__w"><span class="hb-sleep__wv">' +
				esc(v ? v.display : '—') +
				(unit ? '<span class="hb-sleep__wu">' + esc(unit) + '</span>' : '') + '</span>' +
				'<span class="hb-sleep__wl">' + esc(w.label) + '</span></div>';
		});
		return html;
	}

	function wallPaint() {
		if (!wall.el) { return; }
		var d = new Date();
		var c = wall.el.querySelector('.hb-sleep__clock');
		var dd = wall.el.querySelector('.hb-sleep__date');
		var ww = wall.el.querySelector('.hb-sleep__widgets');
		if (c) {
			c.textContent = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
		}
		if (dd) {
			dd.textContent = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
		}
		if (ww) { ww.innerHTML = wallWidgetsInner(); }
	}

	/* Anti-marquage : le bloc se décale de quelques pixels à intervalle
	   régulier, comme un économiseur d'écran. Amplitude faible et bornée en
	   pixels pour rester discret sur un grand écran comme sur une tablette. */
	function wallDrift() {
		if (!wall.el) { return; }
		var inner = wall.el.querySelector('.hb-sleep__inner');
		if (!inner) { return; }
		var maxX = Math.min(56, Math.round(window.innerWidth * 0.06));
		var maxY = Math.min(34, Math.round(window.innerHeight * 0.05));
		var x = Math.round((Math.random() * 2 - 1) * maxX);
		var y = Math.round((Math.random() * 2 - 1) * maxY);
		inner.style.setProperty('--hb-dx', x + 'px');
		inner.style.setProperty('--hb-dy', y + 'px');
	}

	function wallSleep() {
		if (!wall.el) {
			wall.el = document.createElement('div');
			wall.el.className = 'hb-sleep';
			wall.el.setAttribute('aria-hidden', 'true');
			wall.el.innerHTML = '<div class="hb-sleep__inner">' +
				'<div class="hb-sleep__clock"></div>' +
				'<div class="hb-sleep__date"></div>' +
				'<div class="hb-sleep__widgets"></div></div>';
			document.body.appendChild(wall.el);
		}
		wall.asleep = true;
		document.body.classList.add('hb-asleep');
		wallPaint();
		wallDrift();
		clearInterval(wall.clockTimer);
		wall.clockTimer = setInterval(wallPaint, 20000);
		clearInterval(wall.driftTimer);
		wall.driftTimer = setInterval(wallDrift, 60000);
	}

	function wallDelayMs() {
		var m = Number(S.home && S.home.wallDelay);
		if (!(m > 0)) { m = 2; }
		return Math.max(1, Math.min(60, m)) * 60000;
	}

	function wallIdle() {
		if (!S.home || !Number(S.home.wallMode)) { return; }
		clearTimeout(wall.timer);
		wall.timer = setTimeout(wallSleep, wallDelayMs());
	}

	/* Empêche la mise en veille de l'écran mural quand le navigateur l'autorise
	   (Wake Lock : HTTPS + geste utilisateur). Silencieux sinon. */
	function wallLock() {
		if (!S.home || !Number(S.home.wallMode)) { return; }
		if (!('wakeLock' in navigator) || wall.lock) { return; }
		navigator.wakeLock.request('screen').then(function (l) {
			wall.lock = l;
			l.addEventListener('release', function () { wall.lock = null; });
		}).catch(function () {});
	}

	function wallWake() {
		if (!S.home || !Number(S.home.wallMode)) { return; }
		if (wall.asleep) {
			wall.asleep = false;
			document.body.classList.remove('hb-asleep');
			clearInterval(wall.clockTimer);
			clearInterval(wall.driftTimer);
			if (wall.el) {
				var inner = wall.el.querySelector('.hb-sleep__inner');
				if (inner) {
					inner.style.setProperty('--hb-dx', '0px');
					inner.style.setProperty('--hb-dy', '0px');
				}
			}
			/* Le voile vient d'être retiré : le clic de réveil ne doit pas
			   atteindre la tuile située dessous. */
			document.addEventListener('click', function (e) {
				e.stopPropagation();
				e.preventDefault();
			}, { capture: true, once: true });
		}
		wallLock();
		wallIdle();
	}

	function applyWallMode() {
		var on = !!(S.home && Number(S.home.wallMode));
		document.body.classList.toggle('hb-wall', on);
		if (wall.bound) {
			if (!on) {
				document.body.classList.remove('hb-asleep');
				wall.asleep = false;
				clearTimeout(wall.timer);
				clearInterval(wall.clockTimer);
				clearInterval(wall.driftTimer);
			} else {
				wallIdle();
			}
			return;
		}
		if (!on) { return; }
		wall.bound = true;
		['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
			document.addEventListener(ev, wallWake, { passive: true });
		});
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') { wallWake(); }
		});
		wallIdle();
	}

	function paintHead() {
		var now = new Date();
		var clock = '';
		if (Number(S.home.showClock)) {
			clock = '<div class="hb-clock" id="hb-clock">' +
				now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + '</div>' +
				'<div class="hb-date" id="hb-date">' +
				now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) + '</div>';
		}
		var install = '';
		if (S.installEvent) {
			install = '<button class="hb-installbtn" id="hb-install">' + svg('install') + '<span>Installer</span></button>';
		}
		var exit = '';
		if (EXIT_URL) {
			exit = '<a class="hb-exitbtn" href="' + esc(EXIT_URL) + '" title="Quitter le plein écran" ' +
				'aria-label="Quitter le plein écran">' + svg('shrink') + '</a>';
		}
		/* Accès permanent à la configuration : en plein écran, la barre Jeedom
		   est masquée et il n'y avait plus aucun chemin vers les réglages. */
		var cfg = '';
		if (CFG_URL) {
			cfg = '<a class="hb-exitbtn" href="' + esc(CFG_URL) + '" title="Configurer le tableau de bord" ' +
				'aria-label="Configurer le tableau de bord">' + svg('gear') + '</a>';
		}
		headEl.innerHTML = '<div class="hb-head__left"><h1 class="hb-head__title">' + esc(S.home.homeName) + '</h1>' +
			'<p class="hb-head__sub" id="hb-summary">' + esc(summary()) + '</p></div>' +
			'<div class="hb-head__right">' + clock + install + cfg + exit + '</div>';
		var btn = document.getElementById('hb-install');
		if (btn) {
			btn.addEventListener('click', function () {
				if (!S.installEvent) { return; }
				S.installEvent.prompt();
				S.installEvent = null;
				paintHead();
			});
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Tuiles                                                             */
	/* ------------------------------------------------------------------ */

	function tileSig(t) {
		var c = t.caps || {};
		var ids = [c.state, c.bri, c.temp, c.setpoint];
		var parts = [];
		ids.forEach(function (id) {
			var v = val(id);
			parts.push(id ? (v ? String(v.value) : '') : '');
		});
		return t.kind + '|' + (isOn(t) ? 1 : 0) + '|' + parts.join('/');
	}

	function tileHtml(t, showRoom) {
		var on = isOn(t);
		/* Le type vient du serveur mais il est écrit dans un attribut class :
		   on n'accepte qu'une valeur alphabétique connue. */
		var kind = /^(light|thermostat|sensor|switch|shutter|camera|button|scene)$/.test(t.kind) ? t.kind : 'switch';
		var isAction = (kind === 'scene' || kind === 'button');
		var cls = 'hb-tile hb-tile--' + kind;
		if (t.caps && t.caps.canExec === false) { cls += ' is-locked'; }
		if (t.size === 'wide') { cls += ' hb-tile--wide'; }
		if (t.size === 'tall') { cls += ' hb-tile--tall'; }
		if (!isAction) { cls += on ? ' is-on' : ' is-off'; }

		var html = '<div class="' + cls + '" data-tile="' + t.id + '" data-sig="' + esc(tileSig(t)) + '" ' +
			(showRoom ? 'data-fav="1" ' : '') +
			'role="button" tabindex="0" aria-label="' + esc(t.label) + '">';

		if (isAction) {
			/* Scénario ou bouton poussoir : une action à déclencher, aucun état
			   à afficher (le retour est un accusé temporaire). */
			var subA = (showRoom && t.room_name) ? t.room_name : (t.sub || (kind === 'scene' ? 'Scénario' : 'Action'));
			html += '<div class="hb-tile__top"><span class="hb-tile__icon">' + svg(t.icon) + '</span>' +
				'<span class="hb-tile__run">' + svg('play') + '</span></div>' +
				'<div class="hb-tile__body"><div class="hb-tile__name">' + esc(t.label) + '</div>' +
				'<div class="hb-tile__sub">' + esc(subA) + '</div></div>';
			return html + '</div>';
		}

		if (t.kind === 'thermostat') {
			html += thermostatHtml(t, showRoom);
		} else if (t.kind === 'sensor') {
			var st = tileState(t);
			html += '<div class="hb-tile__top"><span class="hb-tile__icon">' + svg(t.icon) + '</span>' +
				(t.chart ? '<span class="hb-tile__hint">' + svg('chart') + '</span>' : '') + '</div>' +
				'<div class="hb-tile__body">' +
				'<div class="hb-value">' + esc(st ? st.display : '—') +
				(st && st.unit ? '<span class="hb-value__unit">' + esc(st.unit) + '</span>' : '') + '</div>' +
				'<div class="hb-tile__name">' + esc(t.label) + '</div>' +
				'</div>';
		} else {
			var st2 = tileState(t);
			var badge = '';
			var sub = '';
			if (t.kind === 'light') {
				var bri = val(t.caps && t.caps.bri);
				if (bri && num(bri.value) !== null && on) { badge = fmt(num(bri.value), 0) + ' %'; }
				sub = on ? 'Allumée' : 'Éteinte';
			} else if (t.kind === 'shutter') {
				var n = num(st2 ? st2.value : null);
				sub = (n === null) ? '' : (n <= 0 ? 'Fermé' : (n >= 100 ? 'Ouvert' : fmt(n, 0) + ' %'));
			} else {
				sub = on ? 'Allumé' : 'Éteint';
			}
			if (showRoom && t.room_name) { sub = t.room_name; }
			html += '<div class="hb-tile__top"><span class="hb-tile__icon">' + svg(t.icon) + '</span>' +
				'<span class="hb-tile__badge">' + esc(badge) + '</span></div>' +
				'<div class="hb-tile__body"><div class="hb-tile__name">' + esc(t.label) + '</div>' +
				'<div class="hb-tile__sub">' + esc(sub) + '</div></div>';

			if (t.kind === 'light' && t.caps && t.caps.briSet && t.caps.bri) {
				/* La luminosité se règle en appui long (feuille de détail), plus
				   dans la tuile : sur mobile le curseur se touchait en scrollant. */
			}
		}
		return html + '</div>';
	}

	function modeIsActive(t, m) {
		var c = t.caps || {};
		var st = c.state ? (val(c.state) || t.state) : t.state;
		var b = normTxt(st && st.display);
		if (!b) { return false; }
		var a = normTxt(m.name);
		if (!a) { return false; }
		return a === b || a.indexOf(b) !== -1 || b.indexOf(a) !== -1;
	}

	function thermostatHtml(t, showRoom) {
		var c = t.caps || {};
		var temp = val(c.temp);
		var setp = val(c.setpoint);
		var stateName = c.state ? (val(c.state) || t.state) : t.state;
		var cur = num(setp ? setp.value : null);
		var modes = c.modes || [];
		var activeName = String((stateName && stateName.display) || '').toLowerCase();

		var modesHtml = '';
		if (modes.length) {
			modes.forEach(function (m) {
				modesHtml += '<button class="hb-mode' + (modeIsActive(t, m) ? ' is-active' : '') +
					'" data-act="mode" data-cmd="' + m.id + '">' + esc(modeLabel(m.name)) + '</button>';
			});
		}

		return '<div class="hb-thermo__head">' +
			'<div><div class="hb-thermo__cur">' + esc(temp ? temp.display : '—') +
			'<small>' + esc((temp && temp.unit) || '°C') + '</small></div>' +
			'<div class="hb-thermo__state">' + esc(stateName ? stateName.display : '') + '</div></div>' +
			(modesHtml ? '<div class="hb-thermo__modes">' + modesHtml + '</div>' : '') +
			'</div>' +
			'<div class="hb-thermo__foot">' +
			(c.setSetpoint ? '<div class="hb-thermo__target">' +
				'<button class="hb-step" data-act="minus" aria-label="Diminuer">−</button>' +
				'<div class="hb-thermo__set">' + fmt(cur, 1) + '<small>°C</small></div>' +
				'<button class="hb-step" data-act="plus" aria-label="Augmenter">+</button>' +
				'</div>' : '<div></div>') +
			'<div class="hb-thermo__name">' + esc(showRoom && t.room_name ? t.room_name : t.label) + '</div>' +
			'</div>';
	}

	function roomSection(room, withTitle) {
		var html = '<section class="hb-section" data-room="' + room.id + '">';
		if (withTitle) {
			html += '<h2 class="hb-section__title">' + svg(room.icon || 'home') +
				'<span>' + esc(room.name) + '</span>' +
				'<span class="hb-section__count">' + room.tiles.length + '</span></h2>';
		}
		html += '<div class="hb-grid">';
		if (!room.tiles.length) {
			html += '<div class="hb-empty">Aucune tuile dans cette pièce.' +
				(CFG_URL ? '<br><a href="' + esc(CFG_URL) + '">Ajouter des commandes</a>' : '') + '</div>';
		} else {
			room.tiles.forEach(function (t) { html += tileHtml(t); });
		}
		return html + '</div></section>';
	}

	/* ------------------------------------------------------------------ */
	/*  Graphiques                                                         */
	/* ------------------------------------------------------------------ */

	function chartCardHtml(ch) {
		var v = num(ch.value);
		return '<button class="hb-chartcard" data-chart="' + ch.cmd_id + '" data-tile="' + ch.tile_id + '">' +
			'<div class="hb-chartcard__head">' +
			'<span class="hb-chartcard__icon">' + svg(ch.icon || 'sensor') + '</span>' +
			'<span class="hb-chartcard__meta"><span class="hb-chartcard__label">' + esc(ch.label) + '</span>' +
			'<span class="hb-chartcard__room">' + esc(ch.room) + '</span></span>' +
			'<span class="hb-chartcard__value">' + (v === null ? '—' : esc(fmt(v, undefined))) +
			(ch.unit ? ' <small>' + esc(ch.unit) + '</small>' : '') + '</span>' +
			'</div>' +
			'<div class="hb-chartcard__spark" data-spark="' + ch.cmd_id + '"></div>' +
			'<div class="hb-chartcard__stats" data-stats="' + ch.cmd_id + '"></div>' +
			'</button>';
	}

	function chartsView() {
		var html = '<section class="hb-section"><h2 class="hb-section__title">' + svg('chart') +
			'<span>Graphiques</span></h2>';
		if (!S.charts.length) {
			return html + '<div class="hb-grid"><div class="hb-empty">Aucune commande historisée pour le moment.' +
				'<br>Activez « Historiser » sur une commande dans Jeedom, puis ajoutez-la comme tuile.</div></div></section>';
		}
		html += '<div class="hb-charts">';
		S.charts.forEach(function (ch) { html += chartCardHtml(ch); });
		return html + '</div></section>';
	}

	function sparkline(points) {
		if (!points || points.length < 2) { return '<div class="hb-spark-empty">Pas d\'historique</div>'; }
		var W = 300, H = 64, pad = 3;
		var min = Infinity, max = -Infinity;
		points.forEach(function (p) { min = Math.min(min, p[1]); max = Math.max(max, p[1]); });
		if (max - min < 0.001) { max = min + 1; }
		var span = max - min;
		var x0 = points[0][0], x1 = points[points.length - 1][0];
		var dx = (x1 - x0) || 1;
		var d = '';
		points.forEach(function (p, i) {
			var x = ((p[0] - x0) / dx) * (W - pad * 2) + pad;
			var y = H - pad - ((p[1] - min) / span) * (H - pad * 2);
			d += (i === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1) + ' ';
		});
		var area = d + 'L' + (W - pad) + ' ' + (H - pad) + ' L' + pad + ' ' + (H - pad) + ' Z';
		return '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" class="hb-spark">' +
			'<path d="' + area + '" fill="rgba(245,158,11,0.10)"/>' +
			'<path d="' + d + '" fill="none" stroke="#f59e0b" stroke-width="1.6" stroke-linejoin="round" ' +
			'stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>';
	}

	function bigChart(points) {
		if (!points || points.length < 2) {
			return '<div class="hb-chart-empty">Pas encore d\'historique sur cette période.</div>';
		}
		var W = 600, H = 200, padT = 10, padB = 10;
		var min = Infinity, max = -Infinity;
		points.forEach(function (p) { min = Math.min(min, p[1]); max = Math.max(max, p[1]); });
		if (max - min < 0.001) { max = min + 1; }
		var span = max - min;
		var x0 = points[0][0], x1 = points[points.length - 1][0];
		var dx = (x1 - x0) || 1;
		var d = '';
		points.forEach(function (p, i) {
			var x = ((p[0] - x0) / dx) * W;
			var y = H - padB - ((p[1] - min) / span) * (H - padT - padB);
			d += (i === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1) + ' ';
		});
		var area = d + 'L' + W + ' ' + (H - padB) + ' L0 ' + (H - padB) + ' Z';
		var grid = '';
		for (var i = 0; i <= 3; i++) {
			var y = padT + (i * (H - padT - padB) / 3);
			grid += '<line x1="0" y1="' + y + '" x2="' + W + '" y2="' + y + '" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>';
		}
		return '<div class="hb-bigchart">' +
			'<div class="hb-bigchart__ylabels"><span>' + esc(fmt(max, undefined)) + '</span>' +
			'<span>' + esc(fmt(min, undefined)) + '</span></div>' +
			'<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" class="hb-bigchart__svg">' +
			grid +
			'<path d="' + area + '" fill="rgba(245,158,11,0.10)"/>' +
			'<path d="' + d + '" fill="none" stroke="#f59e0b" stroke-width="1.8" stroke-linejoin="round" ' +
			'stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>' +
			'<div class="hb-bigchart__xlabels"><span>' + esc(hhmm(x0)) + '</span><span>' + esc(hhmm(x1)) + '</span></div>' +
			'</div>';
	}

	function sortChartCards() {
		var wrap = sectionsEl.querySelector('.hb-charts');
		if (!wrap) { return; }
		var cards = Array.prototype.slice.call(wrap.children);
		cards.sort(function (a, b) {
			var da = S.sparks[a.getAttribute('data-chart')];
			var db = S.sparks[b.getAttribute('data-chart')];
			var na = (da && da.points && da.points.length > 1) ? 0 : 1;
			var nb = (db && db.points && db.points.length > 1) ? 0 : 1;
			return na - nb;
		});
		cards.forEach(function (c) { wrap.appendChild(c); });
	}

	function loadSparks() {
		S.charts.forEach(function (ch) {
			if (S.sparks[ch.cmd_id]) { return; }
			api({ action: 'history', cmd_id: ch.cmd_id, period: 'day' }).then(function (h) {
				S.sparks[ch.cmd_id] = h;
				var box = sectionsEl.querySelector('[data-spark="' + ch.cmd_id + '"]');
				if (box) { box.innerHTML = sparkline(h.points); }
				var st = sectionsEl.querySelector('[data-stats="' + ch.cmd_id + '"]');
				if (st && h.min !== null) {
					st.textContent = 'min ' + fmt(h.min, undefined) + ' · moy ' + fmt(h.avg, undefined) +
						' · max ' + fmt(h.max, undefined) + (ch.unit ? ' ' + ch.unit : '');
				}
				sortChartCards();
			}).catch(function () {});
		});
	}

	/* ------------------------------------------------------------------ */
	/*  Feuille de détail (graphique)                                      */
	/* ------------------------------------------------------------------ */

	function openSheet(ch) {
		closeSheet();
		var el = document.createElement('div');
		el.className = 'hb-sheet';
		el.innerHTML = '<div class="hb-sheet__backdrop" data-close="1"></div>' +
			'<div class="hb-sheet__panel" role="dialog" aria-modal="true" aria-label="' + esc(ch.label) + '">' +
			'<div class="hb-sheet__head">' +
			'<div><div class="hb-sheet__title">' + esc(ch.label) + '</div>' +
			'<div class="hb-sheet__sub">' + esc(ch.room) + ' · ' + esc(ch.chart_label) + '</div></div>' +
			'<button class="hb-sheet__close" data-close="1" aria-label="Fermer">' + svg('close') + '</button>' +
			'</div>' +
			'<div class="hb-sheet__value" data-value>—</div>' +
			'<div class="hb-periods" data-periods>' +
			'<button data-period="day" class="is-active">24 h</button>' +
			'<button data-period="week">7 jours</button>' +
			'<button data-period="month">30 jours</button>' +
			'</div>' +
			'<div class="hb-sheet__chart" data-chart></div>' +
			'<div class="hb-sheet__stats" data-stats></div>' +
			'</div>';
		document.body.appendChild(el);
		S.sheet = { el: el, ch: ch, period: 'day' };
		document.body.classList.add('hb-noscroll');

		el.addEventListener('click', function (e) {
			if (e.target.closest('[data-close]')) { closeSheet(); return; }
			var p = e.target.closest('[data-period]');
			if (p) {
				Array.prototype.forEach.call(el.querySelectorAll('[data-period]'), function (b) {
					b.classList.toggle('is-active', b === p);
				});
				S.sheet.period = p.getAttribute('data-period');
				renderSheetChart();
			}
		});
		renderSheetChart();
	}

	function renderSheetChart() {
		if (!S.sheet) { return; }
		var ch = S.sheet.ch;
		var box = S.sheet.el.querySelector('[data-chart]');
		box.innerHTML = '<div class="hb-chart-empty">Chargement…</div>';
		api({ action: 'history', cmd_id: ch.cmd_id, period: S.sheet.period }).then(function (h) {
			if (!S.sheet) { return; }
			box.innerHTML = bigChart(h.points);
			var st = S.sheet.el.querySelector('[data-stats]');
			if (h.min === null) {
				st.textContent = 'Aucune donnée sur cette période.';
			} else {
				st.innerHTML = '<span><b>' + fmt(h.min, undefined) + '</b> min</span>' +
					'<span><b>' + fmt(h.avg, undefined) + '</b> moy</span>' +
					'<span><b>' + fmt(h.max, undefined) + '</b> max</span>' +
					'<span><b>' + fmt(h.last, undefined) + '</b> dernier</span>';
			}
			var v = S.sheet.el.querySelector('[data-value]');
			var last = h.last !== null ? h.last : num(ch.value);
			v.innerHTML = (last === null ? '—' : fmt(last, undefined)) +
				(h.unit ? '<small>' + esc(h.unit) + '</small>' : '');
		}).catch(function (e) {
			box.innerHTML = '<div class="hb-chart-empty">' + esc(e.message) + '</div>';
		});
	}

	function closeSheet() {
		if (!S.sheet) { return; }
		if (S.sheet.el.parentNode) { S.sheet.el.parentNode.removeChild(S.sheet.el); }
		S.sheet = null;
		document.body.classList.remove('hb-noscroll');
	}

	/* ------------------------------------------------------------------ */
	/*  Feuille de détail (appui long sur une tuile)                       */
	/* ------------------------------------------------------------------ */

	function detailHtml(t) {
		var c = t.caps || {};
		var on = isOn(t);
		var html = '<div class="hb-detail">' +
			'<div class="hb-detail__head">' +
			'<span class="hb-detail__icon">' + svg(t.icon) + '</span>' +
			'<div><div class="hb-detail__title">' + esc(t.label) + '</div>' +
			'<div class="hb-detail__sub">' + esc(t.sub || '') + '</div></div>' +
			'<button class="hb-sheet__close" data-close="1" aria-label="Fermer">' + svg('close') + '</button>' +
			'</div>';

		if (t.kind === 'light') {
			var bri = c.bri ? num(val(c.bri) ? val(c.bri).value : null) : null;
			html += '<div class="hb-detail__row"><button class="hb-bigbtn' + (on ? ' is-primary' : '') +
				'" data-act="toggle">' + svg('power') + '<span>' + (on ? 'Allumée' : 'Éteinte') +
				'</span></button></div>';
			if (c.briSet && c.bri) {
				var bmin = num(c.briMin); if (bmin === null) { bmin = 0; }
				var bmax = num(c.briMax); if (bmax === null) { bmax = 100; }
				var bstep = num(c.briStep) || 1;
				var b = bri === null ? bmin : Math.round(bri);
				var bpct = bmax > bmin ? Math.round((b - bmin) / (bmax - bmin) * 100) : 0;
				html += '<div class="hb-slider">' +
					'<input type="range" min="' + bmin + '" max="' + bmax + '" step="' + bstep +
					'" value="' + b + '" data-fill="' + bpct + '" aria-label="Luminosité">' +
					'<span class="hb-slider__pct">' + bpct + ' %</span></div>' +
					'<div class="hb-presets">' +
					[20, 50, 75, 100].map(function (p) {
						var v = Math.round(bmin + (bmax - bmin) * p / 100);
						return '<button data-act="preset" data-value="' + v + '">' + p + ' %</button>';
					}).join('') + '</div>';
			}
		} else if (t.kind === 'thermostat') {
			var temp = val(c.temp);
			var setp = num(val(c.setpoint) ? val(c.setpoint).value : null);
			html += '<div class="hb-detail__value">' + esc(temp ? temp.display : '—') +
				'<small>' + esc((temp && temp.unit) || '°C') + '</small></div>';
			if (c.setSetpoint) {
				html += '<div class="hb-detail__row">' +
					'<button class="hb-bigbtn hb-bigbtn--step" data-act="minus" aria-label="Diminuer">−</button>' +
					'<div class="hb-detail__target">' + fmt(setp, 1) + '<small>°C</small></div>' +
					'<button class="hb-bigbtn hb-bigbtn--step" data-act="plus" aria-label="Augmenter">+</button></div>';
			}
			if (c.modes && c.modes.length) {
				html += '<div class="hb-seg">' + c.modes.map(function (m) {
					return '<button class="hb-seg__btn' + (modeIsActive(t, m) ? ' is-active' : '') +
						'" data-act="mode" data-cmd="' + m.id + '">' + esc(modeLabel(m.name)) + '</button>';
				}).join('') + '</div>';
			}
		} else if (t.kind === 'shutter') {
			var st = tileState(t);
			var pos = num(st ? st.value : null);
			html += '<div class="hb-detail__value">' + (pos === null ? '—' : fmt(pos, 0)) +
				'<small>%</small></div><div class="hb-detail__row">' +
				(c.up ? '<button class="hb-bigbtn" data-act="up">Ouvrir</button>' : '') +
				(c.stop ? '<button class="hb-bigbtn" data-act="stop">Stop</button>' : '') +
				(c.down ? '<button class="hb-bigbtn" data-act="down">Fermer</button>' : '') + '</div>';
		} else if (t.kind === 'sensor' || t.kind === 'camera') {
			var s2 = tileState(t);
			html += '<div class="hb-detail__value">' + esc(s2 ? s2.display : '—') +
				(s2 && s2.unit ? '<small>' + esc(s2.unit) + '</small>' : '') + '</div>';
			if (t.chart) {
				html += '<div class="hb-detail__row"><button class="hb-bigbtn" data-act="chart">' +
					svg('chart') + '<span>Voir la courbe</span></button></div>';
			}
		} else {
			html += '<div class="hb-detail__row"><button class="hb-bigbtn' + (on ? ' is-primary' : '') +
				'" data-act="toggle">' + svg('power') + '<span>' + (on ? 'Allumé' : 'Éteint') +
				'</span></button></div>';
		}

		return html + '</div>';
	}

	function openDetail(t) {
		closeSheet();
		var el = document.createElement('div');
		el.className = 'hb-sheet';
		el.innerHTML = '<div class="hb-sheet__backdrop" data-close="1"></div>' +
			'<div class="hb-sheet__panel" role="dialog" aria-modal="true" aria-label="' + esc(t.label) + '">' +
			detailHtml(t) + '</div>';
		document.body.appendChild(el);
		applyFillVars(el);
		S.sheet = { el: el, tile: t, kind: 'detail' };
		document.body.classList.add('hb-noscroll');
		el.addEventListener('click', detailClick);
		el.addEventListener('input', detailInput);
		el.addEventListener('change', detailChange);
		/* Marque l'appui en cours pour que le rafraîchissement automatique ne
		   reconstruise pas le panneau sous le doigt. */
		el.addEventListener('pointerdown', function () {
			if (S.sheet) { S.sheet.pressing = true; }
		});
		var release = function () {
			if (!S.sheet) { return; }
			S.sheet.pressing = false;
			if (S.sheet.retry) {
				S.sheet.retry = false;
				setTimeout(refreshDetail, 120);
			}
		};
		el.addEventListener('pointerup', release);
		el.addEventListener('pointercancel', release);
	}

	function refreshDetail() {
		if (!S.sheet || S.sheet.kind !== 'detail' || S.dragging) { return; }
		/* Ne jamais reconstruire le panneau pendant un appui : le bouton serait
		   détruit avant que son click ne parte. */
		if (S.sheet.pressing) {
			S.sheet.retry = true;
			return;
		}
		var t = S.tiles[String(S.sheet.tile.id)];
		if (!t) { return; }
		S.sheet.tile = t;
		var panel = S.sheet.el.querySelector('.hb-sheet__panel');
		if (panel) {
			panel.innerHTML = detailHtml(t);
			applyFillVars(panel);
		}
	}

	/* Les valeurs de style sont posées via CSSOM et non en attribut inline :
	   la CSP de la PWA n'autorise pas les styles inline. */
	function applyFillVars(scope) {
		if (!scope) { return; }
		Array.prototype.forEach.call(scope.querySelectorAll('[data-fill]'), function (el) {
			el.style.setProperty('--hb-fill', el.getAttribute('data-fill') + '%');
		});
	}

	function detailClick(e) {
		if (e.target.closest('[data-close]')) { closeSheet(); return; }
		var b = e.target.closest('[data-act]');
		if (!b || !S.sheet) { return; }
		var t = S.sheet.tile;
		var c = t.caps || {};
		if (!canAct(t)) { return; }
		var act = b.getAttribute('data-act');
		if (act === 'toggle') {
			toggle(t);
			setTimeout(refreshDetail, 600);
		} else if (act === 'plus' || act === 'minus') {
			stepSetpoint(t, act);
			setTimeout(refreshDetail, 600);
		} else if (act === 'mode') {
			Array.prototype.forEach.call(S.sheet.el.querySelectorAll('.hb-seg__btn'), function (p) {
				p.classList.toggle('is-active', p === b);
			});
			haptic();
			exec(b.getAttribute('data-cmd'), '');
		} else if (act === 'up' || act === 'down' || act === 'stop') {
			haptic();
			exec(c[act], '');
		} else if (act === 'preset') {
			var v = b.getAttribute('data-value');
			var inp = S.sheet.el.querySelector('input[type="range"]');
			if (inp) {
				inp.value = v;
				inp.style.setProperty('--hb-fill', fillPct(inp) + '%');
			}
			var pct = S.sheet.el.querySelector('.hb-slider__pct');
			if (pct) { pct.textContent = fillPct(inp) + ' %'; }
			haptic();
			exec(c.briSet, v, { id: c.bri, value: v });
			setTimeout(refreshDetail, 600);
		} else if (act === 'chart' && t.chart) {
			var ch = t.chart;
			closeSheet();
			openSheet({
				cmd_id: ch.cmd_id, label: t.label, room: t.sub, chart_label: ch.label,
				unit: ch.unit, value: t.state.value, tile_id: t.id, icon: t.icon
			});
		}
	}

	function detailInput(e) {
		if (!e.target.matches || !e.target.matches('input[type="range"]')) { return; }
		S.dragging = true;
		e.target.style.setProperty('--hb-fill', fillPct(e.target) + '%');
		var pct = S.sheet.el.querySelector('.hb-slider__pct');
		if (pct) { pct.textContent = fillPct(e.target) + ' %'; }
	}

	/* Position relative du curseur dans sa plage réelle (min/max lus côté
	   serveur) : afficher la valeur brute serait faux pour un 0-254. */
	function fillPct(inp) {
		var min = Number(inp.min || 0);
		var max = Number(inp.max || 100);
		var v = Number(inp.value);
		if (!(max > min)) { return 0; }
		return Math.round((v - min) / (max - min) * 100);
	}

	function detailChange(e) {
		if (!e.target.matches || !e.target.matches('input[type="range"]')) { return; }
		S.dragging = false;
		var t = S.sheet && S.sheet.tile;
		if (!t || !t.caps || !t.caps.briSet) { return; }
		haptic();
		exec(t.caps.briSet, e.target.value, { id: t.caps.bri, value: e.target.value });
		setTimeout(refreshDetail, 600);
	}

	/* ------------------------------------------------------------------ */
	/*  Navigation : suit la pièce visible au défilement                   */
	/* ------------------------------------------------------------------ */

	var navFollow = { frame: null, current: null };

	function navItems() {
		var box = navList || navEl;
		return box ? box.querySelectorAll('.hb-nav__item[data-room]') : [];
	}

	function centerNavItem(el) {
		var box = navList || navEl;
		if (!box || !el) { return; }
		var r = el.getBoundingClientRect();
		var b = box.getBoundingClientRect();
		var vertical = getComputedStyle(box).flexDirection === 'column';
		if (vertical) {
			box.scrollTop += (r.top - b.top) - (b.height - r.height) / 2;
		} else {
			box.scrollLeft += (r.left - b.left) - (b.width - r.width) / 2;
		}
	}

	function updateNavFromScroll() {
		navFollow.frame = null;
		if (!sectionsEl || S.active !== 'all') { return; }
		var secs = sectionsEl.querySelectorAll('.hb-section[data-room]');
		if (!secs.length) { return; }
		var limit = 120;
		var current = null;
		Array.prototype.forEach.call(secs, function (sec) {
			if (sec.getBoundingClientRect().top <= limit) {
				current = sec.getAttribute('data-room');
			}
		});
		if (current === navFollow.current) { return; }
		navFollow.current = current;
		Array.prototype.forEach.call(navItems(), function (it) {
			var on = current !== null && it.getAttribute('data-room') === current;
			it.classList.toggle('is-current', on);
			if (on) { centerNavItem(it); }
		});
	}

	function scheduleNavFollow() {
		if (navFollow.frame) { return; }
		navFollow.frame = requestAnimationFrame(updateNavFromScroll);
	}

	function resetNavFollow() {
		navFollow.current = null;
		Array.prototype.forEach.call(navItems(), function (it) {
			it.classList.remove('is-current');
		});
	}

	function paintSections() {
		if (S.active === 'charts') {
			sectionsEl.innerHTML = chartsView();
			loadSparks();
			return;
		}
		var html = '';
		var emptyAll = true;
		S.rooms.forEach(function (r) { if (r.tiles.length) { emptyAll = false; } });

		if (S.favorites.length && S.active === 'all') {
			html += '<section class="hb-section"><h2 class="hb-section__title">' +
				svg('scene') + '<span>Favoris</span></h2><div class="hb-grid">';
			S.favorites.forEach(function (t) { html += tileHtml(t, true); });
			html += '</div></section>';
		}

		if (S.active === 'all') {
			S.rooms.forEach(function (r) {
				if (r.tiles.length) { html += roomSection(r, true); }
			});
		} else {
			var room = null;
			S.rooms.forEach(function (r) { if (String(r.id) === String(S.active)) { room = r; } });
			if (room) { html += roomSection(room, true); }
		}

		if (emptyAll) {
			html = '<div class="hb-grid"><div class="hb-empty">Aucune commande configurée pour le moment.' +
				(CFG_URL ? '<br><a href="' + esc(CFG_URL) + '">Configurer le tableau de bord</a>' : '') +
				'</div></div>';
		}
		sectionsEl.innerHTML = html;
		scheduleNavFollow();
	}

	/* Rafraîchissement ciblé : ne remplace que les tuiles dont la valeur change */
	function refreshTiles() {
		if (S.active === 'charts' || S.dragging) { return; }
		Object.keys(S.tiles).forEach(function (id) {
			var t = S.tiles[id];
			var sig = tileSig(t);
			var els = sectionsEl.querySelectorAll('[data-tile="' + id + '"]');
			if (!els.length) { return; }
			var needs = false;
			Array.prototype.forEach.call(els, function (el) {
				if (el.getAttribute('data-sig') !== sig) { needs = true; }
			});
			if (!needs) { return; }
			Array.prototype.forEach.call(els, function (el) {
				var fav = el.getAttribute('data-fav') === '1';
				var src = fav && S.favById[id] ? S.favById[id] : t;
				var wrap = document.createElement('div');
				wrap.innerHTML = tileHtml(src, fav);
				if (el.parentNode) { el.parentNode.replaceChild(wrap.firstChild, el); }
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/*  Actions                                                            */
	/* ------------------------------------------------------------------ */

	function setLocal(cmdId, value) {
		if (!cmdId) { return; }
		var prev = S.values[cmdId] || {};
		S.values[cmdId] = { id: cmdId, value: value, display: optimisticDisplay(prev.display, value), unit: prev.unit || '' };
		S.pending[cmdId] = { value: String(value), until: Date.now() + 8000 };
		if (!S.dragging) { refreshTiles(); }
	}

	/* Libellé d'état pendant l'attente de la confirmation : l'ancien libellé
	   restait affiché (« Allumé » alors que l'appareil venait de passer à 0). */
	function optimisticDisplay(prev, value) {
		var v = String(value);
		var on = (value === true || value === 1 || v === '1' || v.toLowerCase() === 'on');
		var pairs = [['Allumé', 'Éteint'], ['Ouvert', 'Fermé'], ['Présence', 'Absence'],
			['Fuite', 'Sec'], ['Fumée', 'Pas de fumée'], ['Actif', 'Inactif']];
		for (var i = 0; i < pairs.length; i++) {
			if (prev === pairs[i][0] || prev === pairs[i][1]) {
				return on ? pairs[i][0] : pairs[i][1];
			}
		}
		return v;
	}

	function exec(cmdId, value, optimistic) {
		if (!cmdId) { return Promise.resolve(); }
		if (optimistic) { setLocal(optimistic.id, optimistic.value); }
		return api({ action: 'exec', cmd_id: cmdId, value: value === null || value === undefined ? '' : value }, 'POST')
			.then(function () { setTimeout(poll, 250); })
			.catch(function (e) {
				toast('Échec : ' + e.message);
				poll();
			});
	}

	function toggle(t) {
		var c = t.caps || {};
		var on = isOn(t);
		var cmdId = on ? c.off : c.on;
		if (!cmdId) { cmdId = c.toggle; }
		if (!cmdId) { toast('Aucune commande On/Off trouvée'); return; }
		if (!canAct(t)) { return; }
		var stateId = c.state || (t.state && t.state.id);
		haptic();
		exec(cmdId, '', { id: stateId, value: on ? 0 : 1 });
	}

	/* Tuile visible mais équipement non exécutable (profil restreint) : la
	   commande ne part pas et l'utilisateur est prévenu. */
	function canAct(t) {
		if (t && t.caps && t.caps.canExec === false) {
			toast('Droits insuffisants sur cet équipement');
			return false;
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/*  Scénarios et boutons poussoirs                                     */
	/* ------------------------------------------------------------------ */

	var flashTimers = {};

	/* Accusé temporaire dans la sous-ligne de la tuile. Le texte d'origine est
	   mémorisé pour être restauré sans redessiner toute la grille. */
	function flashTile(id, text) {
		var els = sectionsEl.querySelectorAll('[data-tile="' + id + '"]');
		if (!els.length) { return; }
		Array.prototype.forEach.call(els, function (el) {
			el.classList.add('is-flash');
			var sub = el.querySelector('.hb-tile__sub');
			if (sub) {
				if (!sub.hasAttribute('data-orig')) { sub.setAttribute('data-orig', sub.textContent); }
				sub.textContent = text;
			}
		});
		clearTimeout(flashTimers[id]);
		flashTimers[id] = setTimeout(function () {
			Array.prototype.forEach.call(els, function (el) {
				el.classList.remove('is-flash');
				var sub = el.querySelector('.hb-tile__sub');
				if (sub && sub.hasAttribute('data-orig')) {
					sub.textContent = sub.getAttribute('data-orig');
					sub.removeAttribute('data-orig');
				}
			});
		}, 3200);
	}

	function runScene(t) {
		if (!t.scenario_id || !canAct(t)) { return; }
		haptic();
		flashTile(t.id, 'Envoi…');
		api({ action: 'runScenario', id: t.scenario_id }, 'POST').then(function (r) {
			if (!r || !r.launched) {
				flashTile(t.id, 'Non lancé');
				toast('Scénario non lancé (désactivé ou droits insuffisants)');
				return;
			}
			flashTile(t.id, 'En cours…');
			pollScenario(t, 0);
		}).catch(function (e) {
			flashTile(t.id, 'Échec');
			toast(e.message);
		});
	}

	/* Retour d'exécution réel : on interroge l'état du scénario jusqu'à ce
	   qu'il quitte « starting » / « in progress ». */
	function pollScenario(t, n) {
		if (n > 15) { flashTile(t.id, 'Lancé'); return; }
		setTimeout(function () {
			api({ action: 'scenarioState', ids: t.scenario_id }).then(function (r) {
				var s = r ? r[t.scenario_id] : null;
				if (!s) { flashTile(t.id, 'Lancé'); return; }
				if (s.state === 'starting' || s.state === 'in progress') { pollScenario(t, n + 1); return; }
				flashTile(t.id, 'Terminé');
			}).catch(function () { pollScenario(t, n + 1); });
		}, 700);
	}

	function pressButton(t) {
		if (!t.cmd_id || !canAct(t)) { return; }
		haptic();
		flashTile(t.id, 'Envoyé');
		exec(t.cmd_id, '');
	}

	function stepSetpoint(t, dir) {
		var c = t.caps || {};
		if (!c.setSetpoint || !canAct(t)) { return; }
		var cur = num(val(c.setpoint) ? val(c.setpoint).value : null);
		if (cur === null) {
			/* Sans consigne réelle, on ne devine pas : envoyer une valeur
			   arbitraire à un thermostat est pire que ne rien faire. */
			toast('Consigne indisponible — réessaie dans un instant');
			return;
		}
		var step = num(c.spStep) || 0.5;
		var min = num(c.spMin); if (min === null) { min = 5; }
		var max = num(c.spMax); if (max === null) { max = 30; }
		var next = Math.round((cur + (dir === 'plus' ? step : -step)) / step) * step;
		next = Math.max(min, Math.min(max, Math.round(next * 100) / 100));
		haptic();
		exec(c.setSetpoint, next, { id: c.setpoint, value: next });
	}

	function onAct(t, el) {
		var act = el.getAttribute('data-act');
		var c = t.caps || {};
		if (!canAct(t)) { return; }
		if (act === 'plus' || act === 'minus') {
			stepSetpoint(t, act);
		} else if (act === 'mode') {
			var siblings = el.parentNode ? el.parentNode.querySelectorAll('.hb-mode') : [];
			Array.prototype.forEach.call(siblings, function (p) {
				p.classList.toggle('is-active', p === el);
			});
			haptic();
			exec(el.getAttribute('data-cmd'), '');
		} else if (act === 'up' || act === 'down' || act === 'stop') {
			haptic();
			exec(c[act], '');
		}
	}

	function bind() {
		root = document.getElementById('hb-root');
		if (!root) { return false; }
		API = root.getAttribute('data-api');
		CFG_URL = root.getAttribute('data-config') || '';
		EXIT_URL = root.getAttribute('data-exit') || '';

		root.addEventListener('click', function (e) {
			var nav = e.target.closest ? e.target.closest('.hb-nav__item') : null;
			if (nav && nav.getAttribute('data-room')) {
				var r = nav.getAttribute('data-room');
				S.active = (r === 'all' || r === 'charts') ? r : Number(r);
				markActive();
				resetNavFollow();
				paintSections();
				window.scrollTo({ top: 0, behavior: 'smooth' });
				scheduleNavFollow();
				return;
			}
			var card = e.target.closest ? e.target.closest('.hb-chartcard') : null;
			if (card) {
				var ch = null;
				S.charts.forEach(function (c) {
					if (String(c.cmd_id) === card.getAttribute('data-chart')) { ch = c; }
				});
				if (ch) { openSheet(ch); }
				return;
			}
			var el = e.target.closest ? e.target.closest('[data-tile]') : null;
			if (!el) { return; }
			if (press.fired) { press.fired = false; return; }
			var t = S.tiles[el.getAttribute('data-tile')];
			if (!t) { return; }
			var actEl = e.target.closest('[data-act]');
			if (actEl) { e.preventDefault(); onAct(t, actEl); return; }
			if (t.kind === 'scene') { runScene(t); return; }
			if (t.kind === 'button') { pressButton(t); return; }
			if (t.kind === 'sensor') {
				if (t.chart) {
					openSheet({
						cmd_id: t.chart.cmd_id, label: t.label, room: t.sub,
						chart_label: t.chart.label, unit: t.chart.unit, value: t.state.value,
						tile_id: t.id, icon: t.icon
					});
				} else {
					openDetail(t);
				}
				return;
			}
			if (t.kind === 'thermostat' || t.kind === 'camera' || t.kind === 'shutter') {
				openDetail(t);
				return;
			}
			toggle(t);
		});

		root.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' && e.key !== ' ') { return; }
			var el = e.target.closest ? e.target.closest('[data-tile]') : null;
			if (!el || e.target.closest('.hb-bri')) { return; }
			e.preventDefault();
			var t = S.tiles[el.getAttribute('data-tile')];
			if (!t) { return; }
			if (t.kind === 'scene') { runScene(t); return; }
			if (t.kind === 'button') { pressButton(t); return; }
			if (t.kind !== 'thermostat' && t.kind !== 'sensor') { toggle(t); }
		});

		window.addEventListener('scroll', scheduleNavFollow, { passive: true });
		window.addEventListener('resize', scheduleNavFollow, { passive: true });

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { closeSheet(); }
		});

		/* Appui long (480 ms) => feuille de détail ; appui court => on/off */
		var press = { timer: null, x: 0, y: 0, fired: false };

		root.addEventListener('pointerdown', function (e) {
			if (!e.target.closest) { return; }
			var el = e.target.closest('[data-tile]');
			if (!el || e.target.closest('[data-act]')) { return; }
			press.x = e.clientX;
			press.y = e.clientY;
			press.fired = false;
			clearTimeout(press.timer);
			press.timer = setTimeout(function () {
				press.fired = true;
				haptic(18);
				var t = S.tiles[el.getAttribute('data-tile')];
				/* Pas de feuille de détail pour une tuile d'action : l'appui
				   long doit tout de même déclencher le scénario/bouton. */
				if (t && (t.kind === 'scene' || t.kind === 'button')) {
					press.fired = false;
					return;
				}
				if (t) { openDetail(t); }
			}, 480);
		});

		root.addEventListener('pointermove', function (e) {
			if (!press.timer) { return; }
			if (Math.abs(e.clientX - press.x) > 12 || Math.abs(e.clientY - press.y) > 12) {
				clearTimeout(press.timer);
				press.timer = null;
			}
		});

		root.addEventListener('pointerup', function () {
			clearTimeout(press.timer);
			press.timer = null;
		});

		root.addEventListener('pointercancel', function () {
			clearTimeout(press.timer);
			press.timer = null;
			press.fired = false;
		});

		return true;
	}

	/* ------------------------------------------------------------------ */
	/*  Synchronisation                                                    */
	/* ------------------------------------------------------------------ */

	function collectIds() {
		var out = {};
		S.rooms.forEach(function (r) {
			r.tiles.forEach(function (t) {
				var c = t.caps || {};
				[c.state, c.bri, c.temp, c.setpoint, (t.state && t.state.id),
					(t.chart && t.chart.cmd_id)].forEach(function (id) {
					if (id) { out[id] = 1; }
				});
			});
		});
		S.favorites.forEach(function (t) {
			var c = t.caps || {};
			[c.state, c.bri, c.temp, c.setpoint, (t.chart && t.chart.cmd_id)].forEach(function (id) {
				if (id) { out[id] = 1; }
			});
		});
		/* Widgets du mode mur : souvent des commandes qui ne sont sur aucune
		   tuile (conso, météo), donc à interroger explicitement. */
		((S.home && S.home.wallWidgets) || []).forEach(function (w) {
			if (w && w.cmd) { out[w.cmd] = 1; }
		});
		return Object.keys(out);
	}

	function status(ok) {
		var dot = document.getElementById('hb-dot');
		var txt = document.getElementById('hb-foot-txt');
		if (!dot) { return; }
		dot.classList.toggle('is-stale', !ok);
		if (txt) { txt.textContent = ok ? 'Synchronisé' : 'Hors ligne — nouvelle tentative…'; }
	}

	function poll() {
		if (document.hidden || S.polling) { return; }
		var ids = collectIds();
		if (!ids.length) { return; }
		S.polling = true;
		api({ action: 'values', ids: ids.join(',') }).then(function (vals) {
			var changed = false;
			Object.keys(vals).forEach(function (id) {
				var a = S.values[id];
				var b = vals[id];
				var p = S.pending[id];
				if (p) {
					if (String(b.value) === p.value) { delete S.pending[id]; }
					else if (Date.now() < p.until) { return; }
					else { delete S.pending[id]; }
				}
				if (!a || String(a.value) !== String(b.value) || String(a.display) !== String(b.display)) {
					S.values[id] = b;
					changed = true;
				}
			});
			status(true);
			var sum = document.getElementById('hb-summary');
			if (sum) { sum.textContent = summary(); }
			if (changed) { refreshTiles(); }
			if (S.sheet && S.sheet.kind === 'detail') { refreshDetail(); }
			if (wall.asleep) { wallPaint(); }
			S.polling = false;
		}).catch(function () { S.polling = false; status(false); });
	}

	function schedule() {
		clearInterval(S.timer);
		S.timer = setInterval(poll, Math.max(2, Number(S.home.refresh) || 3) * 1000);
	}

	/* ------------------------------------------------------------------ */
	/*  Démarrage                                                          */
	/* ------------------------------------------------------------------ */

	function indexTiles() {
		S.tiles = {};
		S.rooms.forEach(function (r) {
			r.tiles.forEach(function (t) { S.tiles[String(t.id)] = t; });
		});
	}

	function seedValues() {
		S.rooms.forEach(function (r) {
			r.tiles.forEach(function (t) {
				var c = t.caps || {};
				if (t.state && t.state.id) { S.values[t.state.id] = t.state; }
				if (t.extra) {
					Object.keys(t.extra).forEach(function (k) {
						var id = c[k];
						if (id) { S.values[id] = t.extra[k]; }
					});
				}
			});
		});
	}

	function boot() {
		if (!bind()) { return; }
		/* Service worker : uniquement sur la page PWA (pas sur le dashboard
		   Jeedom, où il n'a rien à faire). */
		if ('serviceWorker' in navigator && /\/plugins\/homeboard\/pwa\//.test(location.pathname)) {
			window.addEventListener('load', function () {
				navigator.serviceWorker.register('./sw.js', { scope: './' }).catch(function () {});
			});
		}
		api({ action: 'layout' }).then(function (data) {
			S.rooms = data.rooms || [];
			S.favorites = data.favorites || [];
			S.charts = data.charts || [];
			S.favById = {};
			S.favorites.forEach(function (t) { S.favById[String(t.id)] = t; });
			S.home = data.home || S.home;
			indexTiles();
			seedValues();
			buildShell();
			paintNav();
			paintHead();
			applyWallMode();
			paintSections();
			schedule();
			poll();
		}).catch(function (e) {
			root.innerHTML = '<div class="hb-empty hb-empty--error">Impossible de charger le tableau de bord.<br>' +
				esc(e.message) + '</div>';
		});

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) { poll(); }
		});

		setInterval(function () {
			var c = document.getElementById('hb-clock');
			if (!c) { return; }
			var now = new Date();
			c.textContent = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
			var d = document.getElementById('hb-date');
			if (d) { d.textContent = now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }); }
			var sum = document.getElementById('hb-summary');
			if (sum) { sum.textContent = summary(); }
		}, 20000);

		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			S.installEvent = e;
			if (headEl) { paintHead(); }
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
