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

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
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

	/* Défense CSRF complémentaire : Jeedom 4.4+ n'expose plus de jeton CSRF
	   (ajax::getToken() renvoie ''), on s'appuie donc sur POST + même origine.
	   Un formulaire hostile d'un autre site n'a ni le bon Origin ni un
	   Sec-Fetch-Site same-origin. */
	$writeActions = array('exec', 'runScenario', 'saveSettings', 'saveWall', 'saveRoom', 'deleteRoom', 'saveTile',
		'saveTiles', 'deleteTile', 'import');
	if (in_array($action, $writeActions, true)) {
		$site = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
		if ($site !== '' && !in_array($site, array('same-origin', 'none'), true)) {
			throw new Exception('403 Requête cross-origin refusée');
		}
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
		if ($origin !== '') {
			$o = parse_url($origin);
			$ohost = isset($o['host']) ? strtolower($o['host']) : '';
			$oport = isset($o['port']) ? (int) $o['port'] : null;
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
			$h = parse_url('http://' . $host);
			$hhost = isset($h['host']) ? strtolower($h['host']) : '';
			$hport = isset($h['port']) ? (int) $h['port'] : null;
			if ($ohost === '' || $ohost !== $hhost || ($oport !== null && $hport !== null && $oport !== $hport)) {
				throw new Exception('403 Origin invalide');
			}
		}
	}

	$adminActions = array('saveSettings', 'saveWall', 'saveRoom', 'deleteRoom', 'saveTile', 'saveTiles',
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
			config::save('wallMode', (int) init('wallMode', 0), homeboard::PLUGIN_ID);
			ajax::success(homeboard::settings());
			break;

		case 'saveWall':
			ajax::success(homeboard::saveWall(array(
				'wallMode' => init('wallMode', 0),
				'wallOled' => init('wallOled', 0),
				'wallDelay' => init('wallDelay', 2),
				'wallWidgets' => init('wallWidgets', '[]'),
			)));
			break;

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
			homeboard::saveTilesBulk(init('rows', ''));
			ajax::success();
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
