#!/bin/bash
# Déploiement du plugin Homeboard sur une instance Jeedom.
#
#   JEEDOM_HOST=root@jeedom.local ./tools/deploy.sh
#
# Variables (ou fichier .deploy.env à la racine du plugin, non versionné) :
#   JEEDOM_HOST     cible SSH             (obligatoire, ex. root@192.168.1.10)
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
	echo "JEEDOM_HOST manquant. Exemple : JEEDOM_HOST=root@192.168.1.10 $0" >&2
	exit 1
fi

for f in $(find . -name '*.php' -not -path './tools/*'); do
	php -l "$f" > /dev/null || { echo "ERREUR PHP: $f"; exit 1; }
done

tar czf /tmp/homeboard.tgz --exclude=./tools --exclude=./screenshots --exclude=./.deploy.env .
scp -q /tmp/homeboard.tgz "$HOST:/tmp/"

ssh "$HOST" "set -e
  rm -rf '$PLUGINS/.homeboard.new'
  mkdir -p '$PLUGINS/.homeboard.new'
  tar xzf /tmp/homeboard.tgz -C '$PLUGINS/.homeboard.new'
  rm -rf '$PLUGINS/homeboard'
  mv '$PLUGINS/.homeboard.new' '$PLUGINS/homeboard'
  chown -R '$WEBUSER:$WEBUSER' '$PLUGINS/homeboard'
  find '$PLUGINS/homeboard' -type d -exec chmod 755 {} \;
  find '$PLUGINS/homeboard' -type f -exec chmod 644 {} \;
  echo '--- déployé:' \$(find '$PLUGINS/homeboard' -type f | wc -l) 'fichiers'"
