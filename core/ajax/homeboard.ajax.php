<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* Jeedom n'expose pas d'aide pour le schéma réel de la requête (proxy
   jeedom.link, reverse proxy local). On lit donc l'en-tête du proxy en premier,
   puis la connexion elle-même. */
function hbRequestScheme() {
	if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
		$proto = strtolower(trim(current(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']))));
		if ($proto === 'https' || $proto === 'http') {
			return $proto;
		}
	}
	if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
		return 'https';
	}
	if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
		return 'https';
	}
	return 'http';
}

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	require_once dirname(__FILE__) . '/../class/homeboard.pure.php';
	include_file('core', 'authentification', 'php');

	/* Lectures autorisées en GET ; toutes les actions d'écriture et exec()
	   doivent arriver en POST. Sans cet appel, une balise <img> ou un lien
	   piégé ouvert depuis une session Jeedom authentifiée pouvait exécuter un
	   équipement ou effacer la configuration (CSRF). */
	ajax::init(array('layout', 'values', 'history', 'adminData', 'searchCmd', 'scan', 'scenarioState', 'scenarios'));

	if (!isConnect()) {
		throw new Exception('401 Unauthorized');
	}

	$action = init('action');

	/* Tableau de bord courant : le paramètre « b » (slug ou identifiant) décide
	   du périmètre de lecture ET d'écriture. Absent ou inconnu, on retombe sur
	   le tableau par défaut. */
	homeboard::useBoard(init('b', init('board', '')));

	/* Défense CSRF complémentaire : Jeedom 4.4+ n'expose plus de jeton CSRF
	   (ajax::getToken() renvoie ''), on s'appuie donc sur POST + même origine.
	   Contrôle strict : il faut au moins un des deux en-têtes, et l'origine
	   complète (schéma + hôte + port effectif) doit correspondre à la requête.
	   En cas de doute, on refuse : une écriture rejetée à tort se retente,
	   une écriture acceptée à tort ne se rattrape pas. */
	$writeActions = array('exec', 'runScenario', 'saveSettings', 'saveRoom', 'deleteRoom', 'saveTile',
		'saveTiles', 'deleteTile', 'import');
	if (in_array($action, $writeActions, true)) {
		$site = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
		if ($site === '' && $origin === '') {
			throw new Exception('403 Contrôle d\'origine absent');
		}
		if ($site !== '' && !in_array($site, array('same-origin', 'none'), true)) {
			throw new Exception('403 Requête cross-origin refusée');
		}
		$scheme = hbRequestScheme();
		$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
		$h = parse_url($scheme . '://' . $host);
		$hhost = (isset($h['host']) && $h['host'] !== '') ? strtolower($h['host']) : '';
		if ($hhost === '') {
			throw new Exception('403 Hôte de la requête illisible');
		}
		if ($origin !== '' && !homeboardPure::originMatches($scheme, $hhost, isset($h['port']) ? $h['port'] : null, $origin)) {
			throw new Exception('403 Origin invalide');
		}
	}

	$adminActions = array('saveSettings', 'saveRoom', 'deleteRoom', 'saveTile', 'saveTiles',
		'deleteTile', 'searchCmd', 'adminData', 'scan', 'import', 'scenarios');
	if (in_array($action, $adminActions, true) && !isConnect('admin')) {
		throw new Exception('401 Unauthorized - droits administrateur requis');
	}

	switch ($action) {

		/* ---------------- Lecture (dashboard) ---------------- */
		case 'layout':
			ajax::success(homeboard::getLayout());
			break;

		case 'values':
			$ids = init('ids', '');
			$ids = $ids === '' ? array() : explode(',', $ids);
			ajax::success(homeboard::values($ids));
			break;

		case 'history':
			ajax::success(homeboard::history(init('cmd_id'), init('period', 'day')));
			break;

		case 'exec':
			$value = init('value', null);
			$result = homeboard::exec(init('cmd_id'), $value);
			ajax::success(array('executed' => $result));
			break;

		/* ---------------- Scénarios ---------------- */
		case 'runScenario':
			ajax::success(homeboard::runScenario(init('id')));
			break;

		case 'scenarioState':
			$ids = init('ids', '');
			$ids = $ids === '' ? array() : explode(',', $ids);
			ajax::success(homeboard::scenarioStates($ids));
			break;

		case 'scenarios':
			ajax::success(homeboard::scenarios());
			break;

		/* ---------------- Administration ---------------- */
		case 'adminData':
			ajax::success(homeboard::adminData());
			break;

		case 'searchCmd':
			ajax::success(homeboard::searchCmds(init('q', ''), init('limit', 40)));
			break;

		case 'scan':
			ajax::success(homeboard::scan());
			break;

		case 'import':
			ajax::success(homeboard::importTiles(init('rows', '[]')));
			break;

		case 'saveSettings':
			config::save('homeName', trim((string) init('homeName', 'Maison')), homeboard::PLUGIN_ID);
			config::save('showClock', (int) init('showClock', 1), homeboard::PLUGIN_ID);
			config::save('refresh', max(2, min(30, (int) init('refresh', 3))), homeboard::PLUGIN_ID);
			ajax::success(homeboard::settings());
			break;

		/* Tableau affiché par la page de configuration. Jeedom rend cette page
		   en AJAX : un paramètre d'URL ne survit pas au rechargement, on garde
		   donc le choix côté serveur. */
		case 'saveRoom':
			$id = homeboard::saveRoom(array(
				'id' => init('id', 0),
				'name' => init('name', ''),
				'icon' => init('icon', 'home'),
				'pos' => init('pos', 0),
				'isVisible' => init('isVisible', 1),
			));
			ajax::success(array('id' => $id));
			break;

		case 'deleteRoom':
			homeboard::deleteRoom(init('id'));
			ajax::success();
			break;

		case 'saveTile':
			$id = homeboard::saveTile(array(
				'id' => init('id', 0),
				'room_id' => init('room_id', 0),
				'cmd_id' => init('cmd_id', 0),
				'scenario_id' => init('scenario_id', 0),
				'label' => init('label', ''),
				'kind' => init('kind', 'auto'),
				'icon' => init('icon', ''),
				'size' => init('size', 'normal'),
				'isFavorite' => init('isFavorite', 0),
				'pos' => init('pos', 0),
			));
			ajax::success(array('id' => $id));
			break;

		case 'saveTiles':
			ajax::success(homeboard::saveTilesBulk(init('rows', '')));
			break;

		case 'deleteTile':
			homeboard::deleteTile(init('id'));
			ajax::success();
			break;

		default:
			throw new Exception('Méthode inconnue : ' . $action);
	}
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
} catch (Throwable $e) {
	ajax::error(displayException($e), $e->getCode());
}
