<?php

/* This file is part of Jeedom. */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function homeboard_install() {
	homeboard::createTables();
	homeboard::installDefaults();
	log::add('homeboard', 'info', 'Installation du plugin Homeboard');
}

function homeboard_update() {
	homeboard::createTables();
	homeboard::installDefaults();
	log::add('homeboard', 'info', 'Mise à jour du plugin Homeboard');
}

function homeboard_remove() {
	/* Les données (pièces et tuiles) sont conservées : une réinstallation
	   retrouve la configuration. Suppression manuelle des tables possible. */
	log::add('homeboard', 'info', 'Désinstallation du plugin Homeboard (données conservées)');
}
