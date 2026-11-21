<?php
/* Homeboard — application autonome (PWA).
 * Cette page sert le shell de l'application ; toutes les données passent
 * par core/ajax/homeboard.ajax.php (session Jeedom obligatoire).
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

/* Pas de session Jeedom : on sert quand même la coquille (neutre, sans
   donnée) et la page affiche un message explicite avec un lien vers Jeedom.
   Un renvoi silencieux vers le dashboard laissait croire que l'adresse du
   tableau était cassée, alors qu'il manquait seulement la session sur ce
   domaine (l'IP locale et le DNS Jeedom sont deux origines distinctes). */
$hbAuth = isConnect() ? '1' : '0';

$homeName = config::byKey('homeName', 'homeboard', 'Maison');
$cssVer = @filemtime(dirname(__FILE__) . '/../desktop/css/homeboard.css');
$jsVer = @filemtime(dirname(__FILE__) . '/../desktop/js/homeboard.js');
/* Le shell ne contient aucune information liée à la session : il est donc
   identique pour tous les profils et peut être mis en cache sans risque. Le
   lien de configuration est toujours présent, mais la page de configuration
   exige elle-même le profil administrateur. */
$cfgUrl = '../../../index.php?v=d&p=plugin&id=homeboard';
/* Tableau de bord demandé (?b=slug). Une valeur inconnue n'est pas une erreur :
   l'API retombe sur le tableau par défaut, l'affichage reste cohérent. */
$boardSlug = '';
if (isset($_GET['b']) && $_GET['b'] !== '') {
	/* Un tableau est un équipement Jeedom : on résout par slug ou par id. Une
	   valeur inconnue laisse la main au tableau par défaut. */
	$hbBoard = homeboard::board((string) $_GET['b']);
	if (is_array($hbBoard) && !empty($hbBoard['slug'])) {
		$boardSlug = (string) $hbBoard['slug'];
	}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<title><?php echo htmlspecialchars($homeName); ?></title>
	<meta name="theme-color" content="#0a0a0b">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($homeName); ?>">
	<link rel="manifest" href="./manifest.php">
	<link rel="apple-touch-icon" href="./icons/icon-192.png">
	<link rel="icon" href="./icons/icon-192.png">
	<link rel="stylesheet" href="../../../core/php/getResource.php?file=plugins/homeboard/desktop/css/homeboard.css&v=<?php echo $cssVer; ?>">
</head>
<body class="hb-standalone">
	<div id="hb-root"
	     data-api="../core/ajax/homeboard.ajax.php"
	     data-board="<?php echo htmlspecialchars($boardSlug); ?>"
	     data-auth="<?php echo $hbAuth; ?>"
	     data-login="../../../index.php?v=d"
	     data-config="<?php echo htmlspecialchars($cfgUrl); ?>"></div>
	<script src="../../../core/php/getResource.php?file=plugins/homeboard/desktop/js/homeboard.js&v=<?php echo $jsVer; ?>"></script>
</body>
</html>
