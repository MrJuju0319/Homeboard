<?php
/* Tests Homeboard.
 *
 *   php tests/run.php --pure   → logique pure, sans Jeedom (utilisable en CI)
 *   php tests/run.php          → tout, à exécuter sur l'hôte Jeedom
 *
 * Le second niveau crée ses propres lignes et ne supprime que celles-là, par
 * identifiant exact : aucune suppression par préfixe, donc aucune donnée
 * existante ne peut être touchée même si un test s'arrête au milieu.
 */

$pure = in_array('--pure', $argv, true);
$failed = 0;
$passed = 0;
$GLOBALS['hbTestIds'] = array('tile' => array(), 'room' => array(), 'scenario' => array(), 'board' => array(), 'user' => array());

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

/* Mémorise un identifiant créé par les tests, pour le supprimer à la fin. */
function track($_kind, $_id) {
	$GLOBALS['hbTestIds'][$_kind][] = (int) $_id;
	return (int) $_id;
}

/* Index par identifiant, pour tester l'appartenance sans parcourir à la main. */
function idSet($_items) {
	$out = array();
	foreach ((array) $_items as $it) {
		$out[(int) $it['id']] = true;
	}
	return $out;
}

function allLayoutTiles($_layout) {
	$out = array();
	foreach ((array) $_layout['rooms'] as $r) {
		foreach ($r['tiles'] as $t) {
			$out[] = $t;
		}
	}
	return $out;
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
check('maximum hors grille : dernier cran valide', homeboardPure::clampStep(10, 5, 10, 3) === 8.0, (string) homeboardPure::clampStep(10, 5, 10, 3));
check('maximum hors grille, demande au-delà', homeboardPure::clampStep(50, 5, 10, 3) === 8.0, (string) homeboardPure::clampStep(50, 5, 10, 3));
check('sans pas : simple bornage', homeboardPure::clampStep(42, '', '', '') === 42.0);
check('valeur non numérique refusée', homeboardPure::clampStep('abc', 0, 100, 1) === null);

echo "\n[5 bis] Borne haute alignée sur le pas\n";
check('min 5, max 10, pas 3 → 8', homeboardPure::alignMax(5, 10, 3) === 8.0, (string) homeboardPure::alignMax(5, 10, 3));
check('min 5, max 27, pas 0,5 → 27', homeboardPure::alignMax(5, 27, 0.5) === 27.0, (string) homeboardPure::alignMax(5, 27, 0.5));
check('min 0, max 100, pas 1 → 100', homeboardPure::alignMax(0, 100, 1) === 100.0);
check('min 10, max 25, pas 0,5 → 25', homeboardPure::alignMax(10, 25, 0.5) === 25.0);
check('sans pas : borne inchangée', homeboardPure::alignMax(0, 254, '') === 254.0);
check('max déjà aligné : inchangé', homeboardPure::alignMax(5, 8, 3) === 8.0);
check('max sous le minimum : renvoyé tel quel', homeboardPure::alignMax(10, 5, 2) === 5.0);

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

/* Nettoyage : uniquement les identifiants créés par ces tests, jamais une
   suppression par préfixe (une tuile réelle pourrait s'appeler « ZZ… »). */
register_shutdown_function(function () {
	$ids = $GLOBALS['hbTestIds'];
	try {
		foreach ($ids['tile'] as $id) {
			DB::Prepare('DELETE FROM homeboard_tile WHERE id = ?', array((int) $id));
		}
		foreach ($ids['room'] as $id) {
			DB::Prepare('DELETE FROM homeboard_room WHERE id = ?', array((int) $id));
		}
		foreach ($ids['scenario'] as $id) {
			$sc = scenario::byId((int) $id);
			if (is_object($sc)) { $sc->remove(); }
		}
		foreach ($ids['user'] as $id) {
			DB::Prepare('DELETE FROM user WHERE id = ?', array((int) $id));
		}
		foreach ($ids['board'] as $id) {
			$eq = eqLogic::byId((int) $id);
			if (is_object($eq) && $eq->getEqType_name() === 'homeboard') {
				DB::Prepare('DELETE FROM homeboard_tile WHERE eq_id = ?', array((int) $id));
				$eq->remove();
			}
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

echo "\n[8] Tableau de bord : une seule source de vérité\n";
$cmdRow = DB::Prepare('SELECT cmd_id FROM homeboard_tile WHERE kind <> ? AND cmd_id > 0 LIMIT 1',
	array('scene'), DB::FETCH_TYPE_ROW);
$roomId = track('room', homeboard::saveRoom(array('name' => 'ZZ test masqué', 'icon' => 'home', 'pos' => 999, 'isVisible' => 0)));

if (!is_array($cmdRow)) {
	echo "  ...  aucune tuile existante pour ce test\n";
} else {
	$tileId = track('tile', homeboard::saveTile(array(
		'id' => 0, 'room_id' => $roomId, 'cmd_id' => (int) $cmdRow['cmd_id'], 'kind' => 'switch',
		'label' => 'ZZ tuile masquée', 'icon' => 'power', 'size' => 'normal', 'isFavorite' => 1, 'pos' => 999,
	)));

	homeboard::accessibleTiles(true);
	$layout = homeboard::getLayout();
	check('pièce masquée : tuile absente du tableau de bord', !isset(idSet(allLayoutTiles($layout))[$tileId]));
	check('pièce masquée : tuile absente des favoris', !isset(idSet($layout['favorites'])[$tileId]));
	check('pièce masquée : tuile hors du périmètre des droits', containsTile(homeboard::accessibleTiles(true), $tileId) === false);
	$chartTiles = array();
	foreach ($layout['charts'] as $ch) { $chartTiles[(int) $ch['tile_id']] = true; }
	check('pièce masquée : tuile absente des graphiques', !isset($chartTiles[$tileId]));

	DB::Prepare('UPDATE homeboard_room SET isVisible = 1 WHERE id = ?', array($roomId));
	homeboard::accessibleTiles(true);
	$layout = homeboard::getLayout();
	check('pièce visible : tuile de retour dans le tableau de bord', isset(idSet(allLayoutTiles($layout))[$tileId]));
	check('pièce visible : tuile de retour dans les favoris', isset(idSet($layout['favorites'])[$tileId]));

	echo "\n[9] Tuile sans pièce\n";
	$orphanId = track('tile', homeboard::saveTile(array(
		'id' => 0, 'room_id' => 0, 'cmd_id' => (int) $cmdRow['cmd_id'], 'kind' => 'switch',
		'label' => 'ZZ tuile orpheline', 'icon' => 'power', 'size' => 'normal', 'isFavorite' => 1, 'pos' => 999,
	)));
	homeboard::accessibleTiles(true);
	$layout = homeboard::getLayout();
	check('tuile sans pièce : absente du tableau de bord', !isset(idSet(allLayoutTiles($layout))[$orphanId]));
	check('tuile sans pièce : absente des favoris', !isset(idSet($layout['favorites'])[$orphanId]));
	$fake = 0;
	foreach ($layout['rooms'] as $r) {
		if ((int) $r['id'] === 0 || $r['name'] === 'Autres') { $fake++; }
	}
	check('aucune pièce virtuelle « Autres »', $fake === 0, (string) $fake);

	/* Invariant central : tout ce qui est affiché doit être exécutable. */
	homeboard::accessibleTiles(true);
	$allowed = homeboard::allowedActions(true);
	$missing = array();
	foreach (allLayoutTiles($layout) as $t) {
		if ($t['kind'] === 'scene') { continue; }
		foreach (array('on', 'off', 'toggle', 'briSet', 'colorSet', 'setSetpoint', 'up', 'down', 'stop', 'slider') as $k) {
			if (!empty($t['caps'][$k]) && !isset($allowed[(int) $t['caps'][$k]])) {
				$missing[] = $t['label'] . '/' . $k;
			}
		}
		foreach ((array) (isset($t['caps']['modes']) ? $t['caps']['modes'] : array()) as $m) {
			if (!empty($m['id']) && !isset($allowed[(int) $m['id']])) {
				$missing[] = $t['label'] . '/mode';
			}
		}
	}
	check('tout ce qui est affiché est exécutable', empty($missing), implode(', ', array_slice($missing, 0, 5)));

	echo "\n[10] Scénarios\n";
	$sc = new scenario();
	$sc->setName('ZZ Homeboard test');
	$sc->setIsActive(1);
	$sc->setMode('provoke');
	$sc->save();
	$scId = track('scenario', $sc->getId());
	check('scénario non tuilé : lancement refusé', throwsSomething(function () use ($scId) {
		homeboard::runScenario($scId);
	}));
	check('scénario non tuilé : état non retourné', homeboard::scenarioStates(array($scId)) === array());
	$scTile = track('tile', homeboard::saveTile(array(
		'id' => 0, 'room_id' => $roomId, 'scenario_id' => $scId, 'kind' => 'scene',
		'label' => 'ZZ scène', 'icon' => 'play', 'size' => 'normal', 'isFavorite' => 0, 'pos' => 999,
	)));
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

	echo "\n[11] Enregistrement en masse\n";
	$res = homeboard::saveTilesBulk(json_encode(array(
		array('id' => $tileId, 'kind' => 'button'),
		array('id' => $tileId, 'label' => 'ZZ tuile masquée'),
	)));
	check('ligne incohérente refusée, ligne valide acceptée',
		(int) $res['rejected'] === 1 && (int) $res['updated'] === 1, json_encode($res));
	$row = DB::Prepare('SELECT kind, cmd_id FROM homeboard_tile WHERE id = ?', array($tileId), DB::FETCH_TYPE_ROW);
	check('la tuile refusée garde type et commande', $row['kind'] === 'switch' && (int) $row['cmd_id'] === (int) $cmdRow['cmd_id'], json_encode($row));

	echo "\n[12] Pièces\n";
	$pid = track('room', homeboard::saveRoom(array('name' => str_repeat('P', 300), 'icon' => 'evil', 'pos' => 998, 'isVisible' => 1)));
	$row = DB::Prepare('SELECT name, icon FROM homeboard_room WHERE id = ?', array($pid), DB::FETCH_TYPE_ROW);
	check('nom borné à 191 caractères', mb_strlen($row['name']) === 191, (string) mb_strlen($row['name']));
	check('icône hors liste remplacée', $row['icon'] === 'home', (string) $row['icon']);
}

echo "\n[13] Tableaux de bord\n";
$def = homeboard::defaultBoard();
$defId = is_array($def) ? (int) $def['id'] : 0;
check('un tableau par défaut existe', $defId > 0);
check('slug du tableau par défaut', is_array($def) && $def['slug'] !== '', is_array($def) ? (string) $def['slug'] : '');
check('slugify retire les accents', homeboard::slugify('Mur du Salon Étage') === 'mur-du-salon-etage', homeboard::slugify('Mur du Salon Étage'));

homeboard::useBoard($defId);
$beforeTiles = count(homeboard::tiles());
$b2 = track('board', homeboard::createBoard(array('name' => 'ZZ test board')));
check('tableau créé', $b2 > 0);
homeboard::useBoard($b2);
check('nouveau tableau vide', count(homeboard::tiles()) === 0, (string) count(homeboard::tiles()));

if (is_array($cmdRow)) {
	$t2 = track('tile', homeboard::saveTile(array(
		'id' => 0, 'room_id' => $roomId, 'cmd_id' => (int) $cmdRow['cmd_id'], 'kind' => 'switch',
		'label' => 'ZZ tuile tableau B', 'icon' => 'power', 'size' => 'normal', 'isFavorite' => 0, 'pos' => 1,
	)));
	check('tuile rangée dans son tableau', containsTile(homeboard::tiles(), $t2));

	homeboard::useBoard($defId);
	check('tuile d un autre tableau invisible', !containsTile(homeboard::tiles(), $t2));
	check('tableau par défaut inchangé', count(homeboard::tiles()) === $beforeTiles, count(homeboard::tiles()) . ' vs ' . $beforeTiles);
	$allowedA = homeboard::allowedActions(true);
	$sample = count($allowedA) ? (int) array_key_first($allowedA) : 0;

	homeboard::useBoard($b2);
	$allowedB = homeboard::allowedActions(true);
	check('périmètre des actions limité au tableau', $sample > 0 && !isset($allowedB[$sample]), (string) $sample);

	/* Depuis le tableau de test, une tuile du tableau par défaut ne doit être
	   ni modifiable ni supprimable : sinon un identifiant suffirait à sortir de
	   son périmètre. */
	$foreign = DB::Prepare('SELECT id FROM homeboard_tile WHERE eq_id = ? LIMIT 1', array($defId), DB::FETCH_TYPE_ROW);
	check('modification croisée refusée', throwsSomething(function () use ($foreign, $b2) {
		homeboard::useBoard($b2);
		homeboard::saveTile(array('id' => (int) $foreign['id'], 'label' => 'détournement'));
	}));
	check('suppression croisée refusée', throwsSomething(function () use ($foreign) {
		homeboard::deleteTile((int) $foreign['id']);
	}));
	check('la tuile visée est toujours là', is_array(DB::Prepare('SELECT id FROM homeboard_tile WHERE id = ?', array((int) $foreign['id']), DB::FETCH_TYPE_ROW)));
}

homeboard::useBoard($b2);
homeboard::deleteBoard($b2);
check('les tuiles du tableau supprimé disparaissent', count(DB::Prepare('SELECT id FROM homeboard_tile WHERE eq_id = ?', array($b2), DB::FETCH_TYPE_ALL)) === 0);
check('suppression d un tableau inexistant refusée', throwsSomething(function () {
	homeboard::deleteBoard(999999);
}));
/* Le garde-fou du dernier tableau ne se teste qu'en l'absence d'autres
   tableaux réels : les tests ne suppriment jamais un tableau existant. */
if (count(homeboard::boards()) === 1) {
	check('dernier tableau protégé', throwsSomething(function () use ($defId) {
		homeboard::deleteBoard($defId);
	}));
} else {
	echo "  ...  plusieurs tableaux présents : garde-fou du dernier non testé\n";
}

homeboard::useBoard($defId);
check('retour au tableau par défaut', homeboard::boardId() === $defId);
check('tuiles du tableau par défaut intactes', count(homeboard::tiles()) === $beforeTiles);

echo "\n[14] Tableaux désactivés et droits\n";

/* Tableau de test : on vérifie qu'un tableau désactivé reste résolvable en
   administration, et que l'affichage le refuse explicitement. */
$b3 = track('board', homeboard::createBoard(array('name' => 'ZZ tableau inactif')));
$b3row = null;
foreach (homeboard::boards() as $b) {
	if ((int) $b['id'] === $b3) { $b3row = $b; }
}
check('tableau de test créé', is_array($b3row));
$b3eq = eqLogic::byId($b3);
$b3eq->setIsEnable(0);
$b3eq->save();

$resolved = homeboard::board($b3row['slug']);
check('un tableau désactivé reste résolvable par son slug', is_array($resolved) && (int) $resolved['id'] === $b3, is_array($resolved) ? (string) $resolved['id'] : 'null');
$resolved = homeboard::board((string) $b3);
check('un tableau désactivé reste résolvable par son id', is_array($resolved) && (int) $resolved['id'] === $b3);
check('il n est pas compté comme tableau par défaut', (int) $resolved['isDefault'] === 0);
check('le tableau par défaut est bien un tableau actif', (int) homeboard::defaultBoard()['isEnable'] === 1);

homeboard::useBoard($b3);
check('affichage refusé pour un tableau désactivé', throwsSomething(function () {
	homeboard::getLayout();
}));
homeboard::useBoard($defId);
check('retour au tableau actif', homeboard::boardId() === $defId);

/* Droits propres au tableau : profil restreint sans « r » sur l'équipement. */
$login = 'zz_hb_test_' . substr((string) mt_rand(1000, 9999), 0, 4);
$u = new user();
$u->setLogin($login);
$u->setPassword('Test-' . mt_rand(100000, 999999));
$u->setProfils('restricted');
$u->setEnable(1);
$u->save();
track('user', (int) $u->getId());
$u = user::byLogin($login);
check('profil restreint créé', is_object($u) && $u->getProfils() === 'restricted');

$savedUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$_SESSION['user'] = $u;
homeboard::useBoard($defId);
check('sans droit r, le tableau est refusé', throwsSomething(function () {
	homeboard::getLayout();
}));
check('sans droit r, aucune action autorisée', throwsSomething(function () {
	homeboard::allowedActions(true);
}));

$u->setRights('eqLogic' . $defId, 'r');
$u->save();
$u = user::byLogin($login);
$_SESSION['user'] = $u;
homeboard::useBoard($defId);
$eqDef = eqLogic::byId($defId);
check('avec droit r, l équipement répond', $eqDef->hasRight('r', $u));
check('le tableau redevient lisible', throwsSomething(function () {
	homeboard::getLayout();
}) === false);

$_SESSION['user'] = $savedUser;

/* On retire le tableau de test pour se retrouver dans le cas « un seul
   tableau », puis on appelle preRemove directement : il doit refuser sans rien
   supprimer. */
homeboard::useBoard($b3);
homeboard::deleteBoard($b3);
homeboard::useBoard($defId);
check('tableau de test retiré', count(homeboard::boards()) === 1, (string) count(homeboard::boards()));

$eqLast = eqLogic::byId($defId);
$others = 0;
foreach (homeboard::boards() as $b) {
	if ((int) $b['id'] !== $defId) { $others++; }
}
if ($others === 0) {
	check('preRemove refuse le dernier tableau', throwsSomething(function () use ($eqLast) {
		$eqLast->preRemove();
	}));
	check('le dernier tableau est toujours là', is_object(eqLogic::byId($eqLast->getId())));
} else {
	echo "  ...  plusieurs tableaux : garde-fou preRemove non testé\n";
}

homeboard::useBoard($defId);

echo "\n[15] Sauvegarde et migration\n";

check('aucune table héritée sur cette installation', homeboard::legacyTableExists() === false);
check('migration à jour', homeboard::legacyMigrationPending() === false);

/* Fausse table héritée, volontairement SANS la colonne `pos` : la lecture doit
   échouer, ce qui vérifie qu'une erreur SQL n'est jamais confondue avec « rien à
   migrer ». La table et le marqueur sont toujours remis en état, même en cas
   d'échec d'un test. */
DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_board`');
DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_board_old`');
DB::getConnection()->exec('CREATE TABLE `homeboard_board` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`name` varchar(191) NOT NULL,
	`slug` varchar(64) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB');
check('table héritée détectée', homeboard::legacyTableExists() === true);

config::save('migratedEqLogic', 0, 'homeboard');
check('table héritée + marqueur absent : migration en attente', homeboard::legacyMigrationPending() === true);

try {
	$threw = throwsSomething(function () {
		homeboard::migrateBoardsToEqLogic();
	});
	check('erreur SQL : exception remontée', $threw);
	check('erreur SQL : migration NON marquée terminée', (int) config::byKey('migratedEqLogic', 'homeboard', 0) === 0, 'marqueur=' . config::byKey('migratedEqLogic', 'homeboard', 0));
	check('erreur SQL : table héritée intacte', homeboard::legacyTableExists() === true);
} finally {
	DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_board`');
	DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_board_old`');
	config::save('migratedEqLogic', 1, 'homeboard');
}

check('table de test supprimée', homeboard::legacyTableExists() === false);
check('installation revenue à l état normal', count(homeboard::boards()) === 1, (string) count(homeboard::boards()));

echo "\n$passed test(s) passé(s), $failed échec(s)\n";
exit($failed === 0 ? 0 : 1);
