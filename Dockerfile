# Fleetbase Community Edition pour Cloudron.
#
# Plutôt que de recompiler l'API (PHP 8.2 + FrankenPHP + extension GEOS) et la
# console (Ember, pnpm), on part des images officielles publiées par Fleetbase
# sur Docker Hub pour chaque release, et on y ajoute ce qu'il faut pour tourner
# dans un seul conteneur Cloudron : nginx (point d'entrée unique), supervisor
# (API Octane + worker de file + SocketCluster), et le déport des chemins
# inscriptibles vers /app/data et /run (le système de fichiers est en lecture
# seule sur Cloudron).
#
# Pour passer à une nouvelle version de Fleetbase : changer FLEETBASE_VERSION
# (et les digests) puis vérifier que le motif de patch de la console ci-dessous
# existe toujours (le build échoue sinon, volontairement).

ARG FLEETBASE_VERSION=0.7.68

FROM fleetbase/fleetbase-console:v0.7.68@sha256:1ed70f360a9b95b27babb9c81e3701b03b624b4d91f06d93779d9deaca66e373 AS console
FROM socketcluster/socketcluster:v17.4.0@sha256:3cd5c2dde94eab1d4b4dde7d35a6eb8e5a109e7cb491c6f3964111144557b085 AS socketcluster

FROM fleetbase/fleetbase-api:v0.7.68@sha256:dc42bcfeadf22e4579dd0a2e68fa95ad9d1ed49765c6f16a469abe6ca3f1fe37

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor gosu openssl ca-certificates \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default \
    # gosu replace HOME par celui de /etc/passwd : il doit être inscriptible (Caddy, .npmrc…)
    && usermod -d /run/fleetbase/home www-data

# --- SocketCluster (temps réel) : serveur Node + node_modules de l'image officielle
COPY --from=socketcluster /usr/src/ /app/code/socketcluster/

# --- Console (SPA Ember compilée)
COPY --from=console /usr/share/nginx/html/ /app/code/console/

# La console publiée appelle l'API en chemin relatif (même origine) mais a le
# port WebSocket 38000 figé en dur et la config runtime désactivée. On patche
# le bundle : port 443 (nginx proxifie /socketcluster/) et config runtime
# activée (lue dans /fleetbase.config.json, servi depuis /app/data). Les grep
# font échouer le build si une future version change ces motifs.
RUN set -eu; \
    f="$(ls /app/code/console/assets/@fleetbase/console-*.js)"; \
    grep -q 'socket:{path:"/socketcluster/",hostname:"",secure:!0,port:"38000"}' "$f"; \
    sed -i 's#socket:{path:"/socketcluster/",hostname:"",secure:!0,port:"38000"}#socket:{path:"/socketcluster/",hostname:"",secure:!0,port:"443"}#' "$f"; \
    grep -q 'disableRuntimeConfig:!0,disableFleetbaseAttribution' "$f"; \
    sed -i 's#disableRuntimeConfig:!0,disableFleetbaseAttribution#disableRuntimeConfig:!1,disableFleetbaseAttribution#' "$f"; \
    rm -f /app/code/console/fleetbase.config.json; \
    # index.html porte des hachages d'intégrité (SRI) : on les recalcule pour le bundle patché
    idx=/app/code/console/index.html; b="$(basename "$f")"; \
    grep -q "$b\" integrity=\"sha256-" "$idx"; \
    h256="$(openssl dgst -sha256 -binary "$f" | base64 | tr -d '\n')"; \
    h512="$(openssl dgst -sha512 -binary "$f" | base64 | tr -d '\n')"; \
    sed -i "\#$b#s#integrity=\"[^\"]*\"#integrity=\"sha256-$h256 sha512-$h512\"#" "$idx"; \
    grep -q "sha512-$h512" "$idx"

# --- API Laravel : sortir les chemins inscriptibles de l'image
#  storage/          -> /app/data/storage   (uploads, sessions, logs, caches)
#  bootstrap/cache/  -> /run/fleetbase/bootstrap-cache (config:cache, route:cache)
#  .env              -> /app/data/env       (APP_KEY + réglages de l'administrateur)
#  .fleetbase-id     -> /app/data/fleetbase-id (identifiant d'instance, stable)
#  public/storage    -> /app/data/storage/app/public (équivalent de storage:link)
RUN set -eu; cd /fleetbase/api; \
    mv storage /app/code/storage.skel; \
    rm -rf bootstrap/cache .env .fleetbase-id crontab .ssm-parent.yaml; \
    ln -s /app/data/storage storage; \
    ln -s /run/fleetbase/bootstrap-cache bootstrap/cache; \
    ln -s /app/data/env .env; \
    ln -s /app/data/fleetbase-id .fleetbase-id; \
    ln -s /app/data/storage/app/public public/storage; \
    cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php; \
    chown -h www-data:www-data storage bootstrap/cache .env .fleetbase-id public/storage public/frankenphp-worker.php

# --- Connexion avec les comptes Cloudron (addon oidc) : Fleetbase n'a pas de
# fournisseur OIDC générique, on ajoute un driver "cloudron" (deux classes PSR-4
# dans core-api) et son entrée dans config/oauth.php, lue via les CLOUDRON_OIDC_*.
COPY oauth/CloudronProvider.php /fleetbase/api/vendor/fleetbase/core-api/src/Auth/OAuth/Socialite/CloudronProvider.php
COPY oauth/CloudronDriver.php /fleetbase/api/vendor/fleetbase/core-api/src/Auth/OAuth/Drivers/CloudronDriver.php
COPY oauth/providers.php /tmp/oauth-providers.php
RUN set -eu; cfg=/fleetbase/api/vendor/fleetbase/core-api/config/oauth.php; \
    grep -q "^    'providers' => \[" "$cfg"; \
    sed -i "/^    'providers' => \[/r /tmp/oauth-providers.php" "$cfg"; \
    grep -q "'cloudron' => \[" "$cfg"; rm -f /tmp/oauth-providers.php; \
    php -l "$cfg" && php -l /fleetbase/api/vendor/fleetbase/core-api/src/Auth/OAuth/Drivers/CloudronDriver.php \
    && php -l /fleetbase/api/vendor/fleetbase/core-api/src/Auth/OAuth/Socialite/CloudronProvider.php; \
    chown www-data:www-data "$cfg" /fleetbase/api/vendor/fleetbase/core-api/src/Auth/OAuth/Drivers/CloudronDriver.php /fleetbase/api/vendor/fleetbase/core-api/src/Auth/OAuth/Socialite/CloudronProvider.php

# --- Préfixe de base Cloudron pouvant être lu comme un nombre par MySQL : les
# migrations référencent la base principale en SQL brut via un helper unique,
# qu'on fait renvoyer entre backticks (voir patches/quote-database-name.php).
COPY patches/quote-database-name.php /tmp/quote-database-name.php
RUN set -eu; u=/fleetbase/api/vendor/fleetbase/core-api/src/Support/Utils.php; \
    php /tmp/quote-database-name.php "$u" && php -l "$u" && rm -f /tmp/quote-database-name.php

COPY nginx.conf supervisord.conf env.sh env.template fleetbase.config.json /app/code/
COPY start.sh schedule.sh createdb.php /app/code/

RUN chmod +x /app/code/start.sh /app/code/schedule.sh \
    && mkdir -p /run/nginx && nginx -t -c /app/code/nginx.conf && rm -rf /run/nginx

ENTRYPOINT []
CMD ["/app/code/start.sh"]
