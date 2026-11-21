<?php
/* This file is part of Jeedom. */

if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

$icons = homeboard::iconList();
$homeName = config::byKey('homeName', 'homeboard', 'Maison');
$showClock = (int) config::byKey('showClock', 'homeboard', 1);
$refresh = (int) config::byKey('refresh', 'homeboard', 3);
$wallMode = (int) config::byKey('wallMode', 'homeboard', 0);
$wallDelay = max(1, min(60, (int) config::byKey('wallDelay', 'homeboard', 2)));
$wallOled = (int) config::byKey('wallOled', 'homeboard', 0);

$pwaUrl = rtrim((string) network::getNetworkAccess('internal'), '/') . '/plugins/homeboard/pwa/index.php';
$dashUrl = 'index.php?v=d&p=homeboard&m=homeboard';

function hb_icon_options($icons, $selected = '') {
	$out = '';
	foreach ($icons as $ic) {
		$out .= '<option value="' . $ic . '"' . ($ic === $selected ? ' selected' : '') . '>' . $ic . '</option>';
	}
	return $out;
}
?>
<div class="hb-admin">

	<form class="form-horizontal">

		<fieldset>
			<legend><i class="fas fa-home"></i> {{Général}}</legend>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Nom de la maison}}</label>
				<div class="col-sm-4">
					<input type="text" class="form-control" id="hb-home-name" value="<?php echo htmlspecialchars($homeName); ?>">
					<span class="help-block">{{Affiché en haut du tableau de bord}}</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Horloge}}</label>
				<div class="col-sm-4">
					<input type="checkbox" id="hb-show-clock" <?php echo $showClock ? 'checked' : ''; ?>>
					<span class="help-block">{{Afficher l'heure et la date en haut de l'écran}}</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Rafraîchissement}}</label>
				<div class="col-sm-3">
					<div class="input-group">
						<input type="number" class="form-control" id="hb-refresh" min="2" max="30" value="<?php echo $refresh; ?>">
						<span class="input-group-addon">{{secondes}}</span>
					</div>
					<span class="help-block">{{Intervalle de mise à jour des états (2 à 30 s)}}</span>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-6">
					<button type="button" class="btn btn-success" id="hb-save-settings">
						<i class="fas fa-check-circle"></i> {{Enregistrer}}
					</button>
					<a class="btn btn-default" href="<?php echo $dashUrl; ?>" target="_blank">
						<i class="fas fa-external-link-alt"></i> {{Ouvrir le tableau de bord}}
					</a>
				</div>
			</div>
		</fieldset>

		<fieldset>
			<legend><i class="fas fa-moon"></i> {{Mode mur}}</legend>
			<p class="help-block">
				{{Pour une tablette fixée au mur : après un délai sans contact, l'écran affiche un voile noir avec
				une grande horloge et les valeurs choisies, puis se réveille au premier toucher.}}
			</p>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Activer}}</label>
				<div class="col-sm-6">
					<input type="checkbox" id="hb-wall-mode" <?php echo $wallMode ? 'checked' : ''; ?>>
					<span class="help-block">
						{{Voile de veille, réveil au toucher, et demande au navigateur de garder l'écran allumé (HTTPS requis pour cette dernière partie).}}
					</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Veille après}}</label>
				<div class="col-sm-3">
					<div class="input-group">
						<input type="number" class="form-control" id="hb-wall-delay" min="1" max="60" value="<?php echo (int) $wallDelay; ?>">
						<span class="input-group-addon">{{minutes}}</span>
					</div>
					<span class="help-block">{{Délai sans contact avant le voile (1 à 60 minutes)}}</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Écran OLED}}</label>
				<div class="col-sm-6">
					<input type="checkbox" id="hb-wall-oled" <?php echo $wallOled ? 'checked' : ''; ?>>
					<span class="help-block">
						{{Anti-marquage renforcé pour une dalle OLED : luminosité abaissée, décalage plus large
						(jusqu'à 12 % de l'écran) toutes les 45 s, et variation lente de luminosité pour qu'aucun
						pixel ne reste à la même intensité. À laisser décoché sur un écran LCD.}}
					</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Widgets}}</label>
				<div class="col-sm-6">
					<input type="text" class="form-control" id="hb-wall-search" placeholder="{{Ajouter une valeur : consommation, température, météo…}}" autocomplete="off">
					<div id="hb-wall-results" class="hb-search-results" style="display:none;max-height:220px;overflow:auto"></div>
					<table class="table table-condensed table-bordered" id="hb-wall-widgets" style="margin-top:8px"></table>
					<span class="help-block">
						{{Quatre valeurs au maximum, affichées sous l'heure pendant la veille. Le libellé se modifie
						sur place ; la croix retire la valeur.}}
					</span>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-6">
					<button type="button" class="btn btn-success" id="hb-save-wall">
						<i class="fas fa-check-circle"></i> {{Enregistrer le mode mur}}
					</button>
				</div>
			</div>
		</fieldset>

		<fieldset>
			<legend><i class="fas fa-magic"></i> {{Import automatique}}</legend>
			<p class="help-block">
				{{Le plugin parcourt vos équipements et propose une tuile par appareil, avec le type déjà deviné
				(thermostat, lumière, prise, sonde…). Décochez ce que vous ne voulez pas, corrigez le type ou le
				libellé au besoin, puis importez : les pièces manquantes sont créées automatiquement.}}
			</p>
			<div class="row">
				<div class="col-sm-4">
					<button type="button" class="btn btn-primary" id="hb-scan">
						<i class="fas fa-search"></i> {{Analyser mes équipements}}
					</button>
				</div>
				<div class="col-sm-8" id="hb-scan-toolbar" style="display:none;text-align:right">
					<select class="form-control" id="hb-filter-room" style="display:inline-block;width:190px;vertical-align:middle"></select>
					<label style="margin:0 10px;font-weight:normal;vertical-align:middle">
						<input type="checkbox" id="hb-hide-imported" checked> {{Masquer les commandes déjà importées}}
					</label>
					<button type="button" class="btn btn-default btn-sm" id="hb-sel-all">{{Tout cocher}}</button>
					<button type="button" class="btn btn-default btn-sm" id="hb-sel-none">{{Tout décocher}}</button>
				</div>
			</div>
			<div id="hb-scan-empty" style="display:none;margin-top:12px" class="alert alert-info"></div>
			<div id="hb-scan-results" style="display:none;margin-top:12px">
				<table class="table table-condensed table-bordered" id="hb-scan-table">
					<thead>
						<tr>
							<th style="width:36px" class="text-center"><i class="fas fa-check"></i></th>
							<th style="width:130px">{{Pièce}}</th>
							<th>{{Équipement}}</th>
							<th>{{Commande}}</th>
							<th style="width:150px">{{Type de tuile}}</th>
							<th style="width:250px">{{Libellé}}</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
				<div class="row">
					<div class="col-sm-12">
						<span class="help-block pull-left" id="hb-scan-count"></span>
						<span class="pull-left" id="hb-import-status" style="margin-left:12px;font-weight:bold;color:#3a9d3a"></span>
						<button type="button" class="btn btn-success pull-right" id="hb-import">
							<i class="fas fa-file-import"></i> {{Importer la sélection}}
						</button>
					</div>
				</div>
			</div>
		</fieldset>

		<fieldset>
			<legend><i class="fas fa-door-open"></i> {{Pièces}}</legend>
			<p class="help-block">
				{{Les pièces deviennent les onglets du tableau de bord. L'ordre est donné par la colonne « Ordre » (croissant).}}
			</p>
			<table class="table table-condensed table-bordered" id="hb-rooms">
				<thead>
					<tr>
						<th style="width:50px">#</th>
						<th>{{Nom}}</th>
						<th style="width:150px">{{Icône}}</th>
						<th style="width:90px">{{Ordre}}</th>
						<th style="width:90px" class="text-center">{{Visible}}</th>
						<th style="width:50px" class="text-center">{{Action}}</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
			<div class="row">
				<div class="col-sm-12">
					<button type="button" class="btn btn-default" id="hb-add-room">
						<i class="fas fa-plus-circle"></i> {{Ajouter une pièce}}
					</button>
					<button type="button" class="btn btn-success pull-right" id="hb-save-rooms">
						<i class="fas fa-check-circle"></i> {{Enregistrer les pièces}}
					</button>
				</div>
			</div>
		</fieldset>

		<fieldset>
			<legend><i class="fas fa-th"></i> {{Tuiles}}</legend>
			<p class="help-block">
				{{Une tuile est pilotée par une commande Jeedom. Le plugin retrouve automatiquement les commandes associées de l'équipement (on/off, variation, consigne, modes…) : il suffit de choisir la commande d'état principale.}}
			</p>

			<div class="well">
				<div class="row">
					<div class="col-sm-3">
						<label>{{Pièce}}</label>
						<select class="form-control" id="hb-tile-room"></select>
					</div>
					<div class="col-sm-5">
						<label>{{Commande Jeedom}}</label>
						<input type="text" class="form-control" id="hb-cmd-search" placeholder="{{Rechercher un équipement ou une commande…}}" autocomplete="off">
						<div id="hb-cmd-results" class="hb-search-results" style="display:none"></div>
						<div id="hb-cmd-picked" style="display:none;margin-top:6px"></div>
					</div>
					<div class="col-sm-4">
						<label>{{Libellé de la tuile}}</label>
						<input type="text" class="form-control" id="hb-tile-label" placeholder="{{(nom de l'équipement par défaut)}}">
					</div>
				</div>
				<div class="row" style="margin-top:10px">
					<div class="col-sm-2">
						<label>{{Type}}</label>
						<select class="form-control" id="hb-tile-kind">
							<option value="auto">{{Automatique}}</option>
							<option value="light">{{Lumière}}</option>
							<option value="thermostat">{{Thermostat}}</option>
							<option value="sensor">{{Sonde}}</option>
							<option value="switch">{{Prise / Interrupteur}}</option>
							<option value="shutter">{{Volet}}</option>
							<option value="camera">{{Caméra}}</option>
						</select>
					</div>
					<div class="col-sm-2">
						<label>{{Icône}}</label>
						<select class="form-control" id="hb-tile-icon">
							<?php echo hb_icon_options(array_merge(array(''), $icons)); ?>
						</select>
					</div>
					<div class="col-sm-2">
						<label>{{Taille}}</label>
						<select class="form-control" id="hb-tile-size">
							<option value="normal">{{Normale}}</option>
							<option value="wide">{{Large}}</option>
							<option value="tall">{{Haute}}</option>
						</select>
					</div>
					<div class="col-sm-2">
						<label>{{Favori}}</label>
						<div class="checkbox" style="margin-top:8px">
							<label><input type="checkbox" id="hb-tile-fav"> {{Épingler en haut}}</label>
						</div>
					</div>
					<div class="col-sm-4" style="text-align:right;padding-top:22px">
						<button type="button" class="btn btn-primary" id="hb-add-tile">
							<i class="fas fa-plus-circle"></i> {{Ajouter la tuile}}
						</button>
					</div>
				</div>
			</div>

			<table class="table table-condensed table-bordered" id="hb-tiles">
				<thead>
					<tr>
						<th style="width:44px">#</th>
						<th style="width:150px">{{Pièce}}</th>
						<th>{{Commande}}</th>
						<th style="width:170px">{{Libellé}}</th>
						<th style="width:130px">{{Type}}</th>
						<th style="width:120px">{{Icône}}</th>
						<th style="width:110px">{{Taille}}</th>
						<th style="width:70px" class="text-center">{{Favori}}</th>
						<th style="width:80px">{{Ordre}}</th>
						<th style="width:50px" class="text-center">{{Action}}</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
			<div class="row">
				<div class="col-sm-12">
					<button type="button" class="btn btn-success pull-right" id="hb-save-tiles">
						<i class="fas fa-check-circle"></i> {{Enregistrer les tuiles}}
					</button>
				</div>
			</div>
		</fieldset>

		<fieldset>
			<legend><i class="fas fa-film"></i> {{Scènes}}</legend>
			<p class="help-block">
				{{Ajoutez un scénario Jeedom comme tuile du tableau de bord : un appui déclenche le scénario et la
				tuile affiche son état (en cours, terminé). Les scénarios désactivés ne sont pas proposés.}}
			</p>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Pièce d'accueil}}</label>
				<div class="col-sm-4">
					<select class="form-control" id="hb-scene-room"></select>
					<span class="help-block">{{Pièce où sont rangées les tuiles ajoutées depuis cette liste}}</span>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12">
					<table class="table table-condensed table-bordered" id="hb-scenes">
						<thead>
							<tr>
								<th>{{Scénario}}</th>
								<th style="width:180px">{{Groupe}}</th>
								<th style="width:110px" class="text-center">{{État}}</th>
								<th style="width:180px" class="text-center">{{Tuile}}</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</fieldset>

		<fieldset>
			<legend><i class="fas fa-mobile-alt"></i> {{Application mobile (PWA)}}</legend>
			<p class="help-block">
				{{Cette page est une application web installable : ouvre-la dans le navigateur du téléphone puis « Ajouter à l'écran d'accueil ». Elle fonctionne alors en plein écran, sans barre d'adresse.}}
			</p>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Adresse de l'application}}</label>
				<div class="col-sm-9">
					<span class="hb-url"><?php echo htmlspecialchars($pwaUrl); ?></span>
					<span class="help-block">
						{{Pour la vraie installation (plein écran + cache), l'adresse doit être en HTTPS. Sur iOS et Android, l'ajout à l'écran d'accueil fonctionne aussi en HTTP mais sans mode hors ligne.}}
					</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Page Jeedom}}</label>
				<div class="col-sm-9">
					<a href="<?php echo $dashUrl; ?>" target="_blank"><?php echo $dashUrl; ?></a>
					<span class="help-block">{{Accessible depuis le menu Plugins → Homeboard (utilisateurs connectés).}}</span>
				</div>
			</div>
		</fieldset>

	</form>
</div>

<script>
(function () {
	'use strict';

	var API = 'plugins/homeboard/core/ajax/homeboard.ajax.php';
	var ICONS = <?php echo json_encode($icons); ?>;
	var KIND_LABELS = {
		auto: 'Automatique', light: 'Lumière', thermostat: 'Thermostat', sensor: 'Sonde',
		switch: 'Prise', shutter: 'Volet', camera: 'Caméra', button: 'Bouton', scene: 'Scène'
	};

	var HB = { rooms: [], tiles: [], picked: null, timer: null };

	function esc(s) {
		return String(s === null || s === undefined ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	function call(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (!j || j.state !== 'ok') { throw new Error((j && j.result) || 'Erreur inconnue'); }
				return j.result;
			});
	}

	function ok(msg) { jeedomUtils.showAlert({ message: msg, level: 'success' }); }
	/* Les messages d'erreur viennent du serveur (PHP, PDO, plugins tiers) et
	   sont rendus en HTML par la boîte d'alerte : on les échappe. */
	function ko(e) {
		var raw = (e && e.message) ? String(e.message) : String(e);
		var txt = raw.replace(/<[^>]*>/g, '').replace(/[&<>"]/g, function (c) {
			return c === '&' ? '&amp;' : (c === '<' ? '&lt;' : (c === '>' ? '&gt;' : '&quot;'));
		});
		jeedomUtils.showAlert({ message: txt, level: 'danger' });
	}

	function iconOptions(sel) {
		var out = '';
		ICONS.forEach(function (i) {
			out += '<option value="' + i + '"' + (i === sel ? ' selected' : '') + '>' + i + '</option>';
		});
		return out;
	}

	function roomOptions(sel) {
		var out = '<option value="0">—</option>';
		HB.rooms.forEach(function (r) {
			out += '<option value="' + r.id + '"' + (Number(sel) === Number(r.id) ? ' selected' : '') + '>' +
				esc(r.name) + '</option>';
		});
		return out;
	}

	function kindOptions(sel) {
		var out = '';
		Object.keys(KIND_LABELS).forEach(function (k) {
			out += '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + KIND_LABELS[k] + '</option>';
		});
		return out;
	}

	function sizeOptions(sel) {
		var map = { normal: 'Normale', wide: 'Large', tall: 'Haute' };
		var out = '';
		Object.keys(map).forEach(function (k) {
			out += '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + map[k] + '</option>';
		});
		return out;
	}

	function renderRooms() {
		var tb = document.querySelector('#hb-rooms tbody');
		var html = '';
		HB.rooms.forEach(function (r, i) {
			html += '<tr class="hb-admin__row" data-id="' + r.id + '">' +
				'<td>' + (i + 1) + '</td>' +
				'<td><input type="text" class="form-control hb-room-name" value="' + esc(r.name) + '"></td>' +
				'<td><select class="form-control hb-room-icon">' + iconOptions(r.icon) + '</select></td>' +
				'<td><input type="number" class="form-control hb-mini hb-room-pos" value="' + (r.pos || 0) + '"></td>' +
				'<td class="text-center"><input type="checkbox" class="hb-room-vis"' + (Number(r.isVisible) ? ' checked' : '') + '></td>' +
				'<td class="text-center"><i class="fas fa-trash cursor hb-del-room" title="{{Supprimer}}"></i></td>' +
				'</tr>';
		});
		tb.innerHTML = html || '<tr><td colspan="6" class="text-center text-muted">{{Aucune pièce}}</td></tr>';

		var sel = document.getElementById('hb-tile-room');
		sel.innerHTML = roomOptions(sel.value);
	}

	function renderTiles() {
		var tb = document.querySelector('#hb-tiles tbody');
		var html = '';
		HB.tiles.forEach(function (t, i) {
			html += '<tr class="hb-admin__row" data-id="' + t.id + '">' +
				'<td>' + (i + 1) + '</td>' +
				'<td><select class="form-control hb-t-room">' + roomOptions(t.room_id) + '</select></td>' +
				'<td><strong>' + esc(t.eq_name) + '</strong><br><small class="text-muted">' + esc(t.cmd_name) +
				' (' + esc(t.generic_type || '—') + ') — ' + esc(t.state) + ' ' + esc(t.unite) + '</small></td>' +
				'<td><input type="text" class="form-control hb-t-label" value="' + esc(t.label) + '"></td>' +
				'<td><select class="form-control hb-t-kind">' + kindOptions(t.kind) + '</select></td>' +
				'<td><select class="form-control hb-t-icon">' + iconOptions(t.icon) + '</select></td>' +
				'<td><select class="form-control hb-t-size">' + sizeOptions(t.size) + '</select></td>' +
				'<td class="text-center"><input type="checkbox" class="hb-t-fav"' + (Number(t.isFavorite) ? ' checked' : '') + '></td>' +
				'<td><input type="number" class="form-control hb-mini hb-t-pos" value="' + (t.pos || 0) + '"></td>' +
				'<td class="text-center"><i class="fas fa-trash cursor hb-del-tile" title="{{Supprimer}}"></i></td>' +
				'</tr>';
		});
		tb.innerHTML = html || '<tr><td colspan="10" class="text-center text-muted">{{Aucune tuile}}</td></tr>';
	}

	/* ---------------- Scènes ---------------- */

	function renderScenes() {
		var tb = document.querySelector('#hb-scenes tbody');
		if (!tb) { return; }
		var used = {};
		HB.tiles.forEach(function (t) {
			if (String(t.kind) === 'scene' && Number(t.scenario_id)) {
				used[Number(t.scenario_id)] = true;
			}
		});
		var html = '';
		(HB.scenes || []).forEach(function (s) {
			var active = Number(s.isActive) ? true : false;
			html += '<tr data-scenario="' + s.id + '">' +
				'<td><strong>' + esc(s.name) + '</strong>' +
				(active ? '' : ' <small class="text-muted">({{désactivé}})</small>') + '</td>' +
				'<td>' + esc(s.group || '—') + '</td>' +
				'<td class="text-center">' + esc(s.state || '—') + '</td>' +
				'<td class="text-center">' +
				(used[s.id]
					? '<span class="label label-success">{{Ajoutée}}</span>'
					: (active
						? '<button class="btn btn-default btn-xs hb-add-scene" data-id="' + s.id +
							'" data-name="' + esc(s.name) + '"><i class="fas fa-plus"></i> {{Ajouter}}</button>'
						: '<span class="text-muted">{{désactivé}}</span>')) +
				'</td></tr>';
		});
		tb.innerHTML = html || '<tr><td colspan="4" class="text-center text-muted">{{Aucun scénario}}</td></tr>';
	}

	function loadScenes() {
		return call('scenarios').then(function (list) {
			HB.scenes = list || [];
			var sel = document.getElementById('hb-scene-room');
			if (sel) {
				var keep = sel.value;
				var html = '<option value="0">{{Autres}}</option>';
				HB.rooms.forEach(function (r) {
					html += '<option value="' + r.id + '">' + esc(r.name) + '</option>';
				});
				sel.innerHTML = html;
				if (keep) { sel.value = keep; }
			}
			renderScenes();
		}).catch(ko);
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.hb-add-scene') : null;
		if (!b) { return; }
		var roomSel = document.getElementById('hb-scene-room');
		b.disabled = true;
		call('saveTile', {
			id: 0,
			room_id: roomSel ? roomSel.value : 0,
			cmd_id: 0,
			scenario_id: b.getAttribute('data-id'),
			label: b.getAttribute('data-name'),
			kind: 'scene',
			icon: 'scene',
			size: 'normal',
			isFavorite: 0,
			pos: HB.tiles.length
		}).then(load).then(loadScenes).then(function () {
			ok('{{Tuile de scénario ajoutée}}');
		}).catch(function (err) {
			b.disabled = false;
			ko(err);
		});
	});

	function load() {
		return call('adminData').then(function (d) {
			HB.rooms = d.rooms || [];
			HB.tiles = d.tiles || [];
			renderRooms();
			renderTiles();
			if (!WALL.loaded) {
				WALL.loaded = true;
				WALL.widgets = ((d.settings && d.settings.wallWidgets) || []).map(function (w) {
					return { cmd: Number(w.cmd), label: w.label || '' };
				});
				var del = document.getElementById('hb-wall-delay');
				if (del && d.settings && d.settings.wallDelay) { del.value = d.settings.wallDelay; }
				renderWallWidgets();
			}
		}).catch(ko);
	}

	/* ---------------- Réglages ---------------- */

	document.getElementById('hb-save-settings').addEventListener('click', function () {
		call('saveSettings', {
			homeName: document.getElementById('hb-home-name').value,
			showClock: document.getElementById('hb-show-clock').checked ? 1 : 0,
			wallMode: document.getElementById('hb-wall-mode').checked ? 1 : 0,
			refresh: document.getElementById('hb-refresh').value
		}).then(function () { ok('{{Réglages enregistrés}}'); }).catch(ko);
	});

	/* ---------------- Mode mur ---------------- */

	var WALL = { widgets: [], loaded: false };

	function renderWallWidgets() {
		var tb = document.getElementById('hb-wall-widgets');
		if (!tb) { return; }
		if (!WALL.widgets.length) {
			tb.innerHTML = '<tr><td class="text-center text-muted" colspan="3">{{Aucun widget — le voile n\'affichera que l\'heure}}</td></tr>';
			return;
		}
		var html = '';
		WALL.widgets.forEach(function (w, i) {
			html += '<tr data-i="' + i + '">' +
				'<td><input type="text" class="form-control input-sm hb-wall-label" value="' + esc(w.label) + '"></td>' +
				'<td class="text-center"><small class="text-muted">#' + w.cmd + '</small></td>' +
				'<td class="text-center"><i class="fas fa-times cursor hb-wall-del" title="{{Retirer}}"></i></td>' +
				'</tr>';
		});
		tb.innerHTML = html;
	}

	var wallSearch = document.getElementById('hb-wall-search');
	var wallResults = document.getElementById('hb-wall-results');
	var wallTimer = null;

	if (wallSearch) {
		wallSearch.addEventListener('input', function () {
			clearTimeout(wallTimer);
			var q = wallSearch.value.trim();
			wallTimer = setTimeout(function () {
				call('searchCmd', { q: q, limit: 20 }).then(function (rows) {
					var infos = (rows || []).filter(function (r) { return r.type === 'info'; });
					if (!infos.length) {
						wallResults.innerHTML = '<div class="hb-search-item text-muted">{{Aucun résultat}}</div>';
						wallResults.style.display = '';
						return;
					}
					var html = '';
					infos.forEach(function (r) {
						html += '<div class="hb-search-item" data-id="' + r.id +
							'" data-label="' + esc(r.eq + ' — ' + r.name) + '">' +
							'<strong>' + esc(r.eq) + '</strong> — ' + esc(r.name) +
							' <small>(' + esc(r.unite || '—') + ')</small></div>';
					});
					wallResults.innerHTML = html;
					wallResults.style.display = '';
				}).catch(ko);
			}, 260);
		});

		wallResults.addEventListener('click', function (e) {
			var item = e.target.closest('.hb-search-item[data-id]');
			if (!item) { return; }
			if (WALL.widgets.length >= 4) {
				jeedomUtils.showAlert({ message: '{{Quatre widgets au maximum}}', level: 'warning' });
				return;
			}
			var id = Number(item.getAttribute('data-id'));
			var dup = WALL.widgets.some(function (w) { return w.cmd === id; });
			if (!dup) {
				WALL.widgets.push({ cmd: id, label: item.getAttribute('data-label') });
				renderWallWidgets();
			}
			wallSearch.value = '';
			wallResults.style.display = 'none';
		});
	}

	document.addEventListener('click', function (e) {
		var del = e.target.closest ? e.target.closest('.hb-wall-del') : null;
		if (!del) { return; }
		var tr = del.closest('tr[data-i]');
		if (!tr) { return; }
		WALL.widgets.splice(Number(tr.getAttribute('data-i')), 1);
		renderWallWidgets();
	});

	document.addEventListener('input', function (e) {
		if (!e.target.classList || !e.target.classList.contains('hb-wall-label')) { return; }
		var tr = e.target.closest('tr[data-i]');
		if (!tr) { return; }
		var i = Number(tr.getAttribute('data-i'));
		if (WALL.widgets[i]) { WALL.widgets[i].label = e.target.value; }
	});

	var saveWallBtn = document.getElementById('hb-save-wall');
	if (saveWallBtn) {
		saveWallBtn.addEventListener('click', function () {
			call('saveWall', {
				wallMode: document.getElementById('hb-wall-mode').checked ? 1 : 0,
				wallOled: document.getElementById('hb-wall-oled').checked ? 1 : 0,
				wallDelay: document.getElementById('hb-wall-delay').value,
				wallWidgets: JSON.stringify(WALL.widgets)
			}).then(function (s) {
				WALL.widgets = (s.wallWidgets || []).map(function (w) {
					return { cmd: Number(w.cmd), label: w.label || '' };
				});
				renderWallWidgets();
				ok('{{Mode mur enregistré}}');
			}).catch(ko);
		});
	}

	/* ---------------- Pièces ---------------- */

	document.getElementById('hb-add-room').addEventListener('click', function () {
		call('saveRoom', { id: 0, name: '{{Nouvelle pièce}}', icon: 'home', pos: HB.rooms.length, isVisible: 1 })
			.then(load).then(function () { ok('{{Pièce ajoutée}}'); }).catch(ko);
	});

	document.getElementById('hb-save-rooms').addEventListener('click', function () {
		var rows = document.querySelectorAll('#hb-rooms tbody tr[data-id]');
		var chain = Promise.resolve();
		Array.prototype.forEach.call(rows, function (tr) {
			var id = tr.getAttribute('data-id');
			var data = {
				id: id,
				name: tr.querySelector('.hb-room-name').value,
				icon: tr.querySelector('.hb-room-icon').value,
				pos: tr.querySelector('.hb-room-pos').value,
				isVisible: tr.querySelector('.hb-room-vis').checked ? 1 : 0
			};
			chain = chain.then(function () { return call('saveRoom', data); });
		});
		chain.then(load).then(function () { ok('{{Pièces enregistrées}}'); }).catch(ko);
	});

	document.addEventListener('click', function (e) {
		var del = e.target.closest('.hb-del-room');
		if (del) {
			var tr = del.closest('tr[data-id]');
			if (!confirm('{{Supprimer cette pièce ? Les tuiles associées ne seront pas supprimées.}}')) { return; }
			call('deleteRoom', { id: tr.getAttribute('data-id') })
				.then(load).then(function () { ok('{{Pièce supprimée}}'); }).catch(ko);
		}
		var delTile = e.target.closest('.hb-del-tile');
		if (delTile) {
			var tr2 = delTile.closest('tr[data-id]');
			if (!confirm('{{Supprimer cette tuile ?}}')) { return; }
			call('deleteTile', { id: tr2.getAttribute('data-id') })
				.then(load).then(function () { ok('{{Tuile supprimée}}'); }).catch(ko);
		}
	});

	/* ---------------- Recherche de commande ---------------- */

	var searchInput = document.getElementById('hb-cmd-search');
	var resultsEl = document.getElementById('hb-cmd-results');
	var pickedEl = document.getElementById('hb-cmd-picked');

	searchInput.addEventListener('input', function () {
		clearTimeout(HB.timer);
		var q = searchInput.value.trim();
		if (q.length < 2) { resultsEl.style.display = 'none'; return; }
		HB.timer = setTimeout(function () {
			call('searchCmd', { q: q, limit: 40 }).then(function (rows) {
				if (!rows.length) {
					resultsEl.innerHTML = '<div class="hb-search-item text-muted">{{Aucun résultat}}</div>';
					resultsEl.style.display = '';
					return;
				}
				var html = '';
				rows.forEach(function (r) {
					html += '<div class="hb-search-item" data-id="' + r.id + '" data-kind="' + r.kind +
						'" data-type="' + esc(r.type) + '">' +
						'<strong>' + esc(r.eq) + '</strong> — ' + esc(r.name) +
						' <small>(' + esc(r.gt || '—') + ' · ' + esc(r.kind) +
						(r.type === 'action' ? ' · action' : '') + ')</small></div>';
				});
				resultsEl.innerHTML = html;
				resultsEl.style.display = '';
			}).catch(ko);
		}, 260);
	});

	resultsEl.addEventListener('click', function (e) {
		var item = e.target.closest('.hb-search-item[data-id]');
		if (!item) { return; }
		var txt = item.textContent;
		HB.picked = {
			id: item.getAttribute('data-id'),
			kind: item.getAttribute('data-kind'),
			type: item.getAttribute('data-type'),
			text: txt
		};
		pickedEl.innerHTML = '<span class="hb-picked"><i class="fas fa-check-circle" style="color:#3a3"></i> ' +
			esc(txt) + '<i class="fas fa-times hb-clear"></i></span>';
		pickedEl.style.display = '';
		resultsEl.style.display = 'none';
		if (!document.getElementById('hb-tile-label').value) {
			document.getElementById('hb-tile-label').value = txt.split('—')[0].trim();
		}
		var kindSel = document.getElementById('hb-tile-kind');
		/* Une commande d'action ne peut être qu'une tuile « bouton » : côté
		   serveur, une action comme pilote d'affichage exécuterait la commande
		   à chaque chargement du tableau de bord. */
		if (HB.picked.type === 'action') {
			kindSel.value = 'button';
		} else if (kindSel.value === 'auto' && HB.picked.kind) {
			kindSel.value = HB.picked.kind;
		}
	});

	pickedEl.addEventListener('click', function (e) {
		if (!e.target.closest('.hb-clear')) { return; }
		HB.picked = null;
		pickedEl.style.display = 'none';
		pickedEl.innerHTML = '';
	});

	/* ---------------- Tuiles ---------------- */

	document.getElementById('hb-add-tile').addEventListener('click', function () {
		if (!HB.picked) {
			jeedomUtils.showAlert({ message: '{{Choisis d\'abord une commande Jeedom}}', level: 'danger' });
			return;
		}
		call('saveTile', {
			id: 0,
			room_id: document.getElementById('hb-tile-room').value,
			cmd_id: HB.picked.id,
			label: document.getElementById('hb-tile-label').value,
			kind: document.getElementById('hb-tile-kind').value,
			icon: document.getElementById('hb-tile-icon').value,
			size: document.getElementById('hb-tile-size').value,
			isFavorite: document.getElementById('hb-tile-fav').checked ? 1 : 0,
			pos: HB.tiles.length
		}).then(function () {
			HB.picked = null;
			pickedEl.style.display = 'none';
			document.getElementById('hb-tile-label').value = '';
			searchInput.value = '';
			return load();
		}).then(function () { ok('{{Tuile ajoutée}}'); }).catch(ko);
	});

	document.getElementById('hb-save-tiles').addEventListener('click', function () {
		var rows = [];
		document.querySelectorAll('#hb-tiles tbody tr[data-id]').forEach(function (tr) {
			rows.push({
				id: tr.getAttribute('data-id'),
				room_id: tr.querySelector('.hb-t-room').value,
				label: tr.querySelector('.hb-t-label').value,
				kind: tr.querySelector('.hb-t-kind').value,
				icon: tr.querySelector('.hb-t-icon').value,
				size: tr.querySelector('.hb-t-size').value,
				isFavorite: tr.querySelector('.hb-t-fav').checked ? 1 : 0,
				pos: tr.querySelector('.hb-t-pos').value
			});
		});
		call('saveTiles', { rows: JSON.stringify(rows) })
			.then(function (r) {
				var res = (r && r.updated !== undefined) ? r : { updated: rows.length, rejected: 0, errors: [] };
				return load().then(function () {
					/* Un seul retour à l'utilisateur : vert si tout est passé,
					   orange si une partie a été refusée. Afficher « enregistré »
					   après un avertissement de refus était contradictoire. */
					if (res.rejected) {
						var detail = (res.errors || []).map(function (e) {
							return String(e).replace(/[<>&]/g, function (c) {
								return c === '<' ? '&lt;' : (c === '>' ? '&gt;' : '&amp;');
							});
						}).join('<br>');
						jeedomUtils.showAlert({
							message: '{{' + res.updated + ' tuile(s) enregistrée(s), ' + res.rejected + ' refusée(s)}}<br><small>' + detail + '</small>',
							level: 'warning'
						});
						return;
					}
					ok('{{Tuiles enregistrées}}');
				});
			})
			.catch(ko);
	});

	/* ---------------- Import automatique ---------------- */

	var SCAN = [];

	function updateScanCount() {
		var n = document.querySelectorAll('#hb-scan-table tbody .hb-scan-x:checked').length;
		document.getElementById('hb-scan-count').textContent = n +
			(n > 1 ? ' tuiles sélectionnées' : ' tuile sélectionnée');
	}

	function renderScan() {
		var room = document.getElementById('hb-filter-room').value;
		var hide = document.getElementById('hb-hide-imported').checked;
		var html = '';
		SCAN.forEach(function (r, i) {
			if (room !== '*' && (r.room || r.object) !== room) { return; }
			if (hide && r.already) { return; }
			html += '<tr data-i="' + i + '">' +
				'<td class="text-center"><input type="checkbox" class="hb-scan-x"' + (r.already ? '' : ' checked') + '></td>' +
				'<td>' + esc(r.room || r.object) +
				(r.room && r.room !== r.object ? '<br><small class="text-muted">' + esc(r.object) + '</small>' : '') + '</td>' +
				'<td><strong>' + esc(r.eq_name) + '</strong>' +
				(r.already ? ' <small class="text-muted">({{déjà importé}})</small>' : '') + '</td>' +
				'<td><small class="text-muted">' + esc(r.cmd_name) + ' · ' + esc(r.gt || '—') +
				(r.unit ? ' · ' + esc(r.unit) : '') + '</small></td>' +
				'<td><select class="form-control hb-scan-kind">' + kindOptions(r.kind) + '</select></td>' +
				'<td><input type="text" class="form-control hb-scan-label" value="' + esc(r.label) + '"></td>' +
				'</tr>';
		});
		document.querySelector('#hb-scan-table tbody').innerHTML = html;
		var any = document.querySelectorAll('#hb-scan-table tbody tr').length > 0;
		document.getElementById('hb-scan-results').style.display = any ? '' : 'none';
		var empty = document.getElementById('hb-scan-empty');
		empty.style.display = any ? 'none' : '';
		if (!any) { empty.textContent = '{{Aucun équipement à importer avec ces filtres.}}'; }
		updateScanCount();
	}

	document.getElementById('hb-scan').addEventListener('click', function () {
		var btn = this;
		btn.disabled = true;
		call('scan').then(function (rows) {
			SCAN = rows || [];
			if (!SCAN.length) {
				document.getElementById('hb-scan-empty').style.display = '';
				document.getElementById('hb-scan-empty').textContent =
					'{{Aucun équipement reconnu. Ajoutez vos tuiles à la main plus bas.}}';
				return;
			}
			var rooms = [];
			SCAN.forEach(function (r) {
				var n = r.room || r.object;
				if (rooms.indexOf(n) < 0) { rooms.push(n); }
			});
			rooms.sort();
			var sel = document.getElementById('hb-filter-room');
			sel.innerHTML = '<option value="*">{{Toutes les pièces}}</option>' +
				rooms.map(function (n) { return '<option value="' + esc(n) + '">' + esc(n) + '</option>'; }).join('');
			document.getElementById('hb-scan-toolbar').style.display = '';
			renderScan();
		}).catch(ko).then(function () { btn.disabled = false; });
	});

	document.getElementById('hb-filter-room').addEventListener('change', renderScan);
	document.getElementById('hb-hide-imported').addEventListener('change', renderScan);

	document.getElementById('hb-sel-all').addEventListener('click', function () {
		Array.prototype.forEach.call(document.querySelectorAll('#hb-scan-table tbody .hb-scan-x'),
			function (c) { c.checked = true; });
		updateScanCount();
	});

	document.getElementById('hb-sel-none').addEventListener('click', function () {
		Array.prototype.forEach.call(document.querySelectorAll('#hb-scan-table tbody .hb-scan-x'),
			function (c) { c.checked = false; });
		updateScanCount();
	});

	document.querySelector('#hb-scan-table tbody').addEventListener('change', function (e) {
		if (e.target.classList.contains('hb-scan-x')) { updateScanCount(); }
	});

	document.getElementById('hb-import').addEventListener('click', function () {
		var rows = [];
		Array.prototype.forEach.call(document.querySelectorAll('#hb-scan-table tbody tr'), function (tr) {
			if (!tr.querySelector('.hb-scan-x').checked) { return; }
			var r = SCAN[Number(tr.getAttribute('data-i'))];
			rows.push({
				cmd_id: r.cmd_id,
				object: r.object,
				room: r.room,
				room_icon: r.room_icon,
				label: tr.querySelector('.hb-scan-label').value,
				kind: tr.querySelector('.hb-scan-kind').value,
				icon: r.icon,
				favorite: 0
			});
		});
		if (!rows.length) {
			jeedomUtils.showAlert({ message: '{{Sélectionne au moins une tuile}}', level: 'danger' });
			return;
		}
		var btn = this;
		btn.disabled = true;
		call('import', { rows: JSON.stringify(rows) }).then(function (res) {
			document.getElementById('hb-import-status').textContent = '✓ ' + res.created +
				(res.created > 1 ? ' tuiles importées' : ' tuile importée') +
				(res.skipped ? ' · ' + res.skipped + ' déjà présente(s), ignorée(s)' : '');
			SCAN = [];
			document.getElementById('hb-scan-results').style.display = 'none';
			document.getElementById('hb-scan-toolbar').style.display = 'none';
			document.getElementById('hb-scan-empty').style.display = 'none';
			return load();
		}).catch(ko).then(function () { btn.disabled = false; });
	});

	load().then(loadScenes);
})();
</script>
