<?php

/* Homeboard — logique pure.
 *
 * Ce fichier ne dépend ni de Jeedom ni de la base : il est inclus par la classe
 * du plugin et par les tests, y compris en intégration continue où aucun Jeedom
 * n'est disponible. Toute règle vérifiable sans base doit vivre ici plutôt que
 * dans homeboard.class.php.
 */

class homeboardPure {

	/* Longueur maximale d'un libellé de tuile ou de pièce (colonne varchar). */
	const LABEL_MAX = 191;

	/* Types de tuile admis, dans l'ordre où l'interface les propose. */
	const KINDS = array('auto', 'light', 'thermostat', 'sensor', 'switch', 'shutter', 'camera', 'button', 'scene');

	/* Types de tuile autorisés : la valeur est écrite dans un attribut class
	   côté JavaScript, une chaîne libre serait une injection persistante. */
	public static function validKind($_kind) {
		$k = trim((string) $_kind);
		return in_array($k, self::KINDS, true) ? $k : 'auto';
	}

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

	/* Une mesure d'ambiance est censée remonter régulièrement ; une puissance
	   ou un état ne changent que lorsque l'appareil change. Seules les
	   premières peuvent donc être signalées comme périmées. */
	public static function isPeriodic($_genericType, $_unit = '') {
		$gt = strtoupper(trim((string) $_genericType));
		if ($gt === '') {
			$unit = trim((string) $_unit);
			return in_array($unit, array('°C', '°F', '%', 'hPa', 'ppm', 'lx'), true);
		}
		foreach (array('TEMPERATURE', 'HUMIDITY', 'PRESSURE', 'CO2', 'AIR_QUALITY', 'NOISE', 'LUMINOSITY', 'UV') as $needle) {
			if (strpos($gt, $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	/* Libellé borné et nettoyé : la colonne accepte 191 caractères, MySQL
	   tronquait le reste sans le dire. */
	public static function normalizeLabel($_label) {
		$label = trim((string) $_label);
		return mb_substr($label, 0, self::LABEL_MAX);
	}

	/* Port effectif d'une URL : un port explicite égal au port par défaut doit
	   se comporter exactement comme son absence. */
	public static function effectivePort($_scheme, $_port = null) {
		if ($_port !== null && $_port !== '' && (int) $_port > 0) {
			return (int) $_port;
		}
		return strtolower((string) $_scheme) === 'https' ? 443 : 80;
	}

	/* Comparaison stricte d'une origine avec la requête : schéma, hôte et port
	   effectif. Utilisée par le contrôle CSRF ; renvoie false dès qu'un des
	   éléments manque, pour que l'appelant refuse par défaut. */
	public static function originMatches($_reqScheme, $_reqHost, $_reqPort, $_origin) {
		$origin = trim((string) $_origin);
		if ($origin === '') {
			return false;
		}
		$o = parse_url($origin);
		if (!is_array($o) || !isset($o['scheme']) || !isset($o['host'])) {
			return false;
		}
		$oscheme = strtolower((string) $o['scheme']);
		$ohost = strtolower((string) $o['host']);
		$rscheme = strtolower((string) $_reqScheme);
		$rhost = strtolower((string) $_reqHost);
		if ($ohost === '' || $rhost === '' || $oscheme === '' || $rscheme === '') {
			return false;
		}
		if ($ohost !== $rhost || $oscheme !== $rscheme) {
			return false;
		}
		$oport = self::effectivePort($oscheme, isset($o['port']) ? $o['port'] : null);
		$rport = self::effectivePort($rscheme, $_reqPort);
		return $oport === $rport;
	}

	/* Dernier cran réellement atteignable entre min et max pour un pas donné :
	   avec min=5, max=10 et pas=3, la borne haute affichée doit être 8, sinon
	   le curseur ne peut jamais atteindre la valeur annoncée. */
	public static function alignMax($_min, $_max, $_step) {
		if (!is_numeric($_max)) {
			return $_max;
		}
		$max = (float) $_max;
		if (!is_numeric($_step) || (float) $_step <= 0) {
			return $max;
		}
		$step = (float) $_step;
		$base = is_numeric($_min) ? (float) $_min : 0.0;
		if ($max <= $base) {
			return round($max, 4);
		}
		return round($base + floor(($max - $base) / $step) * $step, 4);
	}

	/* Valeur d'un curseur ramenée dans les bornes et alignée sur le pas, côté
	   serveur : l'interface arrondit déjà, mais elle n'est pas la sécurité.
	   La borne haute doit rester sur la grille du pas : avec min=5, max=10 et
	   pas=3, les valeurs possibles sont 5 et 8 — borner 11 à 10 donnerait une
	   valeur que l'appareil n'accepte pas. */
	public static function clampStep($_value, $_min, $_max, $_step) {
		if (!is_numeric($_value)) {
			return null;
		}
		$v = (float) $_value;
		$min = is_numeric($_min) ? (float) $_min : null;
		$max = is_numeric($_max) ? (float) $_max : null;
		if ($min !== null && $v < $min) {
			$v = $min;
		}
		if ($max !== null && $v > $max) {
			$v = $max;
		}
		if (is_numeric($_step) && (float) $_step > 0) {
			$step = (float) $_step;
			$base = ($min !== null) ? $min : 0.0;
			$v = $base + round(($v - $base) / $step) * $step;
			if ($max !== null && $v > $max) {
				/* Dernier cran réellement dans les bornes. */
				$v = $base + floor(($max - $base) / $step) * $step;
			}
			if ($min !== null && $v < $min) {
				$v = $base + ceil(($min - $base) / $step) * $step;
			}
			$v = round($v, 4);
		}
		return $v;
	}
}
