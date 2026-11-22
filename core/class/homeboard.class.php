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
require_once dirname(__FILE__) . '/homeboard.pure.php';

class homeboard extends eqLogic {

	const PLUGIN_ID = 'homeboard';

	/* Longueur maximale d'un libellé et liste des types : définies dans
	   homeboard.pure.php pour être testables sans Jeedom. */
	const LABEL_MAX = homeboardPure::LABEL_MAX;
	const KINDS = homeboardPure::KINDS;

	/* Le tableau courant et les caches vivent dans homeboardState (classe
	   simple, en bas de ce fichier) : une propriété statique déclarée ici
	   serait prise par Jeedom pour une colonne de la table eqLogic. */

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
				`eq_id` int(11) NOT NULL DEFAULT 0,
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
				KEY `eq_id` (`eq_id`),
				KEY `room_id` (`room_id`),
				KEY `cmd_id` (`cmd_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
		);
		foreach ($sql as $query) {
			DB::getConnection()->exec($query);
		}
		self::ensureSchema();
	}

	/* Migration des installations antérieures à 0.5.1 : les tableaux étaient
	   des lignes de homeboard_board. Ils deviennent des équipements Jeedom, et
	   homeboard_tile.board_id devient eq_id. La table d'origine est renommée
	   (jamais supprimée) le temps de vérifier la bascule. */
	public static function ensureSchema() {
		try {
			$cols = DB::Prepare('SHOW COLUMNS FROM homeboard_tile', array(), DB::FETCH_TYPE_ALL);
		} catch (Exception $e) {
			return;
		} catch (Throwable $e) {
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
		/* board_id (0.5.0) → eq_id (0.5.1). */
		if (!isset($have['eq_id'])) {
			if (isset($have['board_id'])) {
				DB::getConnection()->exec('ALTER TABLE `homeboard_tile` CHANGE `board_id` `eq_id` int(11) NOT NULL DEFAULT 0');
				DB::getConnection()->exec('ALTER TABLE `homeboard_tile` DROP INDEX `board_id`, ADD KEY `eq_id` (`eq_id`)');
			} else {
				DB::getConnection()->exec('ALTER TABLE `homeboard_tile` ADD COLUMN `eq_id` int(11) NOT NULL DEFAULT 0 AFTER `id`');
				DB::getConnection()->exec('ALTER TABLE `homeboard_tile` ADD KEY `eq_id` (`eq_id`)');
			}
		}
		try {
			self::migrateBoardsToEqLogic();
		} catch (Exception $e) {
			log::add(self::PLUGIN_ID, 'error', 'Migration des tableaux : ' . $e->getMessage());
		} catch (Throwable $e) {
			log::add(self::PLUGIN_ID, 'error', 'Migration des tableaux : ' . $e->getMessage());
		}
		self::ensureFirstBoard();
	}

	/* L'ancienne table des tableaux (avant 0.6) est-elle encore là ?

	   Une erreur SQL REMONTE : elle ne doit jamais être confondue avec une
	   absence de table, sinon la migration se déclarerait terminée alors que la
	   table est toujours là et que rien n'a été converti. La recherche est
	   exacte (information_schema) et non un LIKE, où `_` sert de joker. */
	public static function legacyTableExists() {
		if (is_callable(homeboardState::$legacyProbe)) {
			/* Point d'injection utilisé par les tests d'erreur. */
			return (bool) call_user_func(homeboardState::$legacyProbe);
		}
		$row = DB::Prepare(
			'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
			array('homeboard_board'),
			DB::FETCH_TYPE_ROW
		);
		return is_array($row) && (int) $row['n'] > 0;
	}

	/* Migration pas encore faite : l'administration le signale au lieu de
	   laisser croire que tout est en place. Ici l'erreur SQL est absorbée (c'est
	   un affichage, il ne doit pas casser la page) mais journalisée : le chemin
	   d'écriture, lui, reste strict. */
	public static function legacyMigrationPending() {
		if ((int) config::byKey('migratedEqLogic', self::PLUGIN_ID, 0) === 1) {
			return false;
		}
		try {
			return self::legacyTableExists();
		} catch (Throwable $e) {
			log::add(self::PLUGIN_ID, 'error', 'Lecture de l\'ancienne table impossible : ' . $e->getMessage());
			return false;
		}
	}

	/* Reprend chaque ligne de homeboard_board et crée l'équipement
	   correspondant, puis rattache les tuiles.

	   La table d'origine n'est jamais supprimée : elle est renommée
	   homeboard_board_old et sert de sauvegarde, y compris si l'écriture d'un
	   fichier échouait (aucun fichier de sauvegarde n'est donc créé dans le
	   répertoire du plugin, qui est servi par le serveur web).

	   Idempotent : un slug déjà présent est réutilisé, et l'indicateur
	   migratedEqLogic n'est posé qu'après une migration menée à son terme. Une
	   erreur SQL inattendue interrompt la migration sans marquer quoi que ce
	   soit, pour qu'une reprise reste possible. */
	public static function migrateBoardsToEqLogic() {
		if ((int) config::byKey('migratedEqLogic', self::PLUGIN_ID, 0) === 1) {
			return;
		}
		if (!self::legacyTableExists()) {
			/* Rien à migrer : installation neuve, ou migration déjà faite. */
			config::save('migratedEqLogic', 1, self::PLUGIN_ID);
			return;
		}

		$rows = DB::Prepare('SELECT * FROM homeboard_board ORDER BY pos, id', array(), DB::FETCH_TYPE_ALL);
		if (!is_array($rows)) {
			return;
		}
		if (!count($rows)) {
			DB::getConnection()->exec('RENAME TABLE `homeboard_board` TO `homeboard_board_old`');
			config::save('migratedEqLogic', 1, self::PLUGIN_ID);
			return;
		}

		$existing = array();
		foreach (self::boards() as $b) {
			$existing[(string) $b['slug']] = (int) $b['id'];
		}

		$map = array();
		foreach ($rows as $row) {
			$slug = (string) $row['slug'];
			if ($slug !== '' && isset($existing[$slug])) {
				$map[(int) $row['id']] = $existing[$slug];
				continue;
			}
			$eq = new self();
			$eq->setName(mb_substr((string) $row['name'], 0, self::LABEL_MAX));
			$eq->setEqType_name(self::PLUGIN_ID);
			$eq->setIsEnable(1);
			$eq->setIsVisible(isset($row['isVisible']) ? (int) $row['isVisible'] : 1);
			self::setConfigurationArray($eq, array(
				'slug' => $slug,
				'wallMode' => isset($row['wallMode']) ? (int) $row['wallMode'] : 0,
				'wallDelay' => isset($row['wallDelay']) ? (int) $row['wallDelay'] : 2,
				'wallOled' => isset($row['wallOled']) ? (int) $row['wallOled'] : 0,
				'wallWidgets' => isset($row['wallWidgets']) ? (string) $row['wallWidgets'] : '[]',
			));
			$eq->save();
			$map[(int) $row['id']] = (int) $eq->getId();
		}
		foreach ($map as $oldId => $newId) {
			if ($oldId !== $newId) {
				DB::Prepare('UPDATE homeboard_tile SET eq_id = ? WHERE eq_id = ?', array($newId, $oldId));
			}
		}
		/* Renommage en dernier : la table reste la sauvegarde de la migration. */
		DB::getConnection()->exec('RENAME TABLE `homeboard_board` TO `homeboard_board_old`');
		config::save('migratedEqLogic', 1, self::PLUGIN_ID);
		log::add(self::PLUGIN_ID, 'info', 'Migration : ' . count($map) . ' tableau(x) converti(s) en équipement(s) homeboard');
	}

	/* Une installation neuve n'a aucun équipement : on en crée un pour que la
	   page ait quelque chose à afficher. */
	private static function ensureFirstBoard() {
		if (count(self::boards()) > 0) {
			return;
		}
		$eq = new self();
		$eq->setName('Maison');
		$eq->setEqType_name(self::PLUGIN_ID);
		$eq->setIsEnable(1);
		$eq->setIsVisible(1);
		self::setConfigurationArray($eq, array(
			'slug' => 'maison',
			'wallMode' => max(0, min(1, (int) config::byKey('wallMode', self::PLUGIN_ID, 0))),
			'wallDelay' => max(1, min(60, (int) config::byKey('wallDelay', self::PLUGIN_ID, 2))),
			'wallOled' => max(0, min(1, (int) config::byKey('wallOled', self::PLUGIN_ID, 0))),
			'wallWidgets' => '[]',
		));
		$eq->save();
		DB::Prepare('UPDATE homeboard_tile SET eq_id = ? WHERE eq_id = 0', array((int) $eq->getId()));
	}

	/* Suppression des données à la désinstallation du plugin. */
	public static function dropTables() {
		DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_tile`');
		DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_room`');
		DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_board`');
		DB::getConnection()->exec('DROP TABLE IF EXISTS `homeboard_board_old`');
	}

	/* ============================================================
	 *  Tableaux de bord
	 * ============================================================ */

	/* Un tableau de bord est un ÉQUIPEMENT Jeedom (type homeboard) : il a donc
	   un nom, un objet parent, un état actif/visible, ses droits et sa page de
	   configuration comme n'importe quel équipement. Ses tuiles vivent dans
	   homeboard_tile.eq_id ; ses réglages de voile dans sa configuration. */

	private static function boardArray($_eq, $_defaultId = null) {
		$conf = $_eq->getConfiguration();
		$conf = is_array($conf) ? $conf : array();
		return array(
			'id' => (int) $_eq->getId(),
			'name' => (string) $_eq->getName(),
			'slug' => isset($conf['slug']) ? (string) $conf['slug'] : '',
			'object_id' => (int) $_eq->getObject_id(),
			'isEnable' => (int) $_eq->getIsEnable(),
			'isVisible' => (int) $_eq->getIsVisible(),
			'wallMode' => isset($conf['wallMode']) ? (int) $conf['wallMode'] : 0,
			'wallDelay' => isset($conf['wallDelay']) ? max(1, min(60, (int) $conf['wallDelay'])) : 2,
			'wallOled' => isset($conf['wallOled']) ? (int) $conf['wallOled'] : 0,
			'wallWidgets' => isset($conf['wallWidgets']) ? (string) $conf['wallWidgets'] : '[]',
			'isDefault' => ($_defaultId !== null && (int) $_eq->getId() === (int) $_defaultId) ? 1 : 0,
		);
	}

	/* Tous les tableaux, actifs ou non : l'administration doit pouvoir ouvrir
	   un tableau désactivé pour le corriger. Les appelants qui n'affichent que
	   le service (PWA) passent $_onlyEnable = true. */
	public static function boards($_onlyEnable = false) {
		if (homeboardState::$boardsCache === null) {
			homeboardState::$boardsCache = self::loadBoards();
		}
		if (!$_onlyEnable) {
			return homeboardState::$boardsCache;
		}
		$out = array();
		foreach (homeboardState::$boardsCache as $b) {
			if ((int) $b['isEnable'] === 1) {
				$out[] = $b;
			}
		}
		return $out;
	}

	/* Lecture réelle : une seule requête par requête HTTP. */
	private static function loadBoards() {
		$out = array();
		foreach (eqLogic::byType(self::PLUGIN_ID) as $eq) {
			$out[] = self::boardArray($eq);
		}
		usort($out, function ($a, $b) { return $a['id'] - $b['id']; });
		$default = self::defaultIdFrom($out);
		foreach ($out as $i => $b) {
			$out[$i]['isDefault'] = ($b['id'] === $default) ? 1 : 0;
		}
		return $out;
	}

	/* Tableau par défaut : le premier créé qui soit actif ; à défaut le premier
	   créé (pour que l'administration ait toujours quelque chose à montrer). */
	private static function defaultIdFrom($_boards) {
		foreach ($_boards as $b) {
			if ((int) $b['isEnable'] === 1) {
				return (int) $b['id'];
			}
		}
		return count($_boards) ? (int) $_boards[0]['id'] : 0;
	}

	public static function defaultBoard() {
		$all = self::boards();
		$id = self::defaultIdFrom($all);
		foreach ($all as $b) {
			if ((int) $b['id'] === $id) {
				return $b;
			}
		}
		return null;
	}

	/* Équipement Jeedom d'un tableau (droits, état). */
	public static function boardEq($_ref = null) {
		$b = self::board($_ref);
		if (!is_array($b)) {
			return null;
		}
		$eq = eqLogic::byId((int) $b['id']);
		if (!is_object($eq) || $eq->getEqType_name() !== self::PLUGIN_ID) {
			return null;
		}
		return $eq;
	}

	/* Les droits natifs de l'équipement portent sur le tableau lui-même : un
	   profil restreint sans droit « r » n'ouvre pas le tableau, même en
	   connaissant son adresse. */
	private static function assertBoardReadable($_where) {
		$eq = self::boardEq();
		if (!is_object($eq)) {
			return;
		}
		if (!$eq->hasRight('r')) {
			throw new Exception('Accès non autorisé à ce tableau de bord (' . $_where . ')');
		}
	}

	/* Résout un tableau par identifiant d'équipement ou par slug. Sans
	   argument : le tableau courant (paramètre « b »), sinon celui par défaut.
	   Une référence inconnue retombe sur le défaut : un lien périmé montre
	   encore quelque chose au lieu d'une page vide. */
	public static function board($_ref = null) {
		$ref = ($_ref === null || $_ref === '') ? self::currentBoardRef() : $_ref;
		if ($ref !== null && $ref !== '') {
			if (is_numeric($ref)) {
				foreach (self::boards() as $b) {
					if ($b['id'] === (int) $ref) {
						return $b;
					}
				}
			} else {
				foreach (self::boards() as $b) {
					if ($b['slug'] === (string) $ref) {
						return $b;
					}
				}
			}
		}
		return self::defaultBoard();
	}

	public static function boardId() {
		$b = self::board();
		return is_array($b) ? (int) $b['id'] : 0;
	}

	private static function currentBoardRef() {
		if (homeboardState::$boardRef !== null) {
			return homeboardState::$boardRef;
		}
		$ref = null;
		foreach (array('b', 'board') as $k) {
			if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') {
				$ref = $_REQUEST[$k];
				break;
			}
		}
		homeboardState::$boardRef = $ref;
		return homeboardState::$boardRef;
	}

	/* Force le tableau courant (tests, administration d'un autre tableau).
	   Les caches par tableau restent valides : rien à vider. */
	public static function useBoard($_ref) {
		homeboardState::$boardRef = $_ref;
		return self::board();
	}

	public static function slugify($_name) {
		/* mb_strtolower : strtolower() ne descend pas les majuscules accentuées
		   (É, À…), qui finissaient supprimées du slug. */
		$s = function_exists('mb_strtolower')
			? mb_strtolower(trim((string) $_name), 'UTF-8')
			: strtolower(trim((string) $_name));
		$s = strtr($s, array(
			'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'î' => 'i', 'ï' => 'i', 'í' => 'i',
			'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
			'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
			'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
		));
		$s = preg_replace('/[^a-z0-9]+/', '-', $s);
		$s = trim((string) $s, '-');
		$s = mb_substr($s, 0, 40);
		return $s === '' ? 'tableau' : $s;
	}

	private static function uniqueSlug($_base, $_exceptId = 0) {
		$base = self::slugify($_base);
		$slug = $base;
		$n = 1;
		while ($n < 60) {
			$taken = false;
			foreach (self::boards() as $b) {
				if ($b['slug'] === $slug && (int) $b['id'] !== (int) $_exceptId) {
					$taken = true;
					break;
				}
			}
			if (!$taken) {
				return $slug;
			}
			$n++;
			$slug = $base . '-' . $n;
		}
		return $base . '-' . substr((string) mt_rand(1000, 9999), 0, 4);
	}

	/* eqLogic::setConfiguration() prend une clé et une valeur : on boucle pour
	   poser un tableau entier. */
	private static function forgetBoards() {
		homeboardState::$boardsCache = null;
	}

	private static function setConfigurationArray($_eq, $_conf) {
		foreach ($_conf as $k => $v) {
			$_eq->setConfiguration($k, $v);
		}
	}

	/* Création programmatique (import, tests) : un équipement homeboard.
	   L'interface passe par le bouton « Ajouter » standard de Jeedom. */
	public static function createBoard($_data) {
		$name = trim((string) (isset($_data['name']) ? $_data['name'] : ''));
		if ($name === '') {
			throw new Exception('Le nom du tableau est obligatoire');
		}
		$eq = new self();
		$eq->setName(mb_substr($name, 0, self::LABEL_MAX));
		$eq->setEqType_name(self::PLUGIN_ID);
		$eq->setIsEnable(1);
		$eq->setIsVisible(1);
		self::setConfigurationArray($eq, array(
			'slug' => self::uniqueSlug($name),
			'wallMode' => 0,
			'wallDelay' => 2,
			'wallOled' => 0,
			'wallWidgets' => '[]',
		));
		$eq->save();
		return (int) $eq->getId();
	}

	/* Suppression : ses tuiles partent avec lui (preRemove), les pièces
	   restent. Le dernier tableau ne peut pas être supprimé. */
	public static function deleteBoard($_id) {
		$id = (int) $_id;
		$all = self::boards();
		if (count($all) <= 1) {
			throw new Exception('Impossible de supprimer le dernier tableau de bord');
		}
		$eq = eqLogic::byId($id);
		if (!is_object($eq) || $eq->getEqType_name() !== self::PLUGIN_ID) {
			throw new Exception('Tableau introuvable');
		}
		$eq->remove();
		return true;
	}

	/* Hooks Jeedom : le slug est posé au premier enregistrement et ne bouge
	   plus ensuite (les adresses installées restent valides). */
	public function preSave() {
		$conf = $this->getConfiguration();
		$conf = is_array($conf) ? $conf : array();
		if (empty($conf['slug'])) {
			$conf['slug'] = self::uniqueSlug($this->getName(), (int) $this->getId());
		}
		if (!isset($conf['wallMode']) || $conf['wallMode'] === '') {
			$conf['wallMode'] = 0;
		}
		if (!isset($conf['wallDelay']) || $conf['wallDelay'] === '') {
			$conf['wallDelay'] = 2;
		}
		if (!isset($conf['wallOled']) || $conf['wallOled'] === '') {
			$conf['wallOled'] = 0;
		}
		if (!isset($conf['wallWidgets'])) {
			$conf['wallWidgets'] = '[]';
		}
		self::setConfigurationArray($this, $conf);
		self::forgetBoards();
	}

	public function postSave() {
		self::forgetBoards();
		/* Un tableau désactivé dans Jeedom n'est pas servi : voir board(). */
	}

	/* Appelé par DB::remove() sur tous les chemins de suppression, y compris le
	   bouton standard de Jeedom : le dernier tableau ne peut pas partir.
	   La désinstallation du plugin passe aussi par là, mais Jeedom encadre
	   chaque suppression d'un try/catch : l'exception n'y bloque rien. */
	/* La liste est relue dans preRemove pour compter les autres tableaux :
	   on la vide de nouveau une fois la suppression faite, sinon la requête en
	   cours garderait le tableau supprimé dans son cache. */
	public function postRemove() {
		self::forgetBoards();
	}

	public function preRemove() {
		self::forgetBoards();
		$others = 0;
		foreach (self::boards() as $b) {
			if ((int) $b['id'] !== (int) $this->getId()) {
				$others++;
			}
		}
		if ($others === 0) {
			throw new Exception('Impossible de supprimer le dernier tableau de bord');
		}
		DB::Prepare('DELETE FROM homeboard_tile WHERE eq_id = ?', array((int) $this->getId()));
		return true;
	}

	public static function bySlug($_slug) {
		foreach (self::boards() as $b) {
			if ($b['slug'] === (string) $_slug) {
				return $b;
			}
		}
		return null;
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
		if (config::byKey('wallOled', self::PLUGIN_ID, null) === null) {
			config::save('wallOled', 0, self::PLUGIN_ID);
		}
	}

	public static function settings() {
		$b = self::board();
		return array(
			/* Réglages de la maison : communs à tous les tableaux. */
			'homeName' => (string) config::byKey('homeName', self::PLUGIN_ID, 'Maison'),
			'showClock' => (int) config::byKey('showClock', self::PLUGIN_ID, 1),
			'refresh' => max(2, min(30, (int) config::byKey('refresh', self::PLUGIN_ID, 3))),
			'boardId' => is_array($b) ? (int) $b['id'] : 0,
			'boardName' => is_array($b) ? (string) $b['name'] : '',
			'boardSlug' => is_array($b) ? (string) $b['slug'] : '',
			/* Réglages du voile : propres au tableau (le mur du salon ne
			   s'endort pas comme le téléphone). */
			'wallMode' => is_array($b) ? max(0, min(1, (int) $b['wallMode'])) : 0,
			'wallDelay' => is_array($b) ? max(1, min(60, (int) $b['wallDelay'])) : 2,
			'wallOled' => is_array($b) ? max(0, min(1, (int) $b['wallOled'])) : 0,
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
		$b = self::board();
		$raw = is_array($b) && isset($b['wallWidgets']) ? $b['wallWidgets'] : array();
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
		$name = mb_substr($name, 0, self::LABEL_MAX);
		$icon = isset($_data['icon']) ? trim((string) $_data['icon']) : '';
		if ($icon === '') {
			$icon = 'home';
		} elseif (!in_array($icon, self::iconList(), true)) {
			/* L'icône finit dans un attribut class : liste fermée. */
			$icon = 'home';
		}
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

	/* Tuiles du tableau courant (ou du tableau demandé). Aucune requête ne
	   doit lire homeboard_tile sans filtrer : c'est ce qui garantit qu'un
	   tableau ne voit ni n'exécute les tuiles d'un autre. */
	public static function tiles($_boardId = null) {
		$bid = ($_boardId === null) ? self::boardId() : (int) $_boardId;
		$rows = DB::Prepare('SELECT * FROM homeboard_tile WHERE eq_id = ? ORDER BY pos, id', array($bid), DB::FETCH_TYPE_ALL);
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
		/* Même limite que la colonne (191) et que saveRoom : un libellé trop
		   long était tronqué par MySQL sans le dire. */
		$label = mb_substr($label, 0, self::LABEL_MAX);
		$icon = isset($_data['icon']) ? trim((string) $_data['icon']) : '';
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
			/* Une tuile d'un autre tableau ne se modifie pas depuis celui-ci. */
			self::assertTileInBoard($id);
			DB::Prepare('UPDATE homeboard_tile SET room_id = ?, cmd_id = ?, scenario_id = ?, label = ?, kind = ?, icon = ?, size = ?, isFavorite = ?, pos = ? WHERE id = ?',
				array($roomId, $cmdId, $scenarioId, $label, $kind, $icon, $size, $fav, $pos, $id));
		} else {
			DB::Prepare('INSERT INTO homeboard_tile (eq_id, room_id, cmd_id, scenario_id, label, kind, icon, size, isFavorite, pos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				array(self::boardId(), $roomId, $cmdId, $scenarioId, $label, $kind, $icon, $size, $fav, $pos));
			$id = (int) DB::getLastInsertId();
		}
		return $id;
	}

	/* Une tuile n'existe que dans son tableau : toute écriture vérifie cette
	   appartenance, sinon un tableau pourrait modifier ou supprimer les tuiles
	   d'un autre en changeant l'identifiant envoyé. */
	private static function assertTileInBoard($_id) {
		$id = (int) $_id;
		$row = DB::Prepare('SELECT eq_id FROM homeboard_tile WHERE id = ?', array($id), DB::FETCH_TYPE_ROW);
		if (!is_array($row)) {
			throw new Exception('Tuile ' . $id . ' introuvable');
		}
		if ((int) $row['eq_id'] !== self::boardId()) {
			throw new Exception('Tuile ' . $id . ' : ce tableau de bord ne la contient pas');
		}
		return $row;
	}

	public static function deleteTile($_id) {
		self::assertTileInBoard($_id);
		DB::Prepare('DELETE FROM homeboard_tile WHERE id = ?', array((int) $_id));
	}

	/* Types de tuile autorisés : la valeur est écrite dans un attribut class
	   côté JavaScript, une chaîne libre serait une injection persistante. */
	public static function validKind($_kind) {
		return homeboardPure::validKind($_kind);
	}

	private static function roomExists($_id) {
		$row = DB::Prepare('SELECT id FROM homeboard_room WHERE id = ?', array((int) $_id), DB::FETCH_TYPE_ROW);
		return is_array($row) && !empty($row);
	}

	/* Enregistrement en masse (glisser-déposer, renommage) : chaque ligne
	   repasse par saveTile(), donc par exactement les mêmes contrôles que
	   l'enregistrement d'une tuile seule. Les champs absents d'une ligne
	   gardent leur valeur enregistrée — c'était le défaut : changer le type
	   en bouton ou scénario laissait les identifiants à zéro et la tuile
	   disparaissait du tableau de bord. Une ligne invalide est refusée seule,
	   le reste est enregistré, et l'appelant reçoit le détail. */
	public static function saveTilesBulk($_rows) {
		$rows = json_decode($_rows, true);
		if (!is_array($rows)) {
			throw new Exception('Données invalides');
		}
		if (count($rows) > 500) {
			throw new Exception('Trop de lignes à enregistrer (500 maximum)');
		}
		$stored = array();
		foreach (self::tiles() as $t) {
			$stored[(int) $t['id']] = $t;
		}
		$updated = 0;
		$errors = array();
		$db = DB::getConnection();
		$db->beginTransaction();
		try {
			foreach ($rows as $row) {
				if (!is_array($row) || empty($row['id'])) {
					continue;
				}
				$id = (int) $row['id'];
				if (!isset($stored[$id])) {
					$errors[] = 'Tuile ' . $id . ' introuvable';
					continue;
				}
				$base = $stored[$id];
				$merged = array('id' => $id);
				foreach (array('room_id', 'cmd_id', 'scenario_id', 'label', 'kind', 'icon', 'size', 'isFavorite', 'pos') as $k) {
					$merged[$k] = array_key_exists($k, $row) ? $row[$k] : $base[$k];
				}
				try {
					self::saveTile($merged);
					$updated++;
				} catch (Exception $e) {
					$errors[] = 'Tuile ' . $id . ' : ' . $e->getMessage();
				}
			}
			$db->commit();
		} catch (Exception $e) {
			$db->rollBack();
			throw $e;
		} catch (Throwable $e) {
			$db->rollBack();
			throw $e;
		}
		return array('updated' => $updated, 'rejected' => count($errors), 'errors' => $errors);
	}

	/* ============================================================
	 *  Typage des tuiles
	 * ============================================================ */

	public static function kindFromGeneric($_gt, $_subType = '') {
		return homeboardPure::kindFromGeneric($_gt, $_subType);
	}

	public static function defaultIcon($_kind) {
		return homeboardPure::defaultIcon($_kind);
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
			$caps['briMax'] = homeboardPure::alignMax($rng[0], $rng[1], $rng[2]);
			$caps['briStep'] = $rng[2];
		} elseif ($_kind === 'thermostat') {
			$caps['state'] = $id($find(array('THERMOSTAT_STATE_NAME'), 'info'));
			$caps['temp'] = $id($find(array('THERMOSTAT_TEMPERATURE'), 'info'));
			$caps['setpoint'] = $id($find(array('THERMOSTAT_SETPOINT'), 'info'));
			$caps['setSetpoint'] = $id($find(array('THERMOSTAT_SET_SETPOINT'), 'action'));
			$rng = $rangeOf($find(array('THERMOSTAT_SET_SETPOINT'), 'action'), 5, 30, 0.5);
			$caps['spMin'] = $rng[0];
			/* Borne haute ramenée sur la grille du pas : sinon l'interface
			   annonce une valeur que le curseur ne peut pas atteindre. */
			$caps['spMax'] = homeboardPure::alignMax($rng[0], $rng[1], $rng[2]);
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

	/* Tuiles réellement accessibles à l'utilisateur connecté : celles d'une
	   pièce visible, dont la commande ou le scénario existe et dont
	   l'équipement autorise la lecture. C'est exactement ce que getLayout()
	   affiche. S'en servir pour les autorisations évite qu'une tuile masquée
	   (pièce cachée, ou équipement sans droit de lecture) reste exécutable
	   par l'API alors qu'elle n'existe plus à l'écran. */
	public static function accessibleTiles($_reload = false) {
		/* Le tableau lui-même a des droits : sans « r » sur l'équipement, ses
		   tuiles ne sont ni affichées ni exécutables. */
		self::assertBoardReadable('tuiles');
		$bid = self::boardId();
		if (isset(homeboardState::$tilesCache[$bid]) && !$_reload) {
			return homeboardState::$tilesCache[$bid];
		}
		$rooms = array();
		foreach (self::rooms(false) as $r) {
			$rooms[(int) $r['id']] = true;
		}
		$out = array();
		foreach (self::tiles() as $row) {
			$roomId = (int) $row['room_id'];
			/* Une tuile sans pièce n'apparaît nulle part : elle n'ouvre aucun droit. */
			if ($roomId <= 0 || !isset($rooms[$roomId])) {
				continue;
			}
			$kind = self::validKind(isset($row['kind']) ? $row['kind'] : 'auto');
			if ($kind === 'scene') {
				$sc = scenario::byId((int) $row['scenario_id']);
				if (!is_object($sc) || (int) $sc->getIsActive() !== 1) {
					continue;
				}
				if (method_exists($sc, 'hasRight') && !$sc->hasRight('r')) {
					continue;
				}
				$out[] = $row;
				continue;
			}
			$cmd = cmd::byId((int) $row['cmd_id']);
			if (!is_object($cmd)) {
				continue;
			}
			$eq = $cmd->getEqLogic();
			if (is_object($eq) && method_exists($eq, 'hasRight') && !$eq->hasRight('r')) {
				continue;
			}
			$out[] = $row;
		}
		homeboardState::$tilesCache[$bid] = $out;
		return $out;
	}

	/* Scénario présent dans une tuile de scène accessible : c'est la seule
	   permission qui autorise Homeboard à le lancer ou à lire son état. Un
	   compte disposant du droit Jeedom « x » sur tous les scénarios ne doit pas
	   pouvoir lancer n'importe lequel en changeant l'identifiant de la requête. */
	private static function sceneTiled($_scenarioId) {
		$id = (int) $_scenarioId;
		if ($id <= 0) {
			return false;
		}
		foreach (self::accessibleTiles() as $row) {
			if (self::validKind(isset($row['kind']) ? $row['kind'] : 'auto') === 'scene'
				&& (int) $row['scenario_id'] === $id) {
				return true;
			}
		}
		return false;
	}

	public static function runScenario($_id) {
		$id = (int) $_id;
		if (!self::sceneTiled($id)) {
			throw new Exception('403 Scénario non autorisé par Homeboard');
		}
		$sc = scenario::byId($id);
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
			if ($id <= 0 || !self::sceneTiled($id)) {
				continue;
			}
			$sc = scenario::byId($id);
			if (!is_object($sc)) {
				continue;
			}
			/* L'état d'un scénario est une information : droit de lecture
			   obligatoire, même quand la tuile existe. */
			if (method_exists($sc, 'hasRight') && !$sc->hasRight('r')) {
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
		$b = self::board();
		if (is_array($b) && isset($b['isEnable']) && (int) $b['isEnable'] !== 1) {
			throw new Exception('Ce tableau de bord est désactivé dans Jeedom');
		}
		self::assertBoardReadable('layout');
		$rooms = self::rooms(false);
		/* Source unique : les tuiles accessibles. Ce que l'écran affiche et ce
		   que l'API autorise doivent être la même liste, sinon un bouton peut
		   apparaître sans pouvoir être exécuté (ou l'inverse). Une tuile sans
		   pièce, ou d'une pièce masquée, n'apparaît donc nulle part — et
		   n'ouvre aucun droit. */
		$tiles = self::accessibleTiles();
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
			/* Fraîcheur : on prend la date du dernier **événement reçu**
			   (collectDate), pas celle du dernier changement de valeur.
			   Jeedom ne met valueDate à jour que si la valeur change : une
			   sonde qui renvoie 21 °C toutes les cinq minutes gardait donc une
			   valueDate vieille de plusieurs heures et passait pour morte. */
			$stamp = 0;
			if (method_exists($cmd, 'getCollectDate')) {
				$date = (string) $cmd->getCollectDate();
				if ($date === '' && method_exists($cmd, 'getValueDate')) {
					$date = (string) $cmd->getValueDate();
				}
				if ($date !== '') {
					$parsed = strtotime($date);
					if ($parsed) {
						$stamp = (int) $parsed;
					}
				}
			}
			$out[$id] = array(
				'value' => $value,
				'display' => self::formatValue($cmd, $value),
				'unit' => (string) $cmd->getUnite(),
				'ts' => $stamp,
				/* Mesure d'ambiance (température, humidité…) : elle est censée
				   arriver régulièrement, donc une valeur ancienne est anormale.
				   Une puissance ou un état, non : ils ne changent que si
				   l'appareil change, et leur âge n'est qu'informatif. */
				'periodic' => self::isPeriodic($cmd) ? 1 : 0,
			);
		}
		return $out;
	}

	/* ============================================================
	 *  Exécution
	 * ============================================================ */

	/* Commandes d'action que Homeboard a le droit d'exécuter : celles que ses
	   propres tuiles exposent (boutons, et capacités dérivées de la commande
	   pilote — marche/arrêt, luminosité, consigne, modes, volets). Sans cette
	   liste, un compte authentifié pouvait appeler exec() avec l'identifiant
	   d'une commande d'action quelconque de la maison. */
	public static function allowedActions($_reload = false) {
		$bid = self::boardId();
		if (isset(homeboardState::$actionsCache[$bid]) && !$_reload) {
			return homeboardState::$actionsCache[$bid];
		}
		$keys = array('on', 'off', 'toggle', 'briSet', 'colorSet', 'setSetpoint', 'up', 'down', 'stop', 'slider');
		$out = array();
		foreach (self::accessibleTiles() as $row) {
			$kind = self::validKind(isset($row['kind']) ? $row['kind'] : 'auto');
			if ($kind === 'scene') {
				continue;
			}
			$cmd = cmd::byId((int) $row['cmd_id']);
			if (!is_object($cmd)) {
				continue;
			}
			if ($kind === 'button') {
				if ($cmd->getType() === 'action') {
					$out[(int) $cmd->getId()] = true;
				}
				continue;
			}
			if ($cmd->getType() !== 'info') {
				continue;
			}
			/* Une tuile « auto » est typée à l'affichage (kindFromGeneric) : la
			   liste des commandes autorisées doit résoudre le même type, sinon
			   toutes les tuiles automatiques perdraient leurs boutons. */
			if ($kind === 'auto') {
				$kind = self::kindFromGeneric($cmd->getGeneric_type(), $cmd->getSubType());
			}
			$caps = self::caps($cmd, $kind);
			foreach ($keys as $k) {
				if (empty($caps[$k])) {
					continue;
				}
				$id = (int) $caps[$k];
				$action = cmd::byId($id);
				if (is_object($action) && $action->getType() === 'action') {
					$out[$id] = true;
				}
			}
			foreach ((array) (isset($caps['modes']) ? $caps['modes'] : array()) as $m) {
				if (!empty($m['id'])) {
					$out[(int) $m['id']] = true;
				}
			}
		}
		homeboardState::$actionsCache[$bid] = $out;
		return $out;
	}

	private static function isPeriodic($_cmd) {
		return homeboardPure::isPeriodic($_cmd->getGeneric_type(), $_cmd->getUnite());
	}

	public static function exec($_cmdId, $_value) {
		$cmdId = (int) $_cmdId;
		$allowed = self::allowedActions();
		if ($cmdId <= 0 || !isset($allowed[$cmdId])) {
			throw new Exception('403 Commande non autorisée par Homeboard');
		}
		$cmd = cmd::byId($cmdId);
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
				/* Bornes et pas respectés côté serveur : l'interface arrondit
				   déjà, mais elle n'est pas la sécurité. */
				$_value = homeboardPure::clampStep(
					$_value,
					$cmd->getConfiguration('minValue', ''),
					$cmd->getConfiguration('maxValue', ''),
					$cmd->getConfiguration('step', '')
				);
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
		foreach (DB::Prepare('SELECT cmd_id FROM homeboard_tile WHERE eq_id = ?', array(self::boardId()), DB::FETCH_TYPE_ALL) as $t) {
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
			'board' => self::board(),
			'boards' => self::boards(),
			'rooms' => $rooms,
			'tiles' => $tiles,
			'icons' => self::iconList(),
		);
	}
}

class homeboardCmd extends cmd {
}

/* État de requête (tableau courant, caches). Classe séparée volontairement :
   eqLogic::byType() déduit ses colonnes des propriétés de la classe, une
   propriété statique déclarée dans homeboard serait lue comme un champ SQL. */
class homeboardState {
	public static $boardRef = null;
	/* Liste des tableaux pour la requête en cours (invalidée à chaque
	   enregistrement ou suppression). */
	public static $boardsCache = null;
	/* Injecté par les tests pour simuler une erreur SQL sur la détection de
	   l'ancienne table. Toujours null en production. */
	public static $legacyProbe = null;
	public static $tilesCache = array();
	public static $actionsCache = array();
}
