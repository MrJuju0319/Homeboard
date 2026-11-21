<?php
/* This file is part of Jeedom. */

/* Homeboard — liste des tableaux de bord.
 *
 * Un tableau de bord EST un équipement Jeedom (type homeboard) : il a un nom,
 * un objet parent, un état actif/visible, ses droits, et il se crée, se
 * duplique et se supprime avec les boutons standards de Jeedom. Ses tuiles et
 * son comportement de veille lui appartiennent.
 *
 * La vue plein écran reste servie par la même page quand une adresse de
 * tableau est demandée : index.php?v=d&p=homeboard&m=homeboard&board=<id|slug>.
 */

if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

if ((string) init('board') !== '' || (string) init('b') !== '') {
	require dirname(__FILE__) . '/homeboard_live.php';
	return;
}

$plugin = plugin::byId('homeboard');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

/* Tableau affiché dans le formulaire : ?id=<équipement>, sinon le premier. */
$hbEq = null;
if ((string) init('id') !== '') {
	$candidate = eqLogic::byId((int) init('id'));
	if (is_object($candidate) && $candidate->getEqType_name() === 'homeboard') {
		$hbEq = $candidate;
	}
}
if (!is_object($hbEq) && count($eqLogics)) {
	$hbEq = $eqLogics[0];
}
$hbEqId = is_object($hbEq) ? (int) $hbEq->getId() : 0;
if ($hbEqId > 0) {
	homeboard::useBoard($hbEqId);
}

$hbConf = is_object($hbEq) ? $hbEq->getConfiguration() : array();
$hbConf = is_array($hbConf) ? $hbConf : array();
$hbSlug = isset($hbConf['slug']) ? (string) $hbConf['slug'] : '';
$wallMode = isset($hbConf['wallMode']) ? (int) $hbConf['wallMode'] : 0;
$wallDelay = isset($hbConf['wallDelay']) ? max(1, min(60, (int) $hbConf['wallDelay'])) : 2;
$wallOled = isset($hbConf['wallOled']) ? (int) $hbConf['wallOled'] : 0;
$wallWidgetsRaw = isset($hbConf['wallWidgets']) ? (string) $hbConf['wallWidgets'] : '[]';
if ($wallWidgetsRaw === '') {
	$wallWidgetsRaw = '[]';
}

$pwaBase = rtrim((string) network::getNetworkAccess('internal'), '/') . '/plugins/homeboard/pwa/index.php';
$pwaUrl = $pwaBase . ($hbSlug !== '' ? '?b=' . urlencode($hbSlug) : '');
$hbRooms = homeboard::rooms(true);
$hbIcons = homeboard::iconList();
?>
<?php include_file('desktop', 'homeboard', 'css', homeboard::PLUGIN_ID); ?>

<div class="row row-overflow hb-admin">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Ajouter}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
		</div>

		<legend><i class="fas fa-tachometer-alt"></i> {{Mes tableaux de bord}}</legend>
		<?php if (!count($eqLogics)) { ?>
			<br>
			<div class="text-center" style="font-size:1.2em;font-weight:bold;">
				{{Aucun tableau de bord : clique sur « Ajouter » pour en créer un}}
			</div>
		<?php } else { ?>
			<div class="eqLogicThumbnailContainer">
				<?php
				foreach ($eqLogics as $eq) {
					$c = $eq->getConfiguration();
					$c = is_array($c) ? $c : array();
					$opacity = ($eq->getIsEnable()) ? '' : 'disableCard';
					$n = count(homeboard::tiles((int) $eq->getId()));
					$slug = isset($c['slug']) ? (string) $c['slug'] : '';
					echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . (int) $eq->getId() . '">';
					echo '<i class="fas fa-tachometer-alt hb-board-icon"></i>';
					echo '<br>';
					echo '<span class="name">' . htmlspecialchars($eq->getName()) . '</span>';
					echo '<span class="hb-board-sub">' . $n . ' {{tuile' . ($n > 1 ? 's' : '') . '}}</span>';
					echo '<span class="hiddenAsCard displayTableRight hidden">';
					if ($eq->getObject_id() > 0) {
						echo '<span class="label label-default">' . htmlspecialchars((string) jeeObject::byId($eq->getObject_id())->getName()) . '</span> ';
					}
					if ($slug !== '') {
						echo '<span class="label label-info">' . $slug . '</span>';
					}
					echo '</span>';
					echo '</div>';
				}
				?>
			</div>
		<?php } ?>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure">
					<i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy">
					<i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save">
					<i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove">
					<i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>

		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation">
				<a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay">
					<i class="fas fa-arrow-circle-left"></i>
				</a>
			</li>
			<li role="presentation" class="active">
				<a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab">
					<i class="fas fa-tachometer-alt"></i> {{Équipement}}
				</a>
			</li>
			<li role="presentation">
				<a href="#hb-tuiles-tab" aria-controls="profile" role="tab" data-toggle="tab">
					<i class="fas fa-th"></i> {{Tuiles}}
				</a>
			</li>
			<li role="presentation">
				<a href="#hb-scenes-tab" aria-controls="messages" role="tab" data-toggle="tab">
					<i class="fas fa-film"></i> {{Scènes}}
				</a>
			</li>
			<li role="presentation">
				<a href="#hb-import-tab" aria-controls="messages" role="tab" data-toggle="tab">
					<i class="fas fa-magic"></i> {{Import}}
				</a>
			</li>
		</ul>

		<div class="tab-content">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<form class="form-horizontal">
					<fieldset>
						<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Nom du tableau}}</label>
							<div class="col-sm-4">
								<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
								<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Mur du salon}}">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Objet parent}}</label>
							<div class="col-sm-4">
								<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
									<option value="">{{Aucun}}</option>
									<?php
									foreach (jeeObject::all() as $object) {
										echo '<option value="' . (int) $object->getId() . '">' . $object->getName() . '</option>';
									}
									?>
								</select>
								<span class="help-block">{{Rattache le tableau à une pièce Jeedom, comme n'importe quel équipement}}</span>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Actif}}</label>
							<div class="col-sm-4">
								<label class="checkbox-inline">
									<input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activé}}
								</label>
								<label class="checkbox-inline">
									<input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}
								</label>
								<span class="help-block">{{Un tableau désactivé n'est plus servi à son adresse}}</span>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Commentaire}}</label>
							<div class="col-sm-4">
								<textarea class="eqLogicAttr form-control" data-l1key="comment" rows="2"></textarea>
							</div>
						</div>
					</fieldset>

					<fieldset>
						<legend><i class="fas fa-mobile-alt"></i> {{Adresse du tableau}}</legend>
						<p class="help-block">
							{{C'est cette adresse qu'on installe sur l'écran concerné (téléphone, tablette murale).
							Elle reste valable même si le tableau est renommé.}}
						</p>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Application (PWA)}}</label>
							<div class="col-sm-8">
								<span class="hb-url"><?php echo htmlspecialchars($pwaUrl); ?></span>
								<?php if ($hbEqId > 0) { ?>
									<a class="btn btn-sm btn-default" href="<?php echo htmlspecialchars($pwaUrl); ?>" target="_blank" rel="noopener">
										<i class="fas fa-external-link-alt"></i> {{Ouvrir}}
									</a>
								<?php } ?>
								<span class="help-block">
									{{Sans paramètre, cette adresse sert le premier tableau créé. L'identifiant du tableau
									fonctionne aussi :}} <code>?b=<?php echo $hbEqId > 0 ? (int) $hbEqId : '<id>'; ?></code>
								</span>
							</div>
						</div>
					</fieldset>

					<fieldset>
						<legend><i class="fas fa-moon"></i> {{Mode mur}}</legend>
						<p class="help-block">
							{{Pour une tablette fixée au mur : après un délai sans contact, l'écran affiche un voile noir avec
							une grande horloge et les valeurs choisies, puis se réveille au premier toucher. Ce réglage
							appartient à ce tableau : le téléphone n'est pas concerné.}}
						</p>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Activer}}</label>
							<div class="col-sm-6">
								<input type="checkbox" id="hb-wall-mode" class="eqLogicAttr"
								       data-l1key="configuration" data-l2key="wallMode" <?php echo $wallMode ? 'checked' : ''; ?>>
								<span class="help-block">
									{{Voile de veille, réveil au toucher, et demande au navigateur de garder l'écran allumé (HTTPS requis pour cette dernière partie).}}
								</span>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Veille après}}</label>
							<div class="col-sm-3">
								<div class="input-group">
									<input type="number" class="eqLogicAttr form-control" id="hb-wall-delay" min="1" max="60"
									       data-l1key="configuration" data-l2key="wallDelay" value="<?php echo (int) $wallDelay; ?>">
									<span class="input-group-addon">{{minutes}}</span>
								</div>
								<span class="help-block">{{Délai sans contact avant le voile (1 à 60 minutes)}}</span>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Écran OLED}}</label>
							<div class="col-sm-6">
								<input type="checkbox" id="hb-wall-oled" class="eqLogicAttr"
								       data-l1key="configuration" data-l2key="wallOled" <?php echo $wallOled ? 'checked' : ''; ?>>
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
								<input type="hidden" class="eqLogicAttr" id="hb-wall-widgets-json"
								       data-l1key="configuration" data-l2key="wallWidgets"
								       value="<?php echo htmlspecialchars($wallWidgetsRaw); ?>">
								<span class="help-block">
									{{Quatre valeurs au maximum, affichées sous l'heure pendant la veille. Le libellé se
									modifie sur place ; la croix retire la valeur. Enregistre avec « Sauvegarder ».}}
								</span>
							</div>
						</div>
					</fieldset>
				</form>
			</div>

			<div role="tabpanel" class="tab-pane" id="hb-tuiles-tab">
				<form class="form-horizontal">
					<fieldset>
						<legend><i class="fas fa-th"></i> {{Tuiles de ce tableau}}</legend>
						<p class="help-block">
							{{Une tuile est pilotée par une commande Jeedom. Le plugin retrouve automatiquement les
							commandes associées de l'équipement (on/off, variation, consigne, modes…) : il suffit de
							choisir la commande d'état principale. Les pièces sont communes à tous les tableaux.}}
						</p>
						<div id="hb-tiles-locked" class="alert alert-info" style="display:none">
							{{Enregistre d'abord le tableau (bouton « Sauvegarder ») : les tuiles ont besoin d'un identifiant.}}
						</div>
						<div id="hb-tiles-body">
							<div class="well">
								<div class="row">
									<div class="col-sm-3">
										<label>{{Pièce}}</label>
										<select class="form-control" id="hb-tile-room">
											<?php
											foreach ($hbRooms as $r) {
												echo '<option value="' . (int) $r['id'] . '">' . htmlspecialchars($r['name']) . '</option>';
											}
											?>
										</select>
									</div>
									<div class="col-sm-5">
										<label>{{Commande Jeedom}}</label>
										<input type="text" class="form-control" id="hb-cmd-search" placeholder="{{Rechercher une commande…}}" autocomplete="off">
										<div id="hb-cmd-results" class="hb-search-results" style="display:none"></div>
										<div id="hb-cmd-picked" class="hb-picked" style="display:none"></div>
									</div>
									<div class="col-sm-4">
										<label>{{Libellé}}</label>
										<input type="text" class="form-control" id="hb-tile-label" placeholder="{{Nom affiché}}">
									</div>
								</div>
								<div class="row" style="margin-top:8px">
									<div class="col-sm-3">
										<label>{{Type}}</label>
										<select class="form-control" id="hb-tile-kind">
											<option value="auto">Automatique</option>
											<option value="light">Lumière</option>
											<option value="thermostat">Thermostat</option>
											<option value="sensor">Sonde</option>
											<option value="switch">Prise</option>
											<option value="shutter">Volet</option>
											<option value="camera">Caméra</option>
											<option value="button">Bouton d'action</option>
										</select>
									</div>
									<div class="col-sm-3">
										<label>{{Icône}}</label>
										<select class="form-control" id="hb-tile-icon">
											<option value="">Automatique</option>
											<?php foreach ($hbIcons as $ic) { echo '<option value="' . $ic . '">' . $ic . '</option>'; } ?>
										</select>
									</div>
									<div class="col-sm-3">
										<label>{{Taille}}</label>
										<select class="form-control" id="hb-tile-size">
											<option value="normal">Normale</option>
											<option value="wide">Large</option>
											<option value="tall">Haute</option>
										</select>
									</div>
									<div class="col-sm-3">
										<label>&nbsp;</label>
										<button type="button" class="btn btn-primary form-control" id="hb-add-tile">
											<i class="fas fa-plus"></i> {{Ajouter la tuile}}
										</button>
									</div>
								</div>
							</div>

							<table class="table table-condensed table-bordered" id="hb-tiles">
								<thead>
									<tr>
										<th style="width:34px">#</th>
										<th style="width:130px">{{Pièce}}</th>
										<th>{{Commande}}</th>
										<th style="width:150px">{{Libellé}}</th>
										<th style="width:120px">{{Type}}</th>
										<th style="width:110px">{{Icône}}</th>
										<th style="width:90px">{{Taille}}</th>
										<th style="width:60px">{{Favori}}</th>
										<th style="width:60px">{{Ordre}}</th>
										<th style="width:40px"></th>
									</tr>
								</thead>
								<tbody>
									<tr><td colspan="10" class="text-center text-muted">{{Chargement…}}</td></tr>
								</tbody>
							</table>

							<div class="text-right">
								<button type="button" class="btn btn-success" id="hb-save-tiles">
									<i class="fas fa-check-circle"></i> {{Enregistrer les tuiles}}
								</button>
							</div>
						</div>
					</fieldset>
				</form>
			</div>

			<div role="tabpanel" class="tab-pane" id="hb-scenes-tab">
				<form class="form-horizontal">
					<fieldset>
						<legend><i class="fas fa-film"></i> {{Scènes}}</legend>
						<p class="help-block">
							{{Ajoute un scénario Jeedom comme tuile de ce tableau : un appui déclenche le scénario et la
							tuile affiche son état (en cours, terminé). Les scénarios désactivés ne sont pas proposés.}}
						</p>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Pièce d'accueil}}</label>
							<div class="col-sm-4">
								<select class="form-control" id="hb-scene-room">
									<?php
									foreach ($hbRooms as $r) {
										echo '<option value="' . (int) $r['id'] . '">' . htmlspecialchars($r['name']) . '</option>';
									}
									?>
								</select>
								<span class="help-block">{{Pièce où sont rangées les tuiles ajoutées depuis cet onglet}}</span>
							</div>
						</div>
						<table class="table table-condensed table-bordered" id="hb-scenes">
							<thead>
								<tr>
									<th>{{Scénario}}</th>
									<th style="width:130px">{{État}}</th>
									<th style="width:110px">{{Dans le tableau}}</th>
									<th style="width:110px"></th>
								</tr>
							</thead>
							<tbody>
								<tr><td colspan="4" class="text-center text-muted">{{Chargement…}}</td></tr>
							</tbody>
						</table>
					</fieldset>
				</form>
			</div>

			<div role="tabpanel" class="tab-pane" id="hb-import-tab">
				<form class="form-horizontal">
					<fieldset>
						<legend><i class="fas fa-magic"></i> {{Import automatique}}</legend>
						<p class="help-block">
							{{Le plugin parcourt vos équipements et propose une tuile par appareil, avec le type déjà deviné
							(thermostat, lumière, prise, sonde…). Décoche ce que tu ne veux pas, corrige le type ou le
							libellé au besoin, puis importe : les pièces manquantes sont créées automatiquement.}}
						</p>
						<div class="row">
							<div class="col-sm-4">
								<button type="button" class="btn btn-primary" id="hb-scan">
									<i class="fas fa-search"></i> {{Analyser l'installation}}
								</button>
							</div>
							<div class="col-sm-3">
								<select class="form-control" id="hb-filter-room">
									<option value="*">{{Toutes les pièces}}</option>
									<?php
									foreach ($hbRooms as $r) {
										echo '<option value="' . htmlspecialchars($r['name']) . '">' . htmlspecialchars($r['name']) . '</option>';
									}
									?>
								</select>
							</div>
							<div class="col-sm-5">
								<label class="checkbox-inline">
									<input type="checkbox" id="hb-hide-imported" checked> {{Masquer les commandes déjà tuilées}}
								</label>
							</div>
						</div>
						<div id="hb-scan-wrap" style="display:none;margin-top:10px">
							<table class="table table-condensed table-bordered" id="hb-scan-table">
								<thead>
									<tr>
										<th style="width:36px"><input type="checkbox" id="hb-scan-all" checked></th>
										<th>{{Équipement}}</th>
										<th>{{Commande}}</th>
										<th style="width:150px">{{Pièce}}</th>
										<th style="width:150px">{{Libellé}}</th>
										<th style="width:140px">{{Type}}</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
							<div class="text-right">
								<span id="hb-scan-count" class="text-muted"></span>
								<button type="button" class="btn btn-success" id="hb-do-import">
									<i class="fas fa-download"></i> {{Importer}}
								</button>
							</div>
						</div>
					</fieldset>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
(function () {
	'use strict';

	var API = 'plugins/homeboard/core/ajax/homeboard.ajax.php';
	var ICONS = <?php echo json_encode($hbIcons); ?>;
	var KIND_LABELS = {
		auto: 'Automatique', light: 'Lumière', thermostat: 'Thermostat', sensor: 'Sonde',
		switch: 'Prise', shutter: 'Volet', camera: 'Caméra', button: 'Bouton', scene: 'Scène'
	};

	/* eqId : tableau en cours d'édition. 0 tant que l'équipement n'est pas
	   enregistré — les tuiles n'ont pas de support avant. */
	var HB = {
		rooms: <?php echo json_encode(array_map(function ($r) {
			return array('id' => (int) $r['id'], 'name' => (string) $r['name']);
		}, $hbRooms)); ?>,
		tiles: [], scenes: [], picked: null, timer: null,
		eqId: <?php echo (int) $hbEqId; ?>
	};

	function esc(s) {
		return String(s === null || s === undefined ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	function call(action, data) {
		if (!HB.eqId) {
			return Promise.reject(new Error('{{Enregistre d\'abord le tableau de bord}}'));
		}
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('b', HB.eqId);
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

	function renderTiles() {
		var tb = document.querySelector('#hb-tiles tbody');
		if (!tb) { return; }
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
				'<td>' + esc(s.state || '—') + '</td>' +
				'<td class="text-center">' + (used[Number(s.id)] ? '<i class="fas fa-check text-success"></i>' : '—') + '</td>' +
				'<td class="text-center"><button type="button" class="btn btn-xs btn-primary hb-scene-add"' +
				(active && !used[Number(s.id)] ? '' : ' disabled') + '>{{Ajouter}}</button></td>' +
				'</tr>';
		});
		tb.innerHTML = html || '<tr><td colspan="4" class="text-center text-muted">{{Aucun scénario}}</td></tr>';
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.hb-scene-add') : null;
		if (!b) { return; }
		var tr = b.closest('tr[data-scenario]');
		if (!tr) { return; }
		call('saveTile', {
			id: 0,
			room_id: document.getElementById('hb-scene-room').value,
			scenario_id: tr.getAttribute('data-scenario'),
			kind: 'scene',
			label: '',
			icon: 'play',
			size: 'normal',
			isFavorite: 0,
			pos: HB.tiles.length
		}).then(function () {
			ok('{{Scène ajoutée au tableau}}');
			return loadTiles();
		}).catch(ko);
	});

	/* ---------------- Mode mur (widgets) ---------------- */

	var WALL = { widgets: [] };

	function syncWallJson() {
		var inp = document.getElementById('hb-wall-widgets-json');
		if (inp) {
			inp.value = JSON.stringify(WALL.widgets.map(function (w) {
				return { cmd: Number(w.cmd), label: String(w.label || '') };
			}));
		}
	}

	function renderWallWidgets() {
		var tb = document.getElementById('hb-wall-widgets');
		if (!tb) { return; }
		if (!WALL.widgets.length) {
			tb.innerHTML = '<tr><td class="text-center text-muted" colspan="3">{{Aucun widget — le voile n\'affichera que l\'heure}}</td></tr>';
			syncWallJson();
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
		syncWallJson();
	}

	function initWallWidgets(raw) {
		var list = [];
		try {
			list = JSON.parse(raw || '[]');
		} catch (e) {
			list = [];
		}
		WALL.widgets = (Array.isArray(list) ? list : []).map(function (w) {
			return { cmd: Number(w && w.cmd ? w.cmd : w), label: String((w && w.label) || '') };
		}).filter(function (w) { return w.cmd > 0; }).slice(0, 4);
		renderWallWidgets();
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
		if (WALL.widgets[i]) {
			WALL.widgets[i].label = e.target.value;
			syncWallJson();
		}
	});

	/* ---------------- Recherche de commande ---------------- */

	var searchInput = document.getElementById('hb-cmd-search');
	var resultsEl = document.getElementById('hb-cmd-results');
	var pickedEl = document.getElementById('hb-cmd-picked');

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			clearTimeout(HB.timer);
			var q = searchInput.value.trim();
			if (q.length < 2) { resultsEl.style.display = 'none'; return; }
			HB.timer = setTimeout(function () {
				call('searchCmd', { q: q, limit: 40 }).then(function (rows) {
					if (!rows || !rows.length) {
						resultsEl.innerHTML = '<div class="hb-search-item text-muted">{{Aucun résultat}}</div>';
						resultsEl.style.display = '';
						return;
					}
					var html = '';
					rows.forEach(function (r) {
						html += '<div class="hb-search-item" data-id="' + r.id + '">' +
							'<strong>' + esc(r.eq) + '</strong> — ' + esc(r.name) +
							' <small>(' + esc(r.type) + ', ' + esc(r.generic_type || '—') + ')</small></div>';
					});
					resultsEl.innerHTML = html;
					resultsEl.style.display = '';
				}).catch(ko);
			}, 220);
		});

		resultsEl.addEventListener('click', function (e) {
			var item = e.target.closest('.hb-search-item[data-id]');
			if (!item) { return; }
			HB.picked = { id: Number(item.getAttribute('data-id')), label: item.textContent.trim() };
			pickedEl.innerHTML = '<i class="fas fa-check-circle text-success"></i> ' + esc(HB.picked.label) +
				' <i class="fas fa-times cursor hb-unpick" title="{{Retirer}}"></i>';
			pickedEl.style.display = '';
			resultsEl.style.display = 'none';
		});

		pickedEl.addEventListener('click', function (e) {
			if (!e.target.closest('.hb-unpick')) { return; }
			HB.picked = null;
			pickedEl.style.display = 'none';
			pickedEl.innerHTML = '';
		});
	}

	/* ---------------- Tuiles ---------------- */

	var addTileBtn = document.getElementById('hb-add-tile');
	if (addTileBtn) {
		addTileBtn.addEventListener('click', function () {
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
				isFavorite: 0,
				pos: HB.tiles.length
			}).then(function () {
				HB.picked = null;
				pickedEl.style.display = 'none';
				pickedEl.innerHTML = '';
				document.getElementById('hb-tile-label').value = '';
				return loadTiles();
			}).catch(ko);
		});
	}

	var saveTilesBtn = document.getElementById('hb-save-tiles');
	if (saveTilesBtn) {
		saveTilesBtn.addEventListener('click', function () {
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
			call('saveTiles', { rows: JSON.stringify(rows) }).then(function (res) {
				if (res.rejected) {
					jeedomUtils.showAlert({
						message: res.updated + ' {{tuile(s) enregistrée(s),}} ' + res.rejected + ' {{refusée(s)}}' +
							(res.errors && res.errors.length ? '<br><small>' + esc(res.errors.join(' · ')) + '</small>' : ''),
						level: 'warning'
					});
				} else {
					ok(res.updated + ' {{tuile(s) enregistrée(s)}}');
				}
				return loadTiles();
			}).catch(ko);
		});
	}

	document.addEventListener('click', function (e) {
		var del = e.target.closest ? e.target.closest('.hb-del-tile') : null;
		if (!del) { return; }
		var tr = del.closest('tr[data-id]');
		if (!tr) { return; }
		call('deleteTile', { id: tr.getAttribute('data-id') }).then(function () {
			return loadTiles();
		}).catch(ko);
	});

	/* ---------------- Import automatique ---------------- */

	var SCAN = [];

	function updateScanCount() {
		var n = document.querySelectorAll('#hb-scan-table tbody .hb-scan-x:checked').length;
		var el = document.getElementById('hb-scan-count');
		if (el) {
			el.textContent = n + (n > 1 ? ' tuiles sélectionnées' : ' tuile sélectionnée');
		}
	}

	function renderScan() {
		var room = document.getElementById('hb-filter-room').value;
		var hide = document.getElementById('hb-hide-imported').checked;
		var html = '';
		(SCAN || []).forEach(function (r, i) {
			if (room !== '*' && (r.room || r.object) !== room) { return; }
			if (hide && r.imported) { return; }
			html += '<tr data-i="' + i + '">' +
				'<td class="text-center"><input type="checkbox" class="hb-scan-x" data-i="' + i + '"' +
				(r.imported ? ' disabled' : ' checked') + '></td>' +
				'<td>' + esc(r.eq) + '</td>' +
				'<td>' + esc(r.name) + '<br><small class="text-muted">' + esc(r.generic_type || '—') + '</small></td>' +
				'<td>' + esc(r.room || r.object || '—') + '</td>' +
				'<td><input type="text" class="form-control input-sm hb-scan-label" value="' + esc(r.label) + '"></td>' +
				'<td><select class="form-control input-sm hb-scan-kind">' + kindOptions(r.kind) + '</select></td>' +
				'</tr>';
		});
		document.querySelector('#hb-scan-table tbody').innerHTML = html ||
			'<tr><td colspan="6" class="text-center text-muted">{{Aucune commande à importer}}</td></tr>';
		updateScanCount();
	}

	var scanBtn = document.getElementById('hb-scan');
	if (scanBtn) {
		scanBtn.addEventListener('click', function () {
			scanBtn.disabled = true;
			call('scan').then(function (rows) {
				SCAN = rows || [];
				document.getElementById('hb-scan-wrap').style.display = '';
				renderScan();
			}).catch(ko).then(function () { scanBtn.disabled = false; });
		});
	}
	var filterRoom = document.getElementById('hb-filter-room');
	if (filterRoom) { filterRoom.addEventListener('change', renderScan); }
	var hideImported = document.getElementById('hb-hide-imported');
	if (hideImported) { hideImported.addEventListener('change', renderScan); }

	document.addEventListener('change', function (e) {
		if (e.target.id === 'hb-scan-all') {
			document.querySelectorAll('#hb-scan-table tbody .hb-scan-x:not([disabled])').forEach(function (cb) {
				cb.checked = e.target.checked;
			});
			updateScanCount();
			return;
		}
		if (e.target.classList && e.target.classList.contains('hb-scan-x')) { updateScanCount(); }
	});

	var importBtn = document.getElementById('hb-do-import');
	if (importBtn) {
		importBtn.addEventListener('click', function () {
			var rows = [];
			document.querySelectorAll('#hb-scan-table tbody tr[data-i]').forEach(function (tr) {
				var cb = tr.querySelector('.hb-scan-x');
				if (!cb || !cb.checked || cb.disabled) { return; }
				var base = SCAN[Number(tr.getAttribute('data-i'))] || {};
				rows.push({
					cmd_id: base.cmd_id,
					label: tr.querySelector('.hb-scan-label').value,
					kind: tr.querySelector('.hb-scan-kind').value,
					room: tr.cells[3].textContent.trim(),
					object: base.object,
					room_icon: base.room_icon,
					favorite: base.favorite ? 1 : 0
				});
			});
			if (!rows.length) { ko({ message: '{{Aucune tuile sélectionnée}}' }); return; }
			call('import', { rows: JSON.stringify(rows) }).then(function (res) {
				ok(res.created + ' {{tuile(s) importée(s)}}' + (res.skipped ? ', ' + res.skipped + ' {{déjà présente(s)}}' : ''));
				document.getElementById('hb-scan-wrap').style.display = 'none';
				SCAN = [];
				return loadTiles();
			}).catch(ko);
		});
	}

	/* ---------------- Chargement ---------------- */

	function loadTiles() {
		return call('adminData').then(function (d) {
			HB.tiles = d.tiles || [];
			HB.rooms = (d.rooms || []).map(function (r) { return { id: r.id, name: r.name }; });
			renderTiles();
			return call('scenarios').then(function (list) {
				HB.scenes = list || [];
				renderScenes();
			}).catch(function () { renderScenes(); });
		}).catch(function (e) {
			var tb = document.querySelector('#hb-tiles tbody');
			if (tb) { tb.innerHTML = '<tr><td colspan="10" class="text-center text-danger">' + esc(e.message) + '</td></tr>'; }
		});
	}

	function applyEqId(id, wallRaw) {
		HB.eqId = Number(id) || 0;
		document.getElementById('hb-tiles-body').style.display = HB.eqId ? '' : 'none';
		document.getElementById('hb-tiles-locked').style.display = HB.eqId ? 'none' : '';
		initWallWidgets(wallRaw);
		if (HB.eqId) {
			loadTiles();
		} else {
			HB.tiles = [];
			renderTiles();
		}
	}

	/* Le core affiche l'équipement cliqué : on recharge les tuiles de CE
	   tableau. Le délai laisse le core remplir le formulaire (voile compris). */
	document.addEventListener('click', function (e) {
		var card = e.target.closest ? e.target.closest('.eqLogicDisplayCard[data-eqLogic_id]') : null;
		if (!card) { return; }
		var id = Number(card.getAttribute('data-eqLogic_id'));
		setTimeout(function () {
			var json = document.getElementById('hb-wall-widgets-json');
			applyEqId(id, json ? json.value : '[]');
		}, 260);
	});

	/* Après une sauvegarde, le core recharge la page avec ?id= : eqId vient du
	   serveur, il suffit de charger les tuiles. */
	if (HB.eqId) {
		document.getElementById('hb-tiles-body').style.display = '';
		document.getElementById('hb-tiles-locked').style.display = 'none';
		initWallWidgets(<?php echo json_encode($wallWidgetsRaw); ?>);
		loadTiles();
	} else {
		document.getElementById('hb-tiles-body').style.display = 'none';
		document.getElementById('hb-tiles-locked').style.display = '';
		renderTiles();
		renderWallWidgets();
	}
})();
</script>

<?php include_file('core', 'plugin.template', 'js'); ?>
