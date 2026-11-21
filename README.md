# Homeboard — tableau de bord domotique pour Jeedom

Un tableau de bord façon **Maison** (Apple Home / Google Home) : pièces, tuiles de
commandes, application mobile installable (PWA). Aucun démon, aucune dépendance,
aucun build : PHP + JS natif.

## Fonctionnalités

- **Pièces** = onglets du tableau de bord (icône, ordre, visibilité).
- **Tuiles** pilotées par une seule commande Jeedom ; le plugin retrouve seul les
  commandes sœurs de l'équipement (on/off, variation, consigne, modes, volet).
- Types reconnus automatiquement : lumière (avec curseur de luminosité),
  thermostat (température, consigne ±0,5 °C, modes), sonde, prise/interrupteur,
  volet, caméra.
- **Graphiques** : vue dédiée (une carte par commande historisée, sparkline 24 h)
  et feuille de détail au clic — courbe 24 h / 7 j / 30 j, min · moyenne · max ·
  dernière valeur. Un clic sur une tuile « sonde » ouvre directement sa courbe.
- **Temps réel** : rafraîchissement configurable (3 s par défaut), mise à jour
  optimiste au clic (confirmation par le retour d'état), rendu incrémental
  (seules les tuiles dont la valeur change sont redessinées).
- **Bornes réelles** : les plages (min/max/pas) des thermostats et des
  variateurs sont lues dans la configuration des commandes Jeedom (repli
  5–30 °C / 0,5 °C et 0–100). Sans consigne connue, les boutons +/− refusent
  d'envoyer une valeur devinée.
- **Import** : les commandes déjà tuilées sont ignorées côté serveur (compteur
  « déjà présente(s) ») et la pièce retenue par l'analyse est conservée.
- **Responsive** : 2 colonnes sur téléphone, 4 sur tablette, 6 sur écran 16".
  Navigation par chips sur mobile, barre latérale au-delà de 1100 px.
- **PWA** : `plugins/homeboard/pwa/index.php` s'installe sur l'écran d'accueil
  (manifeste + service worker), plein écran, sans barre d'adresse.

## Installation

1. Copier le dossier `homeboard/` dans `/var/www/html/plugins/`, propriétaire
   `www-data:www-data`.
2. Plugins → Gestion des plugins → Homeboard → **Activer**.
3. Ouvrir la configuration du plugin, section **Import automatique** :
   « Analyser mes équipements » liste une tuile par appareil avec le type déjà
   deviné. Décochez ce que vous ne voulez pas, changez le **type de tuile** ou le
   libellé ligne par ligne, puis « Importer la sélection » : les pièces
   manquantes sont créées automatiquement (icône déduite du nom).
4. Ajuster ensuite dans **Pièces** et **Tuiles** (ordre, taille, favori, icône,
   type de chaque commande).

## Application mobile

- Ouvrir `plugins/homeboard/pwa/index.php` sur le téléphone, puis « Ajouter à
  l'écran d'accueil » (Safari iOS / Chrome Android) : plein écran, sans barre
  d'adresse. Le service worker met les assets en cache ; les valeurs restent
  temps réel via l'API Jeedom (session obligatoire, donc sur le LAN ou via le
  VPN / le reverse proxy).
- Page Jeedom : `index.php?v=d&p=homeboard&m=homeboard` s'affiche **en plein
  écran** (barre Jeedom masquée, comme un design en `&fullscreen=1`). Le bouton
  ↙ en haut à droite rend l'habillage Jeedom, et l'URL `…&fullscreen=0` fait de
  même de façon permanente.

- En **HTTPS**, le service worker met le shell en cache (démarrage instantané,
  écran d'erreur propre hors ligne). En HTTP simple, l'ajout à l'écran d'accueil
  fonctionne mais sans cache.
- **Accès par le DNS Jeedom** (`https://<box>.eu.jeedom.link/plugins/homeboard/pwa/index.php`) :
  c'est la voie recommandée, une seule origine partout. Elle exige une CSP
  dédiée, car la CSP globale de Jeedom (`/etc/apache2/conf-available/security.conf`)
  déclare `worker-src blob:` **sans `'self'`** : aucun service worker ne peut
  s'enregistrer, donc pas d'installation PWA. Le plugin fournit
  `plugin_info/homeboard-pwa.conf` à copier dans `/etc/apache2/conf-available/`
  puis `a2enconf homeboard-pwa && systemctl reload apache2`. La règle ne touche
  que `/plugins/homeboard/pwa/` ; pour revenir en arrière :
  `a2disconf homeboard-pwa && systemctl reload apache2`.

## Tactile

- **Navigation basse** sur téléphone et tablette (barre latérale seulement au-delà
  de 1100 px) : « Tout » ancré à gauche, « Graphiques » à droite, les pièces
  défilent entre les deux avec un fondu sur les bords.
- **Suivi du défilement** : la pièce visible en haut de l'écran s'éclaire dans la
  barre et la bande défile automatiquement pour la recentrer. Un tap sur une
  pièce filtre la vue ; « Tout » revient à l'ensemble.
- **Appui court** sur une tuile = on/off. **Appui long** (480 ms) = feuille de
  détail : luminosité + préréglages 20/50/75/100 % (lumière), consigne et modes
  en gros boutons (thermostat), ouvrir/stop/fermer (volet), courbe (sonde).
- Toutes les cibles font au moins 44 px : pilules de mode en sélecteur segmenté,
  +/− à 44 px, curseur avec piste de 10 px et pouce de 28 px.
- Retour d'appui immédiat (échelle + luminosité) et vibration courte sur Android ;
  les actions basculent en optimiste, la confirmation arrive ensuite.
- Le curseur de luminosité n'est plus dans la tuile : sur un écran tactile il se
  touchait en faisant défiler la page.
- `-webkit-touch-callout: none` et `user-select: none` sur les tuiles pour éviter
  le menu contextuel d'iOS lors d'un appui long.

## Sécurité

- Toutes les actions d'écriture et `exec()` passent en **POST** ; les lectures
  (`layout`, `values`, `history`, `scan`, `searchCmd`, `adminData`) sont les
  seules autorisées en GET. `core/ajax/homeboard.ajax.php` appelle `ajax::init()`,
  qui refuse toute autre action demandée en GET.
- En complément (Jeedom 4.4+ n'expose plus de jeton CSRF), chaque écriture
  vérifie l'origine : `Sec-Fetch-Site` doit valoir `same-origin` et l'en-tête
  `Origin`, quand il est présent, doit correspondre à l'hôte. Une page tierce ne
  peut donc pas déclencher un équipement via une session ouverte.
- La commande pilote d'une tuile doit être une commande **d'information** :
  `saveTile()` refuse une commande d'action, `adminData()` et `buildTile()`
  n'exécutent jamais une action (sinon l'action partait à l'affichage).
- Les valeurs exécutées sont validées côté serveur : bornes `minValue`/`maxValue`
  pour un slider, liste `listValue` pour un select, format `#RRGGBB` pour une
  couleur, longueur bornée pour un message.
- `kind`, `icon` et `size` sont restreints à des listes fermées côté PHP, et le
  type de tuile est écrit dans un attribut `class` via une valeur filtrée.
- Les écritures d'administration exigent `isConnect('admin')` ; l'exécution
  vérifie `$eqLogic->hasRight('x')` et l'affichage filtre sur `hasRight('r')`.
  Sans droit d'exécution, la tuile est grisée et la commande ne part pas.
- La CSP de la PWA est stricte (aucun script ni style inline, pas d'`eval`,
  `object-src 'none'`, `form-action 'self'`) : le peu de style dynamique passe
  par le CSSOM.
- Le service worker ne touche qu'aux caches `homeboard-*`, ne lit que le sien,
  n'intercepte que les URL du plugin et ne met jamais en cache une réponse
  redirigée (session expirée).

## Notes techniques

- Le serveur web de Jeedom refuse l'accès direct aux `.js` de
  `plugins/*/desktop/js` et aux `.json` : la page PWA charge donc le JS via
  `core/php/getResource.php` et le manifeste est généré par `pwa/manifest.php`.
- Les actions sans type générique (fréquent sur les équipements virtuels) sont
  reconnues par leur nom (`On` / `Off` / `Allumer` / `Éteindre`…).
- `tools/deploy.sh` copie le plugin sur une instance Jeedom distante :
  `JEEDOM_HOST=root@mon-jeedom.local ./tools/deploy.sh` (ou un fichier
  `.deploy.env` non versionné contenant `JEEDOM_HOST=…`). Variables optionnelles :
  `JEEDOM_PATH` (défaut `/var/www/html/plugins`) et `JEEDOM_WEBUSER`
  (défaut `www-data`).

## Limites connues

- Thème sombre uniquement.
- Pas de glisser-déposer dans l'interface : l'ordre se règle par le champ
  « Ordre » de la configuration.
- Le mode caméra n'affiche pas encore le flux vidéo (tuile de commande simple).
- Les volets se pilotent par Ouvrir / Stop / Fermer : pas de curseur de
  position dans la feuille de détail (la commande `FLAP_SLIDER` est détectée
  mais pas encore exposée).
- La désinstallation du plugin supprime les tables `homeboard_room` et
  `homeboard_tile` (les tuiles sont donc à refaire après réinstallation).

## Licence

AGPL — voir `plugin_info/info.json`.
