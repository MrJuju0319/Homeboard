<?php
/* Homeboard — manifeste PWA.
 * Généré en PHP car le serveur web de Jeedom refuse les fichiers .json
 * dans /plugins. Le nom de l'application suit le réglage du plugin.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

$name = 'Maison';
if (isConnect()) {
	$configured = trim((string) config::byKey('homeName', 'homeboard', ''));
	if ($configured !== '') {
		$name = $configured;
	}
}

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode(array(
	'name' => $name,
	'short_name' => $name,
	'description' => 'Tableau de bord domotique Jeedom',
	'lang' => 'fr',
	'start_url' => './index.php',
	'scope' => './',
	'display' => 'standalone',
	'display_override' => array('standalone', 'minimal-ui'),
	'orientation' => 'any',
	'background_color' => '#0a0a0b',
	'theme_color' => '#0a0a0b',
	'icons' => array(
		array('src' => './icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'),
		array('src' => './icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'),
		array('src' => './icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'),
	),
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
