<?php
/* This file is part of Jeedom. */

/*
 * Contrôle anti-fuite avant publication.
 *
 * Le dépôt est public : aucun fichier livré ne doit contenir d'adresse privée,
 * de domaine personnel, de clé privée ou de secret en clair. Ce script ne
 * remplace pas un scanner de secrets complet (gitleaks, trufflehog…) : il
 * couvre les cas qui nous concernent, sans dépendance et sans réseau.
 *
 *   php tools/scan-secrets.php        # depuis la racine du plugin
 *
 * Sortie 0 si tout est propre, 1 si un motif est trouvé (les lignes sont
 * affichées avec leur numéro).
 */

$root = dirname(__DIR__);
$self = __FILE__;

$skipDirs = array('.git', '.github', 'node_modules', 'vendor', 'screenshots');
$exts = array('php', 'js', 'css', 'json', 'md', 'yml', 'yaml', 'conf', 'sh', 'txt');

$patterns = array(
	'adresse privée' => '/\b(?:10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(?:1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3})\b/',
	/* Hôtes et réseaux personnels, pas le nom de famille de l'auteur (qui est
	   légitimement cité dans info.json). */
	'domaine personnel' => '/\.jeedom\.link|bellier[0-9._-]|\.bellier\./i',
	'clé privée' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
	'secret en clair' => '/\b(?:api[_-]?key|apikey|secret|passwd|password|token)\b\s*[:=]\s*["\']([^"\']{6,})["\']/i',
);

$found = 0;
$scanned = 0;

function hbScanDir($_dir, $_prefix = '')
{
	global $skipDirs, $exts, $patterns, $self, $found, $scanned;
	$entries = @scandir($_dir);
	if (!is_array($entries)) {
		return;
	}
	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$path = $_dir . '/' . $entry;
		$rel = ltrim($_prefix . '/' . $entry, '/');
		if (is_dir($path)) {
			if (!in_array($entry, $skipDirs, true)) {
				hbScanDir($path, $rel);
			}
			continue;
		}
		if ($path === $self) {
			continue;
		}
		$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
		if (!in_array($ext, $exts, true)) {
			continue;
		}
		$lines = @file($path);
		if (!is_array($lines)) {
			continue;
		}
		$scanned++;
		foreach ($lines as $i => $line) {
			foreach ($patterns as $label => $re) {
				if (preg_match($re, $line)) {
					$found++;
					echo $rel . ':' . ($i + 1) . ' [' . $label . '] ' . trim($line) . "\n";
					break;
				}
			}
		}
	}
}

hbScanDir($root);

echo "\n" . $scanned . ' fichier(s) analysé(s), ' . $found . " motif(s) sensible(s)\n";
exit($found === 0 ? 0 : 1);
