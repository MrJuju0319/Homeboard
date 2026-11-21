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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class homeboard extends eqLogic {

	const PLUGIN_ID = 'homeboard';

	/* ============================================================
	 *  Base de données
	 * ============================================================ */

	public static function createTables() {
		$sql = array(
			"CREATE TABLE IF NOT EXISTS `homeboard_room` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` varchar(191) NOT NULL,
				`icon` varchar(64) NOT NULL DEFAULT 'home',
				`pos` int(11) NOT NULL DEFAULT 0,
				`isVisible` tinyint(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"CREATE TABLE IF NOT EXISTS `homeboard_tile` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`room_id` int(11) NOT NULL DEFAULT 0,
				`cmd_id` int(11) NOT NULL DEFAULT 0,
				`scenario_id` int(11) NOT NULL DEFAULT 0,
				`label` varchar(191) NOT NULL DEFAULT '',
				`kind` varchar(32) NOT NULL DEFAULT 'auto',
				`icon` varchar(64) NOT NULL DEFAULT '',
				`size` varchar(16) NOT NULL DEFAULT 'normal',
				`isFavorite` tinyint(1) NOT NULL DEFAULT 0,
				`pos` int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `room_id` (`room_id`),
				KEY `cmd_id` (`cmd_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
		);
		foreach ($sql as $query) {
			DB::getConnection()->exec($query);
		}
		self::ensureSchema();
	}

	/* Migration : les installations créées avant l'ajout des tuiles de scénario
	   n'ont pas la colonne scenario_id. Idempotent, appelé à la mise à jour. */
	public static function ensureSchema() {
		try {
			$cols = DB::Prepare('SHOW COLUMNS FROM homeboard_tile', array(), DB::FETCH_TYPE_ALL);
		} catch (Exception $e) {
			return;
		}
		$have = array();
		foreach ((array) $cols as $c) {
			if (isset($c['Field'])) {
				$have[$c['Field']] = true;
			}
		}
		if (!isset($have['scenario_id'])) {
			DB::getConnection()->exec('ALTER TABLE `homeboard_tile` ADD COLUMN `scenario_id` int(11) NOT NULL DEFAULT 0 AFTER `cmd_id`');
			DB::getConnection()->exec('ALTER TABLE `homeboard_tile` ADD KEY `scenario_id` (`scenario_id`)');
		}
	}

	/* Suppression des données à la désinstallation du plugin. */
	public static function dropTables() {
		DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_tile`');
		DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_room`');
	}

	/* ============================================================
	 *  Réglages
	 * ============================================================ */

	public static function installDefaults() {
		if (config::byKey('homeName', self::PLUGIN_ID, null) === null) {
			config::save('homeName', 'Maison', self::PLUGIN_ID);
		}
		if (config::byKey('showClock', self::PLUGIN_ID, null) === null) {
			config::save('showClock', 1, self::PLUGIN_ID);
		}
		if (config::byKey('refresh', self::PLUGIN_ID, null) === null) {
			config::save('refresh', 3, self::PLUGIN_ID);
		}
		if (config::byKey('wallMode', self::PLUGIN_ID, null) === null) {
			config::save('wallMode', 0, self::PLUGIN_ID);
		}
		if (config::byKey('wallDelay', self::PLUGIN_ID, null) === null) {
			config::save('wallDelay', 2, self::PLUGIN_ID);
		}
	}

	public static function settings() {
		return array(
			'homeName' => (string) config::byKey('homeName', self::PLUGIN_ID, 'Maison'),
			'showClock' => (int) config::byKey('showClock', self::PLUGIN_ID, 1),
			'refresh' => max(2, min(30, (int) config::byKey('refresh', self::PLUGIN_ID, 3))),
			'wallMode' => (int) config::byKey('wallMode', self::PLUGIN_ID, 0),
			'wallDelay' => max(1, min(60, (int) config::byKey('wallDelay', self::PLUGIN_ID, 2))),
			'wallWidgets' => self::wallWidgets(),
		);
	}

	/* Widgets du voile de veille : au plus 4 commandes d'information, validées
	   à l'écriture et revalidées à la lecture (une commande supprimée ou dont
	   les droits ont changé disparaît simplement de l'affichage). */
	public static function wallWidgets() {
		/* config::byKey() décode déjà le JSON stocké : la valeur revient en
		   tableau, pas en chaîne (un cast en string donnait « Array » et
		   vidait silencieusement la liste). */
		$raw = config::byKey('wallWidgets', self::PLUGIN_ID, array());
		if (is_string($raw)) {
			$raw = $raw === '' ? array() : json_decode($raw, true);
		}
		$rows = is_array($raw) ? $raw : array();
		$out = array();
		$seen = array();
		foreach ($rows as $r) {
			if (count($out) >= 4) {
				break;
			}
			$cmdId = (int) (is_array($r) ? (isset($r['cmd']) ? $r['cmd'] : 0) : $r);
			if ($cmdId <= 0 || isset($seen[$cmdId])) {
				continue;
			}
			$cmd = cmd::byId($cmdId);
			if (!is_object($cmd) || $cmd->getType() !== 'info') {
				continue;
			}
			$eq = $cmd->getEqLogic();
			if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('r')) {
				continue;
			}
			$label = is_array($r) && !empty($r['label'])
				? mb_substr(trim((string) $r['label']), 0, 32)
				: mb_substr((string) $cmd->getName(), 0, 32);
			$seen[$cmdId] = true;
			$out[] = array(
				'cmd' => $cmdId,
				'label' => $label,
				'unit' => (string) $cmd->getUnite(),
			);
		}
		return $out;
	}

	public static function saveWall($_data) {
		$mode = isset($_data['wallMode']) ? (int) ((bool) $_data['wallMode']) : 0;
		$delay = isset($_data['wallDelay']) ? (int) $_data['wallDelay'] : 2;
		$delay = max(1, min(60, $delay));
		$rows = isset($_data['wallWidgets']) ? json_decode((string) $_data['wallWidgets'], true) : array();
		$clean = array();
		if (is_array($rows)) {
			foreach ($rows as $r) {
				if (count($clean) >= 4) {
					break;
				}
				$cmdId = (int) (is_array($r) ? (isset($r['cmd']) ? $r['cmd'] : 0) : $r);
				$cmd = cmd::byId($cmdId);
				if (!is_object($cmd) || $cmd->getType() !== 'info') {
					continue;
				}
				$eq = $cmd->getEqLogic();
				if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('r')) {
					continue;
				}
				$label = is_array($r) && !empty($r['label'])
					? mb_substr(trim((string) $r['label']), 0, 32)
					: mb_substr((string) $cmd->getName(), 0, 32);
				$clean[] = array('cmd' => $cmdId, 'label' => $label);
			}
		}
		config::save('wallMode', $mode, self::PLUGIN_ID);
		config::save('wallDelay', $delay, self::PLUGIN_ID);
		config::save('wallWidgets', json_encode($clean), self::PLUGIN_ID);
		return self::settings();
	}

	public static function iconList() {
		return array('home', 'sofa', 'bed', 'kitchen', 'bath', 'garage', 'garden', 'light',
			'lamp', 'plug', 'thermometer', 'shutter', 'camera', 'sensor', 'power', 'water',
			'tv', 'office', 'child', 'stairs', 'pool', 'scene', 'fan', 'car');
	}

	/* ============================================================
	 *  Pièces
	 * ============================================================ */

	public static function rooms($_all = true) {
		$where = $_all ? '' : ' WHERE isVisible = 1';
		$rows = DB::Prepare('SELECT * FROM homeboard_room' . $where . ' ORDER BY pos, id', array(), DB::FETCH_TYPE_ALL);
		return is_array($rows) ? $rows : array();
	}

	public static function saveRoom($_data) {
		$id = isset($_data['id']) ? (int) $_data['id'] : 0;
		$name = isset($_data['name']) ? trim((string) $_data['name']) : '';
		if ($name === '') {
			throw new Exception('Le nom de la pièce est obligatoire');
		}
		$icon = isset($_data['icon']) && $_data['icon'] !== '' ? (string) $_data['icon'] : 'home';
		$pos = isset($_data['pos']) ? (int) $_data['pos'] : 0;
		$visible = isset($_data['isVisible']) ? (int) ((bool) $_data['isVisible']) : 1;
		if ($id > 0) {
			DB::Prepare('UPDATE homeboard_room SET name = ?, icon = ?, pos = ?, isVisible = ? WHERE id = ?',
				array($name, $icon, $pos, $visible, $id));
		} else {
			DB::Prepare('INSERT INTO homeboard_room (name, icon, pos, isVisible) VALUES (?, ?, ?, ?)',
				array($name, $icon, $pos, $visible));
			$id = (int) DB::getLastInsertId();
		}
		return $id;
	}

	public static function deleteRoom($_id) {
		$id = (int) $_id;
		DB::Prepare('DELETE FROM homeboard_room WHERE id = ?', array($id));
		DB::Prepare('UPDATE homeboard_tile SET room_id = 0 WHERE room_id = ?', array($id));
	}

	/* ============================================================
	 *  Tuiles
	 * ============================================================ */

	public static function tiles() {
		$rows = DB::Prepare('SELECT * FROM homeboard_tile ORDER BY pos, id', array(), DB::FETCH_TYPE_ALL);
		return is_array($rows) ? $rows : array();
	}

	public static function saveTile($_data) {
		$id = isset($_data['id']) ? (int) $_data['id'] : 0;
		$kind = self::validKind(isset($_data['kind']) ? $_data['kind'] : 'auto');
		$cmdId = isset($_data['cmd_id']) ? (int) $_data['cmd_id'] : 0;
		$scenarioId = isset($_data['scenario_id']) ? (int) $_data['scenario_id'] : 0;
		$cmd = null;
		$eq = null;
		$sc = null;
		if ($kind === 'scene') {
			$sc = scenario::byId($scenarioId);
			if (!is_object($sc)) {
				throw new Exception('Scénario Jeedom introuvable (id ' . $scenarioId . ')');
			}
			if ((int) $sc->getIsActive() !== 1) {
				throw new Exception('Ce scénario est désactivé dans Jeedom');
			}
			if (method_exists($sc, 'hasRight') && !$sc->hasRight('x')) {
				throw new Exception('Droits insuffisants sur ce scénario');
			}
			$cmdId = 0;
		} else {
			$scenarioId = 0;
			$cmd = cmd::byId($cmdId);
			if (!is_object($cmd)) {
				throw new Exception('Commande Jeedom introuvable (id ' . $cmdId . ')');
			}
			$eq = $cmd->getEqLogic();
			/* Tuile d'affichage = commande d'information ; tuile bouton = action
			   pure. Une commande d'action comme pilote ferait exécuter l'action à
			   chaque affichage (buildTile → stateOf → execCmd). */
			if ($kind === 'button') {
				if ($cmd->getType() !== 'action') {
					throw new Exception('Une tuile bouton doit pointer sur une commande d\'action');
				}
			} elseif ($cmd->getType() !== 'info') {
				throw new Exception('La commande pilote doit être une commande d\'information');
			}
		}
		$label = isset($_data['label']) ? trim((string) $_data['label']) : '';
		if ($label === '') {
			if ($kind === 'scene') {
				$label = (string) $sc->getName();
			} else {
				$label = is_object($eq) ? $eq->getName() : $cmd->getName();
			}
		}
		$label = mb_substr($label, 0, 191);
		$icon = isset($_data['icon']) ? (string) $_data['icon'] : '';
		if ($icon !== '' && !in_array($icon, self::iconList(), true)) {
			$icon = '';
		}
		$size = isset($_data['size']) && in_array($_data['size'], array('normal', 'wide', 'tall'), true) ? $_data['size'] : 'normal';
		$fav = isset($_data['isFavorite']) ? (int) ((bool) $_data['isFavorite']) : 0;
		$roomId = isset($_data['room_id']) ? (int) $_data['room_id'] : 0;
		if ($roomId > 0 && !self::roomExists($roomId)) {
			$roomId = 0;
		}
		$pos = isset($_data['pos']) ? (int) $_data['pos'] : 0;
		if ($id > 0) {
			DB::Prepare('UPDATE homeboard_tile SET room_id = ?, cmd_id = ?, scenario_id = ?, label = ?, kind = ?, icon = ?, size = ?, isFavorite = ?, pos = ? WHERE id = ?',
				array($roomId, $cmdId, $scenarioId, $label, $kind, $icon, $size, $fav, $pos, $id));
		} else {
			DB::Prepare('INSERT INTO homeboard_tile (room_id, cmd_id, scenario_id, label, kind, icon, size, isFavorite, pos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
				array($roomId, $cmdId, $scenarioId, $label, $kind, $icon, $size, $fav, $pos));
			$id = (int) DB::getLastInsertId();
		}
		return $id;
	}

	public static function deleteTile($_id) {
		DB::Prepare('DELETE FROM homeboard_tile WHERE id = ?', array((int) $_id));
	}

	/* Types de tuile autorisés : la valeur est écrite dans un attribut class
	   côté JavaScript, une chaîne libre serait une injection persistante. */
	public static function validKind($_kind) {
		$kinds = array('auto', 'light', 'thermostat', 'sensor', 'switch', 'shutter', 'camera', 'button', 'scene');
		$k = trim((string) $_kind);
		return in_array($k, $kinds, true) ? $k : 'auto';
	}

	private static function roomExists($_id) {
		$row = DB::Prepare('SELECT id FROM homeboard_room WHERE id = ?', array((int) $_id), DB::FETCH_TYPE_ROW);
		return is_array($row) && !empty($row);
	}

	public static function saveTilesBulk($_rows) {
		$rows = json_decode($_rows, true);
		if (!is_array($rows)) {
			throw new Exception('Données invalides');
		}
		$db = DB::getConnection();
		$db->beginTransaction();
		try {
			foreach ($rows as $row) {
				if (!is_array($row) || empty($row['id'])) {
					continue;
				}
				$id = (int) $row['id'];
				$kind = self::validKind(isset($row['kind']) ? $row['kind'] : 'auto');
				$size = (isset($row['size']) && in_array($row['size'], array('normal', 'wide', 'tall'), true)) ? $row['size'] : 'normal';
				DB::Prepare('UPDATE homeboard_tile SET room_id = ?, label = ?, kind = ?, icon = ?, size = ?, isFavorite = ?, pos = ? WHERE id = ?',
					array((int) (isset($row['room_id']) ? $row['room_id'] : 0), trim((string) (isset($row['label']) ? $row['label'] : '')),
						$kind, (string) (isset($row['icon']) ? $row['icon'] : ''),
						$size, (int) (!empty($row['isFavorite'])), (int) (isset($row['pos']) ? $row['pos'] : 0), $id));
			}
			$db->commit();
		} catch (Exception $e) {
			$db->rollBack();
			throw $e;
		}
	}

	/* ============================================================
	 *  Typage des tuiles
	 * ============================================================ */

	public static function kindFromGeneric($_gt, $_subType = '') {
		$gt = strtoupper(trim((string) $_gt));
		if ($gt === '') {
			return $_subType === 'binary' ? 'switch' : 'sensor';
		}
		if (strpos($gt, 'LIGHT') === 0 || $gt === 'BRIGHTNESS') {
			return 'light';
		}
		if (strpos($gt, 'THERMOSTAT') === 0) {
			return 'thermostat';
		}
		if (strpos($gt, 'FLAP') === 0 || $gt === 'SHUTTER_STATE') {
			return 'shutter';
		}
		if (strpos($gt, 'CAMERA') === 0) {
			return 'camera';
		}
		if (strpos($gt, 'MODE') === 0) {
			return 'switch';
		}
		if (in_array($gt, array('TEMPERATURE', 'HUMIDITY', 'POWER', 'CONSUMPTION', 'VOLTAGE',
			'CURRENT', 'BATTERY', 'ENERGY_TOTAL', 'CO2', 'NOISE'), true)) {
			return 'sensor';
		}
		if (strpos($gt, 'ENERGY') === 0 || in_array($gt, array('PRESENCE', 'OPENING', 'WATER_LEAK'), true)) {
			return 'switch';
		}
		return $_subType === 'binary' ? 'switch' : 'sensor';
	}

	public static function defaultIcon($_kind) {
		$map = array(
			'light' => 'light',
			'thermostat' => 'thermometer',
			'sensor' => 'sensor',
			'shutter' => 'shutter',
			'switch' => 'power',
			'camera' => 'camera',
		);
		return isset($map[$_kind]) ? $map[$_kind] : 'sensor';
	}

	/* ============================================================
	 *  Capacités : à partir d'une commande pilote, on retrouve les
	 *  commandes sœurs de l'équipement (on/off, variation, consigne…)
	 * ============================================================ */

	public static function caps($_cmd, $_kind) {
		$caps = array('kind' => $_kind);
		$eq = $_cmd->getEqLogic();
		if (!is_object($eq)) {
			return $caps;
		}
		$byGt = array();
		foreach ($eq->getCmd() as $c) {
			$gt = strtoupper((string) $c->getGeneric_type());
			if ($gt === '') {
				continue;
			}
			$byGt[$gt][] = $c;
		}
		$find = function ($gts, $type) use ($byGt) {
			foreach ((array) $gts as $gt) {
				if (!isset($byGt[$gt])) {
					continue;
				}
				foreach ($byGt[$gt] as $c) {
					if ($c->getType() === $type) {
						return $c;
					}
				}
			}
			return null;
		};
		/* Repli quand l'équipement n'a pas de type générique sur ses actions
		   (fréquent sur les virtuels) : on reconnaît le nom de la commande. */
		$findByName = function ($patterns) use ($eq) {
			foreach ($eq->getCmd() as $c) {
				if ($c->getType() !== 'action') {
					continue;
				}
				$n = strtolower(trim((string) $c->getName()));
				$n = str_replace(array('é', 'è', 'ê', 'ë', 'à', 'â', 'ç', ' ', '-', '_'),
					array('e', 'e', 'e', 'e', 'a', 'a', 'c', '', '', ''), $n);
				foreach ($patterns as $p) {
					if (preg_match($p, $n)) {
						return $c;
					}
				}
			}
			return null;
		};
		$id = function ($cmd) {
			return is_object($cmd) ? (int) $cmd->getId() : null;
		};
		/* Bornes réelles lues dans la configuration de la commande Jeedom
		   (minValue / maxValue / step), avec repli sur les valeurs usuelles. */
		$rangeOf = function ($cmd, $defMin, $defMax, $defStep) {
			if (!is_object($cmd)) {
				return array($defMin, $defMax, $defStep);
			}
			$min = $cmd->getConfiguration('minValue', '');
			$max = $cmd->getConfiguration('maxValue', '');
			$step = $cmd->getConfiguration('step', '');
			return array(
				(is_numeric($min)) ? (float) $min : $defMin,
				(is_numeric($max)) ? (float) $max : $defMax,
				(is_numeric($step) && (float) $step > 0) ? (float) $step : $defStep,
			);
		};

		if ($_kind === 'light') {
			$caps['state'] = $id($find(array('LIGHT_STATE'), 'info'));
			$caps['on'] = $id($find(array('LIGHT_ON', 'LIGHT_TOGGLE'), 'action'));
			$caps['off'] = $id($find(array('LIGHT_OFF'), 'action'));
			$caps['toggle'] = $id($find(array('LIGHT_TOGGLE'), 'action'));
			if (empty($caps['on'])) {
				$caps['on'] = $id($findByName(array('/^(on|allumer|allume|marche|activer|enable)$/')));
			}
			if (empty($caps['off'])) {
				$caps['off'] = $id($findByName(array('/^(off|eteindre|eteins|arret|arreter|stop|desactiver|disable)$/')));
			}
			$caps['bri'] = $id($find(array('LIGHT_BRIGHTNESS'), 'info'));
			$caps['briSet'] = $id($find(array('LIGHT_SLIDER', 'LIGHT_SET_BRIGHTNESS'), 'action'));
			$caps['colorSet'] = $id($find(array('LIGHT_SET_COLOR'), 'action'));
			$rng = $rangeOf($find(array('LIGHT_SLIDER', 'LIGHT_SET_BRIGHTNESS'), 'action'), 0, 100, 1);
			$caps['briMin'] = $rng[0];
			$caps['briMax'] = $rng[1];
			$caps['briStep'] = $rng[2];
		} elseif ($_kind === 'thermostat') {
			$caps['state'] = $id($find(array('THERMOSTAT_STATE_NAME'), 'info'));
			$caps['temp'] = $id($find(array('THERMOSTAT_TEMPERATURE'), 'info'));
			$caps['setpoint'] = $id($find(array('THERMOSTAT_SETPOINT'), 'info'));
			$caps['setSetpoint'] = $id($find(array('THERMOSTAT_SET_SETPOINT'), 'action'));
			$rng = $rangeOf($find(array('THERMOSTAT_SET_SETPOINT'), 'action'), 5, 30, 0.5);
			$caps['spMin'] = $rng[0];
			$caps['spMax'] = $rng[1];
			$caps['spStep'] = $rng[2];
			$modes = array();
			foreach ($byGt as $gt => $cmds) {
				if ($gt !== 'THERMOSTAT_SET_MODE' && $gt !== 'MODE_SET_STATE') {
					continue;
				}
				foreach ($cmds as $c) {
					if ($c->getType() !== 'action') {
						continue;
					}
					$modes[] = array('id' => (int) $c->getId(), 'name' => (string) $c->getName());
				}
			}
			$caps['modes'] = $modes;
		} elseif ($_kind === 'shutter') {
			$caps['state'] = $id($find(array('FLAP_STATE', 'SHUTTER_STATE'), 'info'));
			$caps['up'] = $id($find(array('FLAP_UP'), 'action'));
			$caps['down'] = $id($find(array('FLAP_DOWN'), 'action'));
			$caps['stop'] = $id($find(array('FLAP_STOP'), 'action'));
			$caps['slider'] = $id($find(array('FLAP_SLIDER'), 'action'));
		} elseif ($_kind === 'switch') {
			/* L'état d'une tuile switch doit être une commande d'information :
		   assigner l'id d'une commande d'action ferait exécuter l'action à
		   chaque lecture de valeur (stateOf → execCmd). */
			$caps['state'] = ($_cmd->getType() === 'info') ? (int) $_cmd->getId() : null;
			$caps['on'] = $id($find(array('ENERGY_ON', 'LIGHT_ON'), 'action'));
			$caps['off'] = $id($find(array('ENERGY_OFF', 'LIGHT_OFF'), 'action'));
			if (empty($caps['on'])) {
				$caps['on'] = $id($findByName(array('/^(on|allumer|allume|marche|activer|enable)$/')));
			}
			if (empty($caps['off'])) {
				$caps['off'] = $id($findByName(array('/^(off|eteindre|eteins|arret|arreter|stop|desactiver|disable)$/')));
			}
		} else {
			$caps['state'] = (int) $_cmd->getId();
		}
		return $caps;
	}

	public static function formatValue($_cmd, $_value) {
		if ($_value === null || $_value === '') {
			return '';
		}
		$sub = $_cmd->getSubType();
		if ($sub === 'binary') {
			/* execCmd() peut renvoyer '1', 1, 1.0 ou true selon le plugin. */
			if (is_bool($_value)) {
				$on = $_value;
			} elseif (is_numeric($_value)) {
				$on = (float) $_value > 0.5;
			} else {
				$on = in_array(strtolower(trim((string) $_value)), array('1', 'on', 'true', 'yes'), true);
			}
			/* Le libellé dépend de la nature du capteur : une ouverture
			   « Allumé » n'a aucun sens. */
			$gt = strtoupper(trim((string) $_cmd->getGeneric_type()));
			if (strpos($gt, 'OPENING') !== false) {
				return $on ? 'Ouvert' : 'Fermé';
			}
			if (strpos($gt, 'PRESENCE') !== false || strpos($gt, 'MOTION') !== false) {
				return $on ? 'Présence' : 'Absence';
			}
			if (strpos($gt, 'WATER_LEAK') !== false) {
				return $on ? 'Fuite' : 'Sec';
			}
			if (strpos($gt, 'SMOKE') !== false) {
				return $on ? 'Fumée' : 'Pas de fumée';
			}
			return $on ? 'Allumé' : 'Éteint';
		}
		if ($sub === 'string') {
			return (string) $_value;
		}
		if (is_numeric($_value)) {
			$v = (float) $_value;
			$dec = (abs($v - round($v)) < 0.05) ? 0 : 1;
			return number_format($v, $dec, ',', ' ');
		}
		return (string) $_value;
	}

	public static function stateOf($_cmd) {
		$value = $_cmd->execCmd();
		return array(
			'id' => (int) $_cmd->getId(),
			'value' => $value,
			'display' => self::formatValue($_cmd, $value),
			'unit' => (string) $_cmd->getUnite(),
		);
	}

	/* ============================================================
	 *  Construction du layout envoyé au front
	 * ============================================================ */

	public static function buildTile($_row) {
		$kind = self::validKind((string) $_row['kind']);
		if ($kind === 'scene') {
			return self::buildSceneTile($_row);
		}
		$cmd = cmd::byId((int) $_row['cmd_id']);
		if (!is_object($cmd)) {
			return null;
		}
		if ($kind === 'button') {
			return self::buildButtonTile($_row, $cmd);
		}
		$eq = $cmd->getEqLogic();
		/* Droits par équipement : un utilisateur à accès restreint ne doit pas
		   voir les tuiles d'un équipement qu'il n'a pas le droit de lire. */
		if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('r')) {
			return null;
		}
		$kind = (string) $_row['kind'];
		if ($kind === '' || $kind === 'auto') {
			$kind = self::kindFromGeneric($cmd->getGeneric_type(), $cmd->getSubType());
		}
		$label = trim((string) $_row['label']);
		if ($label === '') {
			$label = is_object($eq) ? $eq->getName() : $cmd->getName();
		}
		$icon = (string) $_row['icon'];
		if ($icon === '') {
			$icon = self::defaultIcon($kind);
		}
		$stateCmd = $cmd;
		$caps = self::caps($cmd, $kind);
		if (!empty($caps['state'])) {
			$tmp = cmd::byId((int) $caps['state']);
			if (is_object($tmp) && $tmp->getType() === 'info') {
				$stateCmd = $tmp;
			}
		}
		/* Filet de sécurité pour les tuiles enregistrées avant le contrôle de
		   saveTile() : stateOf() exécute la commande, donc jamais une action. */
		if ($stateCmd->getType() !== 'info') {
			return null;
		}
		$caps['canExec'] = (!is_object($eq) || !method_exists($eq, 'hasRight')) ? true : (bool) $eq->hasRight('x');
		$extra = array();
		foreach (array('temp', 'setpoint', 'bri') as $key) {
			if (!empty($caps[$key])) {
				$c = cmd::byId((int) $caps[$key]);
				if (is_object($c)) {
					$extra[$key] = self::stateOf($c);
				}
			}
		}
		$chart = null;
		$chartCmd = self::chartCmd($stateCmd, $eq);
		if (is_object($chartCmd)) {
			$chart = array(
				'cmd_id' => (int) $chartCmd->getId(),
				'label' => (string) $chartCmd->getName(),
				'unit' => (string) $chartCmd->getUnite(),
			);
		}
		return array(
			'id' => (int) $_row['id'],
			'room_id' => (int) $_row['room_id'],
			'cmd_id' => (int) $_row['cmd_id'],
			'label' => $label,
			'sub' => is_object($eq) ? $eq->getName() : '',
			'kind' => $kind,
			'icon' => $icon,
			'size' => (string) $_row['size'],
			'favorite' => (int) $_row['isFavorite'],
			'caps' => $caps,
			'extra' => $extra,
			'chart' => $chart,
			'state' => self::stateOf($stateCmd),
		);
	}

	/* ============================================================
	 *  Tuiles d'action : scénario et bouton poussoir
	 * ============================================================ */

	/* Un scénario n'a pas d'état de « tuile » : on rend un modèle minimal, sans
	   commande pilote, pour ne jamais appeler stateOf() dessus. */
	private static function buildSceneTile($_row) {
		$sc = scenario::byId((int) $_row['scenario_id']);
		if (!is_object($sc) || (int) $sc->getIsActive() !== 1) {
			return null;
		}
		if (method_exists($sc, 'hasRight') && !$sc->hasRight('x')) {
			return null;
		}
		$label = trim((string) $_row['label']);
		if ($label === '') {
			$label = (string) $sc->getName();
		}
		$icon = (string) $_row['icon'];
		return array(
			'id' => (int) $_row['id'],
			'room_id' => (int) $_row['room_id'],
			'cmd_id' => 0,
			'scenario_id' => (int) $sc->getId(),
			'label' => $label,
			'sub' => 'Scénario',
			'kind' => 'scene',
			'icon' => $icon !== '' ? $icon : 'scene',
			'size' => (string) $_row['size'],
			'favorite' => (int) $_row['isFavorite'],
			'caps' => array('canExec' => true),
			'extra' => array(),
			'chart' => null,
			'state' => null,
		);
	}

	/* Bouton poussoir : commande d'action pure, aucun état à lire. */
	private static function buildButtonTile($_row, $_cmd) {
		if ($_cmd->getType() !== 'action') {
			return null;
		}
		$eq = $_cmd->getEqLogic();
		if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('r')) {
			return null;
		}
		$canExec = (!is_object($eq) || !method_exists($eq, 'hasRight')) ? true : (bool) $eq->hasRight('x');
		$label = trim((string) $_row['label']);
		if ($label === '') {
			$label = (string) $_cmd->getName();
		}
		$icon = (string) $_row['icon'];
		return array(
			'id' => (int) $_row['id'],
			'room_id' => (int) $_row['room_id'],
			'cmd_id' => (int) $_cmd->getId(),
			'scenario_id' => 0,
			'label' => $label,
			'sub' => is_object($eq) ? (string) $eq->getName() : 'Action',
			'kind' => 'button',
			'icon' => $icon !== '' ? $icon : self::defaultIcon('auto'),
			'size' => (string) $_row['size'],
			'favorite' => (int) $_row['isFavorite'],
			'caps' => array('canExec' => $canExec),
			'extra' => array(),
			'chart' => null,
			'state' => null,
		);
	}

	public static function scenarioStateLabel($_sc) {
		$state = is_object($_sc) ? (string) $_sc->getState() : '';
		if ($state === 'starting' || $state === 'in progress') {
			return 'En cours';
		}
		return '';
	}

	/* Liste des scénarios pour la page de configuration. */
	public static function scenarios() {
		$out = array();
		$all = scenario::all();
		foreach ((array) $all as $sc) {
			if (!is_object($sc)) {
				continue;
			}
			if (method_exists($sc, 'hasRight') && !$sc->hasRight('x')) {
				continue;
			}
			$out[] = array(
				'id' => (int) $sc->getId(),
				'name' => (string) $sc->getName(),
				'group' => (string) $sc->getGroup(),
				'isActive' => (int) $sc->getIsActive(),
				'state' => self::scenarioStateLabel($sc),
			);
		}
		usort($out, function ($a, $b) {
			if ($a['isActive'] !== $b['isActive']) {
				return $b['isActive'] - $a['isActive'];
			}
			return strcmp($a['name'], $b['name']);
		});
		return $out;
	}

	public static function runScenario($_id) {
		$sc = scenario::byId((int) $_id);
		if (!is_object($sc)) {
			throw new Exception('Scénario introuvable');
		}
		if ((int) $sc->getIsActive() !== 1) {
			throw new Exception('Ce scénario est désactivé dans Jeedom');
		}
		if (method_exists($sc, 'hasRight') && !$sc->hasRight('x')) {
			throw new Exception('Droits insuffisants sur ce scénario');
		}
		if (config::byKey('enableScenario') != 1) {
			throw new Exception('Les scénarios sont désactivés dans Jeedom');
		}
		$launched = $sc->launch();
		return array(
			'launched' => (bool) $launched,
			'name' => (string) $sc->getName(),
			'state' => (string) $sc->getState(),
		);
	}

	public static function scenarioStates($_ids) {
		$out = array();
		foreach ((array) $_ids as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$sc = scenario::byId($id);
			if (!is_object($sc)) {
				continue;
			}
			$out[$id] = array('state' => (string) $sc->getState(), 'display' => self::scenarioStateLabel($sc));
		}
		return $out;
	}

	/* ============================================================
	 *  Historique (graphiques)
	 * ============================================================ */

	public static function history($_cmdId, $_period = 'day') {
		$cmd = cmd::byId((int) $_cmdId);
		if (!is_object($cmd) || $cmd->getType() !== 'info') {
			return array('points' => array());
		}
		$eq = $cmd->getEqLogic();
		if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('r')) {
			return array('points' => array());
		}
		$periods = array(
			'day' => array('start' => '-24 hours', 'bucket' => 900),
			'week' => array('start' => '-7 days', 'bucket' => 7200),
			'month' => array('start' => '-30 days', 'bucket' => 21600),
		);
		$p = isset($periods[$_period]) ? $periods[$_period] : $periods['day'];
		$start = date('Y-m-d H:i:s', strtotime($p['start']));
		/* history::all() interroge `history` ET `historyArch` : le cron d'archivage
		   de Jeedom déplace les points de plus de 24 h dans historyArch, donc une
		   requête SQL directe sur `history` vide les vues 7 j et 30 j. */
		$rows = history::all((int) $_cmdId, $start, date('Y-m-d H:i:s'));
		$buckets = array();
		/* Statistiques pondérées sur les mesures réelles : moyenner les moyennes
		   de tranches donne un résultat faux quand une tranche est plus dense. */
		$realN = 0;
		$realSum = 0.0;
		$realMin = null;
		$realMax = null;
		$lastRaw = null;
		foreach ((array) $rows as $r) {
			/* history::all() renvoie des objets history (getters), pas des
			   tableaux : on accepte les deux formes. */
			if (is_object($r)) {
				$rawDt = method_exists($r, 'getDatetime') ? $r->getDatetime() : null;
				$rawVal = method_exists($r, 'getValue') ? $r->getValue() : null;
			} else {
				$rawDt = isset($r['datetime']) ? $r['datetime'] : null;
				$rawVal = isset($r['value']) ? $r['value'] : null;
			}
			if ($rawDt === null || !is_numeric($rawVal)) {
				continue;
			}
			$ts = strtotime($rawDt);
			if ($ts === false) {
				continue;
			}
			$k = (int) (floor($ts / $p['bucket']) * $p['bucket']);
			if (!isset($buckets[$k])) {
				$buckets[$k] = array('sum' => 0, 'n' => 0);
			}
			$val = (float) $rawVal;
			$buckets[$k]['sum'] += $val;
			$buckets[$k]['n']++;
			$realN++;
			$realSum += $val;
			$realMin = ($realMin === null) ? $val : min($realMin, $val);
			$realMax = ($realMax === null) ? $val : max($realMax, $val);
			$lastRaw = $val;
		}
		ksort($buckets);
		$points = array();
		foreach ($buckets as $ts => $b) {
			$points[] = array((int) $ts * 1000, round($b['sum'] / max(1, $b['n']), 2));
		}
		return array(
			'points' => $points,
			'min' => $realMin === null ? null : round($realMin, 2),
			'max' => $realMax === null ? null : round($realMax, 2),
			'avg' => $realN ? round($realSum / $realN, 2) : null,
			'last' => $lastRaw === null ? null : round($lastRaw, 2),
			'count' => $realN,
			'unit' => (string) $cmd->getUnite(),
			'label' => (string) $cmd->getName(),
			'period' => $_period,
		);
	}

	public static function chartCmd($_stateCmd, $_eq) {
		if (!is_object($_eq)) {
			if ($_stateCmd->getIsHistorized() == 1) {
				return $_stateCmd;
			}
			return null;
		}
		/* On ne trace que des grandeurs numériques ou binaires, et on préfère
		   la mesure la plus parlante (température, puissance, luminosité). */
		$pref = array('THERMOSTAT_TEMPERATURE', 'TEMPERATURE', 'POWER', 'CONSUMPTION',
			'LIGHT_BRIGHTNESS', 'HUMIDITY', 'ENERGY_TOTAL', 'VOLTAGE', 'BRIGHTNESS',
			'LIGHT_STATE', 'ENERGY_STATE', 'PRESENCE', 'OPENING', 'BATTERY');
		$byGt = array();
		$numeric = array();
		foreach ($_eq->getCmd('info') as $c) {
			if ($c->getIsHistorized() != 1) {
				continue;
			}
			if (!in_array($c->getSubType(), array('numeric', 'binary'), true)) {
				continue;
			}
			$gt = strtoupper((string) $c->getGeneric_type());
			if ($gt !== '' && !isset($byGt[$gt])) {
				$byGt[$gt] = $c;
			}
			$numeric[] = $c;
		}
		foreach ($pref as $gt) {
			if (isset($byGt[$gt])) {
				return $byGt[$gt];
			}
		}
		if ($_stateCmd->getIsHistorized() == 1 &&
			in_array($_stateCmd->getSubType(), array('numeric', 'binary'), true)) {
			return $_stateCmd;
		}
		return count($numeric) ? $numeric[0] : null;
	}

	public static function getLayout() {
		$rooms = self::rooms(false);
		$tiles = self::tiles();
		$byRoom = array();
		foreach ($tiles as $t) {
			$byRoom[(int) $t['room_id']][] = $t;
		}
		$out = array();
		$favorites = array();
		$charts = array();
		foreach ($rooms as $r) {
			$room = array(
				'id' => (int) $r['id'],
				'name' => (string) $r['name'],
				'icon' => (string) $r['icon'],
				'tiles' => array(),
			);
			if (!empty($byRoom[(int) $r['id']])) {
				foreach ($byRoom[(int) $r['id']] as $row) {
					$tile = self::buildTile($row);
					if ($tile === null) {
						continue;
					}
					$room['tiles'][] = $tile;
					if ($tile['favorite']) {
						$tile['room_name'] = (string) $r['name'];
						$favorites[] = $tile;
					}
					if (!empty($tile['chart'])) {
						if (isset($charts[$tile['chart']['cmd_id']])) {
							continue;
						}
						$chartObj = cmd::byId((int) $tile['chart']['cmd_id']);
						$chartVal = is_object($chartObj) ? $chartObj->execCmd() : '';
						$charts[$tile['chart']['cmd_id']] = array(
							'tile_id' => $tile['id'],
							'label' => $tile['label'],
							'room' => (string) $r['name'],
							'icon' => $tile['icon'],
							'kind' => $tile['kind'],
							'cmd_id' => $tile['chart']['cmd_id'],
							'chart_label' => $tile['chart']['label'],
							'unit' => $tile['chart']['unit'] !== '' ? $tile['chart']['unit'] : $tile['state']['unit'],
							'value' => $chartVal,
						);
					}
				}
			}
			$out[] = $room;
		}
		/* Tuiles orphelines (pièce supprimée / non assignée) : elles doivent
		   aussi alimenter les favoris et les graphiques, sinon une tuile mise
		   en favori sans pièce n'apparaît nulle part. */
		if (!empty($byRoom[0])) {
			$orphan = array('id' => 0, 'name' => 'Autres', 'icon' => 'home', 'tiles' => array());
			foreach ($byRoom[0] as $row) {
				$tile = self::buildTile($row);
				if ($tile === null) {
					continue;
				}
				$orphan['tiles'][] = $tile;
				if ($tile['favorite']) {
					$tile['room_name'] = 'Autres';
					$favorites[] = $tile;
				}
				if (!empty($tile['chart']) && !isset($charts[$tile['chart']['cmd_id']])) {
					$chartObj = cmd::byId((int) $tile['chart']['cmd_id']);
					$charts[$tile['chart']['cmd_id']] = array(
						'tile_id' => $tile['id'],
						'label' => $tile['label'],
						'room' => 'Autres',
						'icon' => $tile['icon'],
						'kind' => $tile['kind'],
						'cmd_id' => $tile['chart']['cmd_id'],
						'chart_label' => $tile['chart']['label'],
						'unit' => $tile['chart']['unit'] !== '' ? $tile['chart']['unit'] : $tile['state']['unit'],
						'value' => is_object($chartObj) ? $chartObj->execCmd() : '',
					);
				}
			}
			if (!empty($orphan['tiles'])) {
				$out[] = $orphan;
			}
		}
		return array(
			'home' => self::settings(),
			'rooms' => $out,
			'favorites' => $favorites,
			'charts' => array_values($charts),
			'user' => array(
				'name' => isset($_SESSION['user']) && is_object($_SESSION['user']) ? $_SESSION['user']->getLogin() : '',
			),
		);
	}

	/* ============================================================
	 *  Valeurs (polling)
	 * ============================================================ */

	public static function values($_ids) {
		$out = array();
		foreach ((array) $_ids as $id) {
			$id = (int) $id;
			if ($id <= 0 || isset($out[$id])) {
				continue;
			}
			$cmd = cmd::byId($id);
			if (!is_object($cmd) || $cmd->getType() !== 'info') {
				continue;
			}
			$eq = $cmd->getEqLogic();
			if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('r')) {
				continue;
			}
			$value = $cmd->execCmd();
			$out[$id] = array(
				'value' => $value,
				'display' => self::formatValue($cmd, $value),
				'unit' => (string) $cmd->getUnite(),
			);
		}
		return $out;
	}

	/* ============================================================
	 *  Exécution
	 * ============================================================ */

	public static function exec($_cmdId, $_value) {
		$cmd = cmd::byId((int) $_cmdId);
		if (!is_object($cmd)) {
			throw new Exception('Commande introuvable');
		}
		if ($cmd->getType() !== 'action') {
			throw new Exception('Cette commande n\'est pas exécutable');
		}
		$eq = $cmd->getEqLogic();
		/* hasRight() est une méthode d'eqLogic (droits par équipement), pas une
		   fonction globale : l'ancien appel ne vérifiait donc rien. */
		if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('x')) {
			throw new Exception('401 Unauthorized');
		}
		if ($_value === null || $_value === '') {
			$cmd->execCmd();
		} else {
			/* Validation de la valeur selon le sous-type avant exécution :
			   une valeur hors bornes ou hors liste est refusée côté serveur. */
			$sub = $cmd->getSubType();
			if ($sub === 'slider') {
				if (!is_numeric($_value)) {
					throw new Exception('Valeur non numérique');
				}
				$v = (float) $_value;
				$min = $cmd->getConfiguration('minValue', '');
				$max = $cmd->getConfiguration('maxValue', '');
				if (is_numeric($min) && $v < (float) $min) {
					$v = (float) $min;
				}
				if (is_numeric($max) && $v > (float) $max) {
					$v = (float) $max;
				}
				$_value = $v;
			} elseif ($sub === 'select') {
				$list = (string) $cmd->getConfiguration('listValue', '');
				if ($list !== '') {
					$allowed = array();
					foreach (explode(';', $list) as $pair) {
						$bits = explode('|', $pair);
						$allowed[] = trim($bits[0]);
					}
					if (!in_array((string) $_value, $allowed, true)) {
						throw new Exception('Valeur absente de la liste autorisée');
					}
				}
			} elseif ($sub === 'color') {
				if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $_value)) {
					throw new Exception('Couleur invalide');
				}
			} elseif ($sub === 'message') {
				$_value = mb_substr((string) $_value, 0, 1000);
			}
			/* Jeedom attend une clé différente selon le sous-type. */
			$key = 'slider';
			switch ($sub) {
				case 'select':
					$key = 'select';
					break;
				case 'color':
					$key = 'color';
					break;
				case 'message':
					$key = 'message';
					break;
			}
			$cmd->execCmd(array($key => $_value));
		}
		log::add(self::PLUGIN_ID, 'debug', 'Exécution de ' . $cmd->getHumanName() . ($_value !== null ? ' (' . $_value . ')' : ''));
		return true;
	}

	/* ============================================================
	 *  Import automatique (plug & play)
	 * ============================================================ */

	public static function roomIconFor($_name) {
		$n = strtolower((string) $_name);
		$map = array(
			'salon' => 'sofa', 'séjour' => 'sofa', 'sejour' => 'sofa',
			'cuisine' => 'kitchen', 'chambre' => 'bed', 'parental' => 'bed', 'amis' => 'child',
			'bain' => 'bath', 'sdb' => 'bath', 'douche' => 'bath', 'wc' => 'bath',
			'garage' => 'garage', 'jardin' => 'garden', 'extérieur' => 'garden', 'exterieur' => 'garden',
			'bureau' => 'office', 'entrée' => 'stairs', 'entree' => 'stairs', 'couloir' => 'stairs',
			'escalier' => 'stairs', 'buanderie' => 'kitchen', 'cellier' => 'kitchen',
			'piscine' => 'pool', 'terrasse' => 'garden',
		);
		foreach ($map as $k => $icon) {
			if (strpos($n, $k) !== false) {
				return $icon;
			}
		}
		return 'home';
	}

	/* Normalisation des noms (casse, accents, ponctuation) pour comparer un nom
	   d'objet Jeedom et un nom de pièce Homeboard. */
	private static function normName($_s) {
		$s = strtolower(trim((string) $_s));
		$s = str_replace(
			array('à', 'â', 'ä', 'á', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'í', 'ô', 'ö', 'ó', 'ù', 'û', 'ü', 'ú', 'ç', 'ñ', 'œ', '’', "'"),
			array('a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n', 'oe', '', ''),
			$s
		);
		$s = preg_replace('/[^a-z0-9]+/', ' ', $s);
		return trim(preg_replace('/\s+/', ' ', $s));
	}

	private static function isWholeWord($_haystack, $_needle) {
		if ($_needle === '') {
			return false;
		}
		return preg_match('/(^| )' . preg_quote($_needle, '/') . '($| )/', $_haystack) === 1;
	}

	public static function roomByName($_name) {
		return self::matchRoom($_name, self::rooms(true));
	}

	/* Même logique, mais sur une liste déjà chargée : scan() appelait rooms()
	   deux fois par équipement (200 requêtes SQL identiques pour 100 appareils). */
	private static function matchRoom($_name, array $_rooms) {
		$n = self::normName($_name);
		if ($n === '') {
			return 0;
		}
		$best = 0;
		$bestLen = 0;
		foreach ($_rooms as $r) {
			$rn = self::normName($r['name']);
			if ($rn === '') {
				continue;
			}
			/* 1. Égalité stricte normalisée. */
			if ($rn === $n) {
				return (int) $r['id'];
			}
			/* 2. Mot entier de part et d'autre (« Chambre » dans « Chambre 2 »). */
			$match = self::isWholeWord($n, $rn) || self::isWholeWord($rn, $n);
			/* 3. Préfixe du nom d'objet : « SalonSAM » → « Salon ». Un simple
			   strpos() créait des collisions (« Webcam » → « WC »). */
			if (!$match && strlen($rn) >= 4 && strpos($n, $rn) === 0) {
				$match = true;
			}
			if ($match && strlen($rn) > $bestLen) {
				$best = (int) $r['id'];
				$bestLen = strlen($rn);
			}
		}
		return $best;
	}

	public static function scan() {
		$blocked = array('conso', 'networks', 'openvpn', 'script', 'discordlink', 'camera',
			'mode', 'homebridge', 'AndroidTV', 'jeelink', 'hoymilescloud');
		$rows = DB::Prepare('SELECT e.id, e.name, e.object_id, e.eqType_name, o.name AS obj_name
			FROM eqLogic e LEFT JOIN object o ON o.id = e.object_id
			WHERE e.isEnable = 1 ORDER BY o.name, e.name', array(), DB::FETCH_TYPE_ALL);
		$used = array();
		foreach (DB::Prepare('SELECT cmd_id FROM homeboard_tile', array(), DB::FETCH_TYPE_ALL) as $u) {
			$used[(int) $u['cmd_id']] = true;
		}
		$out = array();
		$roomsList = self::rooms(true);
		foreach ((array) $rows as $r) {
			if (in_array($r['eqType_name'], $blocked, true)) {
				continue;
			}
			$cmds = DB::Prepare('SELECT id, name, generic_type, subType, unite, isHistorized
				FROM cmd WHERE eqLogic_id = ? AND type = ? ORDER BY name',
				array($r['id'], 'info'), DB::FETCH_TYPE_ALL);
			$byGt = array();
			foreach ((array) $cmds as $c) {
				$gt = strtoupper((string) $c['generic_type']);
				if ($gt !== '' && !isset($byGt[$gt])) {
					$byGt[$gt] = $c;
				}
			}
			$picks = array();
			if (isset($byGt['THERMOSTAT_TEMPERATURE'])) {
				$picks[] = array($byGt['THERMOSTAT_TEMPERATURE'], 'thermostat');
			} elseif (isset($byGt['LIGHT_STATE'])) {
				$picks[] = array($byGt['LIGHT_STATE'], 'light');
			} elseif (isset($byGt['FLAP_STATE'])) {
				$picks[] = array($byGt['FLAP_STATE'], 'shutter');
			} elseif (isset($byGt['ENERGY_STATE'])) {
				$picks[] = array($byGt['ENERGY_STATE'], 'switch');
			} elseif (isset($byGt['TEMPERATURE'])) {
				$picks[] = array($byGt['TEMPERATURE'], 'sensor');
			} elseif (isset($byGt['POWER'])) {
				$picks[] = array($byGt['POWER'], 'sensor');
			} elseif (isset($byGt['HUMIDITY'])) {
				$picks[] = array($byGt['HUMIDITY'], 'sensor');
			} elseif (isset($byGt['PRESENCE'])) {
				$picks[] = array($byGt['PRESENCE'], 'switch');
			} elseif (isset($byGt['OPENING'])) {
				$picks[] = array($byGt['OPENING'], 'switch');
			}
			/* sonde d'humidité en plus de la température (y compris quand la
			   température vient d'un thermostat) */
			$hasTemp = isset($byGt['TEMPERATURE']) || isset($byGt['THERMOSTAT_TEMPERATURE']);
			if ($hasTemp && isset($byGt['HUMIDITY'])) {
				$already = false;
				foreach ($picks as $pk) {
					if ((int) $pk[0]['id'] === (int) $byGt['HUMIDITY']['id']) {
						$already = true;
					}
				}
				if (!$already) {
					$picks[] = array($byGt['HUMIDITY'], 'sensor');
				}
			}
			if (!count($picks)) {
				continue;
			}
			$objName = ($r['obj_name'] !== null && $r['obj_name'] !== '') ? $r['obj_name'] : 'Autres';
			$existingRoom = self::matchRoom($objName, $roomsList);
			$roomTarget = $objName;
			if ($existingRoom > 0) {
				foreach ($roomsList as $rr) {
					if ((int) $rr['id'] === $existingRoom) {
						$roomTarget = (string) $rr['name'];
					}
				}
			}
			foreach ($picks as $p) {
				$c = $p[0];
				$kind = $p[1];
				$unite = (string) $c['unite'];
				$label = $r['name'];
				if ($kind === 'sensor' && $unite === '%') {
					$label = ($r['name'] === $objName) ? 'Humidité' : $r['name'] . ' (humidité)';
				} elseif ($kind === 'sensor' && stripos($c['generic_type'], 'TEMPERATURE') !== false) {
					$label = ($r['name'] === $objName) ? 'Température' : $r['name'];
				}
				$out[] = array(
					'eq_id' => (int) $r['id'],
					'eq_name' => (string) $r['name'],
					'object' => (string) $objName,
					'room' => (string) $roomTarget,
					'room_icon' => self::roomIconFor($objName),
					'cmd_id' => (int) $c['id'],
					'cmd_name' => (string) $c['name'],
					'gt' => (string) $c['generic_type'],
					'unit' => $unite,
					'kind' => $kind,
					'label' => $label,
					'icon' => self::defaultIcon($kind),
					'historized' => (int) $c['isHistorized'],
					'already' => isset($used[(int) $c['id']]),
				);
			}
		}
		return $out;
	}

	public static function importTiles($_rows) {
		$rows = json_decode($_rows, true);
		if (!is_array($rows)) {
			throw new Exception('Données invalides');
		}
		if (count($rows) > 500) {
			throw new Exception('Trop de lignes à importer (500 maximum)');
		}
		$rooms = array();
		$maxRoomPos = 0;
		foreach (self::rooms(true) as $r) {
			$rooms[strtolower(trim($r['name']))] = (int) $r['id'];
			$maxRoomPos = max($maxRoomPos, (int) $r['pos']);
		}
		/* Commandes déjà utilisées : un import répété ne doit pas recréer les
		   tuiles (la vérification de l'interface ne suffit pas). */
		$existing = array();
		foreach (DB::Prepare('SELECT cmd_id FROM homeboard_tile', array(), DB::FETCH_TYPE_ALL) as $t) {
			$existing[(int) $t['cmd_id']] = true;
		}
		$skipped = 0;
		$created = 0;
		$pos = count(self::tiles());
		$db = DB::getConnection();
		$db->beginTransaction();
		try {
			foreach ($rows as $row) {
				if (!is_array($row) || empty($row['cmd_id'])) {
					continue;
				}
				if (isset($existing[(int) $row['cmd_id']])) {
					$skipped++;
					continue;
				}
				/* La pièce retenue par scan() prime sur le nom de l'objet Jeedom :
				   envoyer « object » recréait une pièce déjà reconnue. */
				$roomName = trim((string) (!empty($row['room']) ? $row['room'] : (isset($row['object']) ? $row['object'] : '')));
				if ($roomName === '') {
					$roomName = 'Autres';
				}
				$key = strtolower($roomName);
				$roomId = self::roomByName($roomName);
				if (!$roomId && isset($rooms[$key])) {
					$roomId = $rooms[$key];
				}
				if (!$roomId) {
					$roomId = self::saveRoom(array(
						'id' => 0,
						'name' => $roomName,
						'icon' => !empty($row['room_icon']) ? $row['room_icon'] : self::roomIconFor($roomName),
						'pos' => ++$maxRoomPos,
						'isVisible' => 1,
					));
					$rooms[$key] = $roomId;
				}
				self::saveTile(array(
					'id' => 0,
					'room_id' => $roomId,
					'cmd_id' => (int) $row['cmd_id'],
					'label' => isset($row['label']) ? $row['label'] : '',
					'kind' => isset($row['kind']) ? $row['kind'] : 'auto',
					'icon' => isset($row['icon']) ? $row['icon'] : '',
					'size' => 'normal',
					'isFavorite' => !empty($row['favorite']) ? 1 : 0,
					'pos' => $pos++,
				));
				$created++;
				$existing[(int) $row['cmd_id']] = true;
			}
			$db->commit();
		} catch (Exception $e) {
			$db->rollBack();
			throw $e;
		} catch (Throwable $e) {
			$db->rollBack();
			throw $e;
		}
		return array('created' => $created, 'skipped' => $skipped, 'rooms' => count($rooms));
	}

	/* ============================================================
	 *  Recherche de commandes (page de configuration)
	 * ============================================================ */

	public static function searchCmds($_query, $_limit = 40) {
		$q = trim((string) $_query);
		$limit = max(1, min(100, (int) $_limit));
		$params = array();
		/* Les commandes d'action sont incluses : ce sont elles qui alimentent
		   les tuiles « bouton poussoir » (virtuels sans retour d'état). */
		$where = "c.type IN ('info','action') AND e.isEnable = 1 AND c.isVisible = 1";
		if ($q !== '') {
			$like = '%' . $q . '%';
			$where .= ' AND (c.name LIKE ? OR e.name LIKE ?)';
			$params[] = $like;
			$params[] = $like;
		}
		$rows = DB::Prepare('SELECT c.id, c.name, c.type, c.subType, c.generic_type, c.unite, e.name AS eq_name, e.id AS eq_id
			FROM cmd c JOIN eqLogic e ON e.id = c.eqLogic_id
			WHERE ' . $where . '
			ORDER BY e.name, c.name LIMIT ' . $limit, $params, DB::FETCH_TYPE_ALL);
		$out = array();
		foreach ((array) $rows as $r) {
			$isAction = ((string) $r['type'] === 'action');
			$out[] = array(
				'id' => (int) $r['id'],
				'name' => (string) $r['name'],
				'eq' => (string) $r['eq_name'],
				'eq_id' => (int) $r['eq_id'],
				'type' => (string) $r['type'],
				'gt' => (string) $r['generic_type'],
				'unite' => (string) $r['unite'],
				'kind' => $isAction ? 'button' : self::kindFromGeneric($r['generic_type'], $r['subType']),
			);
		}
		return $out;
	}

	public static function adminData() {
		$rooms = self::rooms(true);
		$tiles = array();
		foreach (self::tiles() as $row) {
			$cmd = cmd::byId((int) $row['cmd_id']);
			$sc = (!empty($row['scenario_id'])) ? scenario::byId((int) $row['scenario_id']) : null;
			if ((string) $row['kind'] === 'scene') {
				$row['cmd_name'] = is_object($sc) ? $sc->getName() : '(scénario supprimé)';
				$row['eq_name'] = 'Scénario';
			} else {
				$row['cmd_name'] = is_object($cmd) ? $cmd->getName() : '(commande supprimée)';
				$row['eq_name'] = (is_object($cmd) && is_object($cmd->getEqLogic())) ? $cmd->getEqLogic()->getName() : '';
			}
			$row['state'] = (is_object($cmd) && $cmd->getType() === 'info') ? (string) $cmd->execCmd() : '';
			$row['unite'] = is_object($cmd) ? (string) $cmd->getUnite() : '';
			$row['generic_type'] = is_object($cmd) ? (string) $cmd->getGeneric_type() : '';
			$row['computed_kind'] = is_object($cmd) ? self::kindFromGeneric($cmd->getGeneric_type(), $cmd->getSubType()) : 'sensor';
			$tiles[] = $row;
		}
		return array(
			'settings' => self::settings(),
			'rooms' => $rooms,
			'tiles' => $tiles,
			'icons' => self::iconList(),
		);
	}
}

class homeboardCmd extends cmd {
}
