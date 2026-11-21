#!/bin/bash
# Déploiement du plugin Homeboard sur une instance Jeedom.
#
#   JEEDOM_HOST=root@jeedom.local ./tools/deploy.sh
#
# Variables (ou fichier .deploy.env à la racine du plugin, non versionné) :
#   JEEDOM_HOST     cible SSH             (obligatoire, ex. root@192.0.2.10)
#   JEEDOM_PATH     dossier des plugins   (défaut : /var/www/html/plugins)
#   JEEDOM_WEBUSER  propriétaire web      (défaut : www-data)
set -e

SRC="$(cd "$(dirname "$0")/.." && pwd)"
cd "$SRC"

if [ -f .deploy.env ]; then
	# shellcheck disable=SC1091
	. ./.deploy.env
fi

HOST="${JEEDOM_HOST:-}"
PLUGINS="${JEEDOM_PATH:-/var/www/html/plugins}"
WEBUSER="${JEEDOM_WEBUSER:-www-data}"

if [ -z "$HOST" ]; then
	echo "JEEDOM_HOST manquant. Exemple : JEEDOM_HOST=root@192.0.2.10 $0" >&2
	exit 1
fi

for f in $(find . -name '*.php' -not -path './tools/*'); do
	php -l "$f" > /dev/null || { echo "ERREUR PHP: $f"; exit 1; }
done

# rsync plutôt que rm + mv : la synchronisation en place ne supprime jamais le
# dossier du plugin, donc aucune fenêtre en 404/500 sur les requêtes en cours.
rsync -az --delete \
	--exclude '.git' --exclude '.deploy.env' --exclude 'tools' --exclude 'screenshots' --exclude '.gitignore' \
	./ "$HOST:$PLUGINS/homeboard/"

ssh "$HOST" "set -e
  chown -R '$WEBUSER:$WEBUSER' '$PLUGINS/homeboard'
  find '$PLUGINS/homeboard' -type d -exec chmod 755 {} \;
  find '$PLUGINS/homeboard' -type f -exec chmod 644 {} \;
  echo '--- déployé:' \$(find '$PLUGINS/homeboard' -type f | wc -l) 'fichiers'"
