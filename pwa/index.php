<?php
/* Homeboard — application autonome (PWA).
 * Cette page sert le shell de l'application ; toutes les données passent
 * par core/ajax/homeboard.ajax.php (session Jeedom obligatoire).
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

if (!isConnect()) {
	header('Location: ../../../index.php?v=d');
	exit;
}

$homeName = config::byKey('homeName', 'homeboard', 'Maison');
$cssVer = @filemtime(dirname(__FILE__) . '/../desktop/css/homeboard.css');
$jsVer = @filemtime(dirname(__FILE__) . '/../desktop/js/homeboard.js');
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
	<style>
		html, body { margin: 0; padding: 0; background: #0a0a0b; }
		body { overscroll-behavior-y: none; }
	</style>
</head>
<body class="hb-standalone">
	<div id="hb-root"
	     data-api="../core/ajax/homeboard.ajax.php"
	     data-config="../../../index.php?v=d&amp;p=plugin&amp;id=homeboard"></div>
	<script src="../../../core/php/getResource.php?file=plugins/homeboard/desktop/js/homeboard.js&v=<?php echo $jsVer; ?>"></script>
	<script>
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', function () {
				navigator.serviceWorker.register('./sw.js', { scope: './' }).catch(function () {});
			});
		}
	</script>
</body>
</html>
