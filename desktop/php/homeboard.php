<?php
/* This file is part of Jeedom. */

if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

$hbConfigUrl = isConnect('admin')
	? 'index.php?v=d&p=plugin&id=' . homeboard::PLUGIN_ID
	: '';

/* Plein écran par défaut : la page occupe tout l'écran, sans barre Jeedom,
   comme un design en &fullscreen=1. Ajouter &fullscreen=0 à l'URL pour
   retrouver l'habillage Jeedom. */
$hbFull = (string) init('fullscreen', '1') !== '0';
$hbExitUrl = $hbFull ? 'index.php?v=d&p=homeboard&m=homeboard&fullscreen=0' : '';
?>

<?php include_file('desktop', 'homeboard', 'css', homeboard::PLUGIN_ID); ?>

<div id="hb-root" class="hb-app-root"
     data-api="plugins/<?php echo homeboard::PLUGIN_ID; ?>/core/ajax/homeboard.ajax.php"
     data-config="<?php echo $hbConfigUrl; ?>"
     data-exit="<?php echo $hbExitUrl; ?>"></div>

<style>
	/* La page du plugin occupe toute la largeur utile, sans marge interne */
	#div_mainContainer > .tab-content > .tab-pane.active > #hb-root,
	#hb-root.hb-app-root {
		margin: -15px -15px -20px -15px;
	}
</style>

<?php if ($hbFull) { ?>
<style>
	/* Plein écran : on retire l'habillage Jeedom (barre du haut + gouttières) */
	header#jeedomMenuBar,
	#jeedomMenuBar {
		display: none !important;
	}

	body {
		padding-top: 0 !important;
	}

	#div_mainContainer {
		margin: 0 !important;
		padding: 0 !important;
		top: auto !important;
		position: static !important;
		height: auto !important;
		overflow: visible !important;
	}

	#div_pageContainer {
		margin: 0 !important;
		padding: 0 !important;
	}

	#hb-root.hb-app-root,
	#div_mainContainer > .tab-content > .tab-pane.active > #hb-root {
		margin: 0 !important;
		width: 100% !important;
	}
</style>
<?php } ?>

<?php include_file('desktop', 'homeboard', 'js', homeboard::PLUGIN_ID); ?>
