<?php
/* This file is part of Jeedom. */

/* Homeboard — configuration du plugin.
 *
 * Ne reste ici que ce qui est commun à tous les tableaux : l'identité de la
 * maison, le rythme de rafraîchissement et les pièces (objets partagés par
 * tous les tableaux). Les tableaux eux-mêmes sont des ÉQUIPEMENTS Jeedom :
 * leur nom, leur objet, leur état, leur voile et leurs tuiles se règlent sur
 * la page du plugin (Gestion → Mes tableaux de bord).
 */

if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

$homeName = config::byKey('homeName', 'homeboard', 'Maison');
$showClock = (int) config::byKey('showClock', 'homeboard', 1);
$refresh = (int) config::byKey('refresh', 'homeboard', 3);
$icons = homeboard::iconList();
$boardCount = count(homeboard::boards());
$dashUrl = 'index.php?v=d&p=homeboard&m=homeboard';
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
				</div>
			</div>
		</fieldset>

		<fieldset>
			<legend><i class="fas fa-tachometer-alt"></i> {{Tableaux de bord}}</legend>
			<p class="help-block">
				{{Chaque tableau de bord est un équipement Jeedom : un pour l'écran mural du salon, un pour le
				téléphone, un pour la technique. Son nom, son objet parent, son état actif, son mode mur et ses
				tuiles se règlent sur sa propre page.}}
			</p>
			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-6">
					<a class="btn btn-primary" href="<?php echo $dashUrl; ?>">
						<i class="fas fa-th-list"></i> {{Gérer les tableaux de bord}}
					</a>
					<span class="help-block">
						<?php echo $boardCount > 1
							? $boardCount . ' {{tableaux créés}}'
							: ($boardCount === 1 ? '{{1 tableau créé}}' : '{{Aucun tableau pour le moment}}'); ?>
					</span>
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

	</form>
</div>

<script>
(function () {
	'use strict';

	var API = 'plugins/homeboard/core/ajax/homeboard.ajax.php';
	var ICONS = <?php echo json_encode($icons); ?>;
	var HB = { rooms: [] };

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
	}

	function load() {
		return call('adminData').then(function (d) {
			HB.rooms = d.rooms || [];
			renderRooms();
		}).catch(ko);
	}

	document.getElementById('hb-save-settings').addEventListener('click', function () {
		call('saveSettings', {
			homeName: document.getElementById('hb-home-name').value,
			showClock: document.getElementById('hb-show-clock').checked ? 1 : 0,
			refresh: document.getElementById('hb-refresh').value
		}).then(function () { ok('{{Réglages enregistrés}}'); }).catch(ko);
	});

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


	load();
})();
</script>
