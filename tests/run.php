<?php
/* Tests Homeboard.
 *
 *   php tests/run.php --pure   → logique pure, sans Jeedom (utilisable en CI)
 *   php tests/run.php          → tout, à exécuter sur l'hôte Jeedom
 *
 * Le second niveau crée puis supprime ses propres lignes (pièce masquée,
 * tuiles, scénario) : il ne modifie aucune donnée existante.
 */

$pure = in_array('--pure', $argv, true);
$failed = 0;
$passed = 0;

function check($_name, $_cond, $_detail = '') {
	global $failed, $passed;
	if ($_cond) {
		$passed++;
		echo "  ok   $_name\n";
		return true;
	}
	$failed++;
	echo "  FAIL $_name" . ($_detail !== '' ? ' — ' . $_detail : '') . "\n";
	return false;
}

function throwsSomething($_fn) {
	try {
		$_fn();
	} catch (Exception $e) {
		return true;
	} catch (Throwable $e) {
		return true;
	}
	return false;
}

function containsTile($_tiles, $_id) {
	foreach ($_tiles as $t) {
		if ((int) $t['id'] === (int) $_id) {
			return true;
		}
	}
	return false;
}

echo "Homeboard — tests\n";
require_once dirname(__FILE__) . '/../core/class/homeboard.pure.php';

echo "\n[1] Typage des tuiles\n";
check('type inconnu ramené à auto', homeboardPure::validKind('light; alert(1)') === 'auto');
check('type en majuscules refusé', homeboardPure::validKind('LIGHT') === 'auto');
check('type valide conservé', homeboardPure::validKind('thermostat') === 'thermostat');
check('type vide ramené à auto', homeboardPure::validKind('') === 'auto');
check('générique lumière', homeboardPure::kindFromGeneric('LIGHT_STATE') === 'light');
check('générique luminosité', homeboardPure::kindFromGeneric('BRIGHTNESS') === 'light');
check('générique thermostat', homeboardPure::kindFromGeneric('THERMOSTAT_TEMPERATURE') === 'thermostat');
check('générique volet', homeboardPure::kindFromGeneric('FLAP_STATE') === 'shutter');
check('générique caméra', homeboardPure::kindFromGeneric('CAMERA_URL') === 'camera');
check('générique mode → interrupteur', homeboardPure::kindFromGeneric('MODE_SET_STATE') === 'switch');
check('binaire sans générique → interrupteur', homeboardPure::kindFromGeneric('', 'binary') === 'switch');
check('numérique sans générique → mesure', homeboardPure::kindFromGeneric('', 'numeric') === 'sensor');

echo "\n[2] Icônes et libellés\n";
check('icône par défaut d une lumière', homeboardPure::defaultIcon('light') === 'light');
check('icône inconnue → sensor', homeboardPure::defaultIcon('zzz') === 'sensor');
check('libellé borné à 191', mb_strlen(homeboardPure::normalizeLabel(str_repeat('x', 400))) === 191);
check('libellé débarrassé des espaces', homeboardPure::normalizeLabel("  Salon  ") === 'Salon');

echo "\n[3] Fraîcheur : quelles mesures peuvent être périmées\n";
check('température périodique', homeboardPure::isPeriodic('TEMPERATURE') === true);
check('humidité périodique', homeboardPure::isPeriodic('HUMIDITY') === true);
check('puissance non périodique', homeboardPure::isPeriodic('POWER') === false);
check('état de lumière non périodique', homeboardPure::isPeriodic('LIGHT_STATE') === false);
check('unité °C sans générique → périodique', homeboardPure::isPeriodic('', '°C') === true);
check('unité W sans générique → non périodique', homeboardPure::isPeriodic('', 'W') === false);

echo "\n[4] Comparaison d'origine (CSRF)\n";
check('origine identique', homeboardPure::originMatches('http', 'jeedom.local', null, 'http://jeedom.local') === true);
check('port 80 explicite équivalent', homeboardPure::originMatches('http', 'jeedom.local', null, 'http://jeedom.local:80') === true);
check('port 443 explicite équivalent', homeboardPure::originMatches('https', 'jeedom.local', 443, 'https://jeedom.local:443') === true);
check('hôte différent refusé', homeboardPure::originMatches('http', 'jeedom.local', null, 'http://evil.example') === false);
check('schéma différent refusé', homeboardPure::originMatches('http', 'jeedom.local', null, 'https://jeedom.local') === false);
check('port non standard refusé', homeboardPure::originMatches('http', 'jeedom.local', null, 'http://jeedom.local:8080') === false);
check('port non standard accepté quand il correspond', homeboardPure::originMatches('http', 'jeedom.local', 8080, 'http://jeedom.local:8080') === true);
check('origine vide refusée', homeboardPure::originMatches('http', 'jeedom.local', null, '') === false);
check('origine illisible refusée', homeboardPure::originMatches('http', 'jeedom.local', null, 'pas-une-url') === false);
check('casse ignorée', homeboardPure::originMatches('HTTP', 'JEEDOM.local', null, 'http://jeedom.LOCAL') === true);

echo "\n[5] Pas des curseurs\n";
check('valeur alignée sur le pas', homeboardPure::clampStep(37.42, 5, 255, 5) === 35.0, (string) homeboardPure::clampStep(37.42, 5, 255, 5));
check('valeur sous le minimum remontée', homeboardPure::clampStep(1, 20, 30, 1) === 20.0);
check('valeur au-dessus du maximum abaissée', homeboardPure::clampStep(150, 0, 100, 1) === 100.0, (string) homeboardPure::clampStep(150, 0, 100, 1));
check('valeur déjà sur un pas inchangée', homeboardPure::clampStep(99, 0, 100, 1) === 99.0);
check('pas de 0,5 respecté', homeboardPure::clampStep(19.3, 5, 30, 0.5) === 19.5, (string) homeboardPure::clampStep(19.3, 5, 30, 0.5));
check('sans pas : simple bornage', homeboardPure::clampStep(42, '', '', '') === 42.0);
check('valeur non numérique refusée', homeboardPure::clampStep('abc', 0, 100, 1) === null);

if ($pure) {
	echo "\n$passed test(s) passé(s), $failed échec(s) — mode pur\n";
	exit($failed === 0 ? 0 : 1);
}

/* ------------------------------------------------------------------ */
/* Niveau Jeedom                                                       */
/* ------------------------------------------------------------------ */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../core/class/homeboard.class.php';

/* Les contrôles de droits passent par la session Jeedom : sans session, tout
   est refusé et les tests ne diraient rien. On ouvre donc celle d'un
   administrateur, comme le ferait un navigateur. */
if (session_status() !== PHP_SESSION_ACTIVE) {
	@session_start();
}
if (!isset($_SESSION['user']) || !is_object($_SESSION['user'])) {
	$admin = user::byLogin('admin');
	if (!is_object($admin)) {
		echo "  FAIL impossible d'ouvrir une session administrateur (utilisateur 'admin' absent)\n";
		exit(1);
	}
	$_SESSION['user'] = $admin;
}

/* Nettoyage de sécurité : si le script s'arrête au milieu (exception, arrêt
   manuel), les lignes ZZ créées par les tests ne doivent pas rester. */
register_shutdown_function(function () {
	try {
		foreach ((array) DB::Prepare("SELECT id FROM homeboard_tile WHERE label LIKE 'ZZ %'", array(), DB::FETCH_TYPE_ALL) as $r) {
			DB::Prepare('DELETE FROM homeboard_tile WHERE id = ?', array((int) $r['id']));
		}
		foreach ((array) DB::Prepare("SELECT id FROM homeboard_room WHERE name LIKE 'ZZ %'", array(), DB::FETCH_TYPE_ALL) as $r) {
			DB::Prepare('DELETE FROM homeboard_room WHERE id = ?', array((int) $r['id']));
		}
		foreach ((array) scenario::all() as $s) {
			if (strpos((string) $s->getName(), 'ZZ Homeboard') === 0) { $s->remove(); }
		}
	} catch (Throwable $e) {
		echo "  ...  nettoyage partiel : " . $e->getMessage() . "\n";
	}
});

echo "\n[6] Cohérence classe / logique pure\n";
check('liste des types identique', homeboard::KINDS === homeboardPure::KINDS);
check('validKind délégué', homeboard::validKind('light; x') === homeboardPure::validKind('light; x'));
check('kindFromGeneric délégué', homeboard::kindFromGeneric('FLAP_STATE') === homeboardPure::kindFromGeneric('FLAP_STATE'));

echo "\n[7] Autorisations\n";
$allowed = homeboard::allowedActions();
check('des commandes sont autorisées', count($allowed) > 0, 'liste vide');
$visible = array();
foreach (homeboard::rooms(false) as $r) { $visible[(int) $r['id']] = true; }
$onlyVisible = true;
foreach (homeboard::accessibleTiles(true) as $t) {
	if ((int) $t['room_id'] <= 0 || !isset($visible[(int) $t['room_id']])) { $onlyVisible = false; }
}
check('accessibleTiles ne renvoie que des pièces visibles', $onlyVisible);

$stranger = null;
foreach (DB::Prepare("SELECT id FROM cmd WHERE type = 'action' AND subType = 'other' LIMIT 200",
	array(), DB::FETCH_TYPE_ALL) as $r) {
	if (!isset($allowed[(int) $r['id']])) { $stranger = (int) $r['id']; break; }
}
if ($stranger !== null) {
	check('exec refuse une commande hors tuiles', throwsSomething(function () use ($stranger) {
		homeboard::exec($stranger, '');
	}));
} else {
	echo "  ...  aucune commande d'action hors tuiles sur cette installation\n";
}

echo "\n[8] Pièce masquée\n";
$roomId = homeboard::saveRoom(array('name' => 'ZZ test masqué', 'icon' => 'home', 'pos' => 999, 'isVisible' => 0));
$cmdRow = DB::Prepare('SELECT cmd_id FROM homeboard_tile WHERE kind <> ? AND cmd_id > 0 LIMIT 1',
	array('scene'), DB::FETCH_TYPE_ROW);
if (!is_array($cmdRow)) {
	echo "  ...  aucune tuile existante pour ce test\n";
	DB::Prepare('DELETE FROM homeboard_room WHERE id = ?', array($roomId));
} else {
	$tileId = homeboard::saveTile(array(
		'id' => 0, 'room_id' => $roomId, 'cmd_id' => (int) $cmdRow['cmd_id'], 'kind' => 'switch',
		'label' => 'ZZ tuile masquée', 'icon' => 'power', 'size' => 'normal', 'isFavorite' => 0, 'pos' => 999,
	));
	check('pièce masquée : tuile exclue', containsTile(homeboard::accessibleTiles(true), $tileId) === false);
	DB::Prepare('UPDATE homeboard_room SET isVisible = 1 WHERE id = ?', array($roomId));
	check('pièce visible : tuile incluse', containsTile(homeboard::accessibleTiles(true), $tileId) === true);

	echo "\n[9] Scénarios\n";
	$sc = new scenario();
	$sc->setName('ZZ Homeboard test');
	$sc->setIsActive(1);
	$sc->setMode('provoke');
	$sc->save();
	$scId = (int) $sc->getId();
	check('scénario non tuilé : lancement refusé', throwsSomething(function () use ($scId) {
		homeboard::runScenario($scId);
	}));
	check('scénario non tuilé : état non retourné', homeboard::scenarioStates(array($scId)) === array());
	$scTile = homeboard::saveTile(array(
		'id' => 0, 'room_id' => $roomId, 'scenario_id' => $scId, 'kind' => 'scene',
		'label' => 'ZZ scène', 'icon' => 'play', 'size' => 'normal', 'isFavorite' => 0, 'pos' => 999,
	));
	homeboard::accessibleTiles(true);
	$ran = false;
	try {
		$res = homeboard::runScenario($scId);
		$ran = !empty($res['launched']);
	} catch (Exception $e) {
		$ran = false;
	}
	check('scénario tuilé : lancement accepté', $ran === true);
	check('scénario tuilé : état retourné', isset(homeboard::scenarioStates(array($scId))[$scId]));

	echo "\n[10] Enregistrement en masse\n";
	$res = homeboard::saveTilesBulk(json_encode(array(
		array('id' => $tileId, 'kind' => 'button'),
		array('id' => $tileId, 'label' => 'ZZ tuile masquée'),
	)));
	check('ligne incohérente refusée, ligne valide acceptée',
		(int) $res['rejected'] === 1 && (int) $res['updated'] === 1, json_encode($res));
	$row = DB::Prepare('SELECT kind, cmd_id FROM homeboard_tile WHERE id = ?', array($tileId), DB::FETCH_TYPE_ROW);
	check('la tuile refusée garde type et commande', $row['kind'] === 'switch' && (int) $row['cmd_id'] === (int) $cmdRow['cmd_id'], json_encode($row));

	echo "\n[11] Pièces\n";
	$pid = homeboard::saveRoom(array('name' => str_repeat('P', 300), 'icon' => 'evil', 'pos' => 998, 'isVisible' => 1));
	$row = DB::Prepare('SELECT name, icon FROM homeboard_room WHERE id = ?', array($pid), DB::FETCH_TYPE_ROW);
	check('nom borné à 191 caractères', mb_strlen($row['name']) === 191, (string) mb_strlen($row['name']));
	check('icône hors liste remplacée', $row['icon'] === 'home', (string) $row['icon']);

	DB::Prepare('DELETE FROM homeboard_tile WHERE id IN (?, ?)', array($tileId, $scTile));
	DB::Prepare('DELETE FROM homeboard_room WHERE id IN (?, ?)', array($roomId, $pid));
	$sc = scenario::byId($scId);
	if (is_object($sc)) { $sc->remove(); }
}

echo "\n$passed test(s) passé(s), $failed échec(s)\n";
exit($failed === 0 ? 0 : 1);
