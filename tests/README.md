# Tests

Deux niveaux, volontairement séparés.

## Logique pure (intégration continue)

```bash
php tests/run.php --pure
```

Sans base ni Jeedom : typage des tuiles, icônes, libellés, seuils de
fraîcheur, comparaison d'origine (CSRF) et arrondi au pas des curseurs. C'est
ce que lance `.github/workflows/ci.yml` à chaque poussée, avec `php -l` sur tous
les fichiers et `node --check` sur le JavaScript.

## Niveau Jeedom (sur l'instance)

```bash
sudo -u www-data php /var/www/html/plugins/homeboard/tests/run.php
```

En plus du niveau pur, vérifie ce qui dépend réellement de Jeedom : périmètre
des commandes autorisées, exclusion des tuiles de pièces masquées, scénarios
tuilés ou non, enregistrement en masse d'une ligne incohérente, bornes des
libellés et des icônes de pièce.

Le script crée puis supprime ses propres lignes (une pièce « ZZ test masqué »,
deux tuiles et un scénario « ZZ Homeboard test ») : il ne modifie aucune donnée
existante. Il se termine par un code de sortie non nul en cas d'échec, donc il
peut être appelé depuis un cron ou un crochet de pré-déploiement.
