# Homeboard — tableau de bord domotique pour Jeedom

Un tableau de bord façon **Maison** (Apple Home / Google Home) : pièces, tuiles de
commandes, application mobile installable (PWA). Aucun démon, aucune dépendance,
aucun build : PHP + JS natif.

## Fonctionnalités

- **Tableaux de bord multiples** : chaque tableau est un **équipement Jeedom**
  (type `homeboard`) avec son nom, son objet parent, son état actif/visible, ses
  propres tuiles et son propre comportement de veille. Voir « Plusieurs tableaux
  de bord ».
- **Pièces** = onglets du tableau de bord (icône, ordre, visibilité). Les pièces
  sont communes à tous les tableaux, les tuiles non.
- **Tuiles** pilotées par une seule commande Jeedom ; le plugin retrouve seul les
  commandes sœurs de l'équipement (on/off, variation, consigne, modes, volet).
- Types reconnus automatiquement : lumière (avec curseur de luminosité),
  thermostat (température, **consigne au curseur**, modes), sonde,
  prise/interrupteur, volet, caméra.
- **Graphiques** : vue dédiée (une carte par commande historisée, sparkline 24 h)
  et feuille de détail au clic — courbe 24 h / 7 j / 30 j, min · moyenne · max ·
  dernière valeur. Un clic sur une tuile « sonde » ouvre directement sa courbe.
- **Temps réel** : rafraîchissement configurable (3 s par défaut), mise à jour
  optimiste au clic (confirmation par le retour d'état), rendu incrémental
  (seules les tuiles dont la valeur change sont redessinées).
- **Bornes réelles** : les plages (min/max/pas) des thermostats et des
  variateurs sont lues dans la configuration des commandes Jeedom (repli
  5–30 °C / 0,5 °C et 0–100). La borne haute est ramenée sur la grille du pas
  (`alignMax`) : avec min 5, max 10 et pas 3, l'interface annonce 8, la dernière
  valeur réellement atteignable. Le pas est aussi appliqué côté serveur, donc un
  curseur ne peut pas envoyer une valeur hors grille.
- **Import** : les commandes déjà tuilées sont ignorées côté serveur (compteur
  « déjà présente(s) ») et la pièce retenue par l'analyse est conservée.
- **Scènes et boutons** : une tuile peut déclencher un scénario Jeedom ou une
  commande d'action pure, avec accusé d'envoi et état du scénario (en cours,
  terminé). Voir « Scènes et boutons » plus bas.
- **Mode mur** : case à cocher dans la configuration. Voir « Mode mur ».
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
- **Accès par le DNS Jeedom** (`https://<adresse-jeedom>/plugins/homeboard/pwa/index.php`) :
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
  détail : luminosité + préréglages 20/50/75/100 % (lumière), consigne au curseur
  et modes (thermostat), ouvrir/stop/fermer (volet), courbe (sonde).
- **Consigne au curseur** : la tuile thermostat porte un curseur pleine largeur,
  la feuille de détail un curseur avec la grande valeur au centre et les bornes
  affichées. Une seule requête part, au relâchement ; pendant le glissement
  l'affichage suit sans rien envoyer. Le geste appartient au curseur : ni appui
  long, ni ouverture de feuille, ni bascule de la tuile.
- Toutes les cibles font au moins 44 px : pilules de mode en sélecteur segmenté,
  curseurs avec une zone de 40 px de haut, piste de 8 à 10 px et pouce de 22 à
  28 px.
- Retour d'appui immédiat (échelle + luminosité) et vibration courte sur Android ;
  les actions basculent en optimiste, la confirmation arrive ensuite.
- Le curseur de luminosité n'est plus dans la tuile : sur un écran tactile il se
  touchait en faisant défiler la page.
- `-webkit-touch-callout: none`, `-webkit-user-select: none` et `user-select: none`
  sur toute la racine (pas seulement les tuiles : Safari ignore la propriété non
  préfixée) pour éviter la sélection de texte et le menu contextuel d'iOS lors
  d'un appui maintenu. Les champs de saisie gardent la sélection.
- Un geste interrompu (`pointercancel`) remet à zéro l'état de glissement : un
  appel entrant ou un changement d'application ne peut pas figer le
  rafraîchissement.

## Plusieurs tableaux de bord

Un tableau de bord **est un équipement Jeedom**. On le crée, le duplique et le
supprime comme n'importe quel équipement, depuis la page du plugin :

```
Plugins → Homeboard → Mes tableaux de bord
index.php?v=d&p=homeboard&m=homeboard
```

Chaque tableau porte :

- **Paramètres généraux** — nom, objet parent, actif, visible, commentaire : les
  champs standards de Jeedom, enregistrés par le bouton « Sauvegarder ».
- **Adresse du tableau** — `plugins/homeboard/pwa/index.php?b=<slug>`, c'est
  l'adresse qu'on installe sur l'écran concerné. `?b=<id>` fonctionne aussi.
  Sans paramètre, l'adresse sert le premier tableau créé ; un slug inconnu y
  retombe également, pour qu'un lien périmé affiche encore quelque chose.
  L'adresse proposée reprend le domaine depuis lequel la configuration est
  ouverte : collée telle quelle, elle garde la session Jeedom. **L'IP locale et
  le DNS Jeedom sont deux origines distinctes** — une session ouverte sur l'une
  ne vaut pas pour l'autre. Sans session, la page l'explique au lieu de renvoyer
  au dashboard.
- **Mode mur** — activation, délai de veille, anti-marquage OLED, widgets : ce
  réglage appartient au tableau, donc le mur du salon peut s'endormir sans que
  le téléphone suive.
- **Tuiles / Scènes / Import** — la sélection de commandes de ce tableau. Les
  pièces sont communes à tous les tableaux ; une même commande peut figurer sur
  plusieurs tableaux.

Le **slug** est calculé au premier enregistrement à partir du nom et ne change
plus ensuite : renommer le tableau ne casse pas les adresses déjà installées.

Le **périmètre des droits** suit le tableau :

- les droits natifs de l'équipement s'appliquent : un profil **restreint** sans
  droit `r` sur le tableau ne l'ouvre pas, même en connaissant son adresse, et
  aucune de ses tuiles n'est exécutable ;
- les commandes exécutables sont celles des tuiles accessibles du tableau
  courant, et une tuile d'un autre tableau ne peut être ni modifiée ni supprimée
  en changeant l'identifiant envoyé à l'API.

Un tableau **désactivé** (`Actif` décoché) n'est plus servi à son adresse : la
page répond « Ce tableau de bord est désactivé dans Jeedom ». Il reste en
revanche ouvrable et modifiable dans l'administration — on ne bascule jamais
silencieusement vers un autre tableau, sinon on éditerait les tuiles du mauvais.

Le **dernier tableau ne peut pas être supprimé**, y compris par le bouton
standard de Jeedom : le refus est dans `preRemove()`, donc il couvre tous les
chemins, et le bouton l'explique au lieu de ne rien faire.

Le plugin n'a plus de configuration propre pour les tableaux : la page
`Configuration` du plugin ne garde que ce qui est commun (nom de la maison,
horloge, rythme de rafraîchissement) et les pièces.

> **Migration depuis une version antérieure à 0.6.** Les tableaux qui vivaient
> dans la table `homeboard_board` sont convertis en équipements au premier
> chargement. La table d'origine n'est **jamais supprimée** : elle est renommée
> `homeboard_board_old` et **c'est elle qui sert de sauvegarde** — aucun fichier
> de sauvegarde n'est écrit dans le répertoire du plugin, qui est servi par le
> serveur web (les `.json` y sont refusés par Jeedom, mais ce n'est pas une
> raison de compter dessus, et un déploiement écrase de toute façon ce dossier).
> La migration est idempotente (un slug déjà présent est réutilisé) et
> `migratedEqLogic` n'est posé qu'après une migration menée à son terme : une
> erreur SQL inattendue laisse la table intacte, ne marque rien, et
> l'administration affiche un bandeau « Migration incomplète » jusqu'à ce que ça
> passe. `homeboard_board_old` peut être effacée une fois les tableaux vérifiés.

La vue plein écran dans Jeedom
(`index.php?v=d&p=homeboard&m=homeboard&board=<id|slug>`) demande seulement une
session, pas le profil administrateur : un utilisateur restreint voit le tableau
s'il en a le droit. La gestion des tableaux, elle, reste réservée aux
administrateurs.

## Scènes et boutons

Deux types de tuiles déclenchent une action au lieu d'afficher un état.

- **Scène** : dans la configuration, section « Scènes », chaque scénario actif a
  un bouton *Ajouter* ; la pièce d'accueil se choisit au-dessus de la liste. Un
  appui lance le scénario, la sous-ligne de la tuile affiche l'accusé (« Envoi… »,
  « En cours… », « Terminé ») en interrogeant l'état réel du scénario. Les
  scénarios désactivés ne sont ni proposés ni conservés à l'affichage.
- **Bouton** : une tuile dont la commande est de type *action* (virtuel sans
  retour d'état). La recherche de commande les propose désormais ; choisir une
  action force le type « Bouton ». La tuile affiche « Envoyé » puis revient à
  son libellé.
- Une commande d'action **ne peut pas** servir de commande pilote pour un autre
  type de tuile : elle s'exécuterait à chaque affichage du tableau de bord.
  Le serveur refuse l'enregistrement et la lecture ignore ce genre de tuile
  héritée.
- L'appui long sur une tuile d'action ne cherche pas à ouvrir une feuille de
  détail : il déclenche l'action, comme un appui court.

## Fiabilité de l'affichage

Un tableau de bord qui affiche une valeur morte comme si elle était vivante
ment. Deux cas sont distingués, sans aucun réglage :

- **Aucune valeur** : la commande n'a jamais rien renvoyé (capteur muet,
  commande supprimée, équipement désactivé). La tuile prend une bordure
  pointillée, la valeur passe en italique grise et la sous-ligne affiche
  « Aucune valeur » — au lieu d'annoncer « Éteinte » ou zéro. Trois indices
  (bordure, style, mot), dont deux lisibles en noir et blanc.
- **Âge de la mesure** : sous le nom, en petit et gris, « il y a 22 min »,
  « il y a 7 h ». Uniquement sur les tuiles de mesure, et seulement au-delà de
  dix minutes : un état de lampe inchangé depuis deux heures n'a rien
  d'anormal, alors qu'une température figée depuis sept heures mérite un coup
  d'œil. L'âge vient de la date du dernier **événement reçu** (celle que Jeedom
  appelle `collectDate`), pas de la date du dernier changement de valeur : une
  sonde qui renvoie 21 °C toutes les cinq minutes garde sinon une date vieille de
  plusieurs heures et passerait pour morte.
- **Mesure périmée** : pour les mesures d'ambiance (température, humidité,
  pression, CO₂…), censées remonter régulièrement, un retard de plus de deux
  heures passe la tuile en bordure pointillée ambre avec « périmé · il y a 8 h ».
  Une puissance ou un état n'est jamais marqué périmé : il ne change que si
  l'appareil change, et son âge reste purement informatif.

Ce comportement suit les recommandations des guides de conception de panneaux
muraux ([états multiples et péremption](https://artofstyleframe.com/blog/smart-home-dashboard-ui-design/),
[lisibilité à deux mètres](https://artofstyleframe.com/blog/wall-mounted-tablet-ui-design/)).

## Mode mur

Case à cocher **Mode mur** dans la configuration (désactivée par défaut), avec son
propre bloc de réglages.

- **Veille après** : délai sans contact avant le voile (1 à 60 minutes, 2 par
  défaut). Un voile noir affiche une grande horloge et la date ; le premier
  toucher réveille le tableau de bord, et ce toucher de réveil n'atteint pas la
  tuile située dessous.
- **Anti-marquage** : le bloc horloge + valeurs se décale de quelques pixels
  toutes les minutes (amplitude bornée, transition douce de 2 s), comme un
  économiseur d'écran. L'heure et les valeurs sont réécrites périodiquement, donc
  aucun pixel fixe ne reste allumé.
- **Widgets** : jusqu'à quatre commandes d'information affichées sous l'heure —
  consommation, température, météo, n'importe quel retour d'état Jeedom. Elles se
  choisissent par recherche dans la section, le libellé se modifie sur place, la
  croix retire la valeur. Les valeurs suivent le rafraîchissement normal : elles
  sont à jour au réveil.
- **Écran OLED** : case dédiée. Sur une dalle OLED un pixel noir ne consomme
  rien, donc Homeboard abaisse la luminance (gris `#b7b7b7` au lieu de blanc),
  porte le décalage à 12 % de la largeur / 9 % de la hauteur toutes les 45 s, et
  fait varier lentement l'intensité du bloc (cycle de 150 s) pour qu'aucun pixel
  ne reste à la même valeur. Sur LCD, laisser décoché : le rétroéclairage
  consomme la même chose quelle que soit l'image.
- Le navigateur est invité à ne pas laisser l'écran s'éteindre (`WakeLock`),
  quand il le permet : HTTPS obligatoire, sinon la demande est simplement
  ignorée.

## Sécurité

- Toutes les actions d'écriture et `exec()` passent en **POST** ; les lectures
  (`layout`, `values`, `history`, `scan`, `searchCmd`, `adminData`) sont les
  seules autorisées en GET. `core/ajax/homeboard.ajax.php` appelle `ajax::init()`,
  qui refuse toute autre action demandée en GET.
- En complément (Jeedom 4.4+ n'expose plus de jeton CSRF), chaque écriture
  vérifie l'origine **strictement** : il faut au moins un des deux en-têtes
  (`Sec-Fetch-Site` ou `Origin`), `Sec-Fetch-Site` doit valoir `same-origin`
  ou `none`, et l'origine complète — schéma, hôte et port effectif — doit
  correspondre à la requête. Le schéma est lu dans `X-Forwarded-Proto` pour
  fonctionner derrière le DNS Jeedom. En cas de doute, la requête est refusée.
- Une commande n'est exécutable que si elle **appartient à une tuile visible** :
  `allowedActions()` reconstruit la liste des commandes d'action que les tuiles
  exposent (boutons, et capacités de la commande pilote : marche/arrêt,
  luminosité, couleur, consigne, modes, volets). `exec()` refuse tout le reste,
  même avec un droit Jeedom `x` sur l'équipement concerné. Le périmètre est
  celui de l'écran : une tuile d'une pièce masquée, ou d'un équipement sans
  droit de lecture, n'ouvre aucune autorisation (`accessibleTiles()`).
- Un scénario n'est lançable que s'il est **présent dans une tuile de scène**,
  actif, si l'utilisateur a le droit `x` dessus et si les scénarios sont
  autorisés dans Jeedom. `scenarioStates()` applique les mêmes filtres plus le
  droit de lecture `r` : l'état d'un scénario non tuilé n'est jamais retourné.
- La commande pilote d'une tuile doit être une commande **d'information** :
  `saveTile()` refuse une commande d'action, `adminData()` et `buildTile()`
  n'exécutent jamais une action (sinon l'action partait à l'affichage).
- `saveTile()`, `saveTilesBulk()` et `importTiles()` partagent les mêmes
  contrôles : les lignes d'un enregistrement en masse repassent une par une par
  `saveTile()`, une ligne invalide est refusée seule (la réponse détaille
  laquelle et pourquoi) et les champs absents gardent leur valeur — changer le
  type d'une tuile ne peut plus la faire disparaître.
- Les valeurs exécutées sont validées côté serveur : bornes `minValue`/`maxValue`
  pour un slider, liste `listValue` pour un select, format `#RRGGBB` pour une
  couleur, longueur bornée pour un message.
- `kind`, `icon` et `size` sont restreints à des listes fermées côté PHP, et le
  type de tuile est écrit dans un attribut `class` via une valeur filtrée.
- Un scénario n'est lançable que s'il est actif, si l'utilisateur a le droit
  `x` dessus et si les scénarios sont autorisés dans Jeedom ; une tuile de
  scénario pointe sur un `scenario_id` validé à l'écriture, jamais sur un
  numéro venu du navigateur au moment de l'exécution.
- Les écritures d'administration exigent `isConnect('admin')` ; l'exécution
  vérifie `$eqLogic->hasRight('x')` et l'affichage filtre sur `hasRight('r')`.
- En **lecture**, `values()` et `history()` s'appuient sur le droit Jeedom `r`
  de la commande, pas sur son appartenance à une tuile. C'est un choix : un
  compte qui possède déjà ce droit peut lire la commande dans Jeedom lui-même,
  donc Homeboard n'ouvre rien de plus ; et les widgets du mode mur comme les
  courbes utilisent des commandes qui ne sont pas forcément sur une tuile. Ce
  sont les **actions** qui sont bornées par l'écran, pas les lectures.
  Sans droit d'exécution, la tuile est grisée et la commande ne part pas.
- La CSP de la PWA est stricte (aucun script ni style inline, pas d'`eval`,
  `object-src 'none'`, `form-action 'self'`) : le peu de style dynamique passe
  par le CSSOM.
- Le service worker ne touche qu'aux caches `homeboard-*`, ne lit que le sien,
  n'intercepte que les URL du plugin et ne met jamais en cache une réponse
  redirigée (session expirée). Seules les ressources **statiques** sont
  précachées (icônes) : le shell PHP n'est mis en cache qu'au chargement réel et
  seulement si la réponse est bien du HTML, pour qu'une page de connexion ou une
  erreur PHP ne puisse pas être servie à la place du tableau de bord.

## Tests

```bash
php tests/run.php --pure                 # logique pure, sans Jeedom (CI)
sudo -u www-data php plugins/homeboard/tests/run.php   # sur l'hôte Jeedom
```

Le second niveau vérifie les droits des tableaux (profil restreint créé puis
supprimé pour la durée du test), les tableaux désactivés, les pièces masquées,
les scénarios et les enregistrements en masse ; il crée puis supprime ses propres
lignes. Détails dans `tests/README.md`.

La CI GitHub lance `php -l` sur tous les fichiers, `node --check` sur le
JavaScript, la validation de `plugin_info/info.json`, les tests purs, et un
**garde anti-fuite** (`php tools/scan-secrets.php`) : le workflow échoue si une
adresse privée (10/8, 172.16/12, 192.168/16), un domaine personnel, un bloc de
clé privée ou une valeur de type secret apparaît dans les fichiers livrés — PHP,
JS, CSS, JSON, Markdown et YAML. Ce n'est pas un scanner de secrets complet
(gitleaks, trufflehog) : c'est un filet ciblé, sans dépendance, qui doit rester
strict plutôt que permissif.

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
- L'accusé d'un scénario s'arrête à « Terminé » : Homeboard suit l'état du
  scénario (`starting` / `in progress` / `stop`), pas le résultat interne de
  chaque action qu'il contient.
- Les droits par tableau ne distinguent que les profils `restricted` : Jeedom
  accorde tous les droits aux profils `admin` et `user`, le contrôle
  `hasRight('r')` n'a donc d'effet que sur un compte restreint.
- À la désinstallation, Jeedom supprime les équipements du plugin un par un en
  encadrant chaque suppression d'un `try/catch` : le dernier tableau, protégé par
  `preRemove()`, survit à la désinstallation et réapparaît si le plugin est
  réinstallé. C'est volontaire — un tableau perdu ne se reconstitue pas — mais il
  faut le supprimer à la main si on veut repartir de zéro.
- La désinstallation supprime les tables `homeboard_room` et `homeboard_tile`
  (les tuiles sont donc à refaire après réinstallation).

## Licence

AGPL — voir `plugin_info/info.json`.
