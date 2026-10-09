#!/bin/bash
# Environnement Laravel dérivé des addons Cloudron.
# Sourcé par start.sh (démarrage) et schedule.sh (tâche cron du scheduler
# Cloudron, qui s'exécute via `docker exec` et ne voit donc pas les exports de
# start.sh). Les réglages de l'administrateur dans /app/data/env sont chargés
# en dernier et priment sur tout ce qui est calculé ici.

export APP_NAME="Fleetbase"
export APP_ENV="production"
export ENVIRONMENT="production"
export APP_DEBUG="false"
export APP_URL="${CLOUDRON_APP_ORIGIN}"
export LOG_CHANNEL="stdout"
export LOG_LEVEL="info"

# La console et l'API partagent l'origine Cloudron (nginx route les deux).
export CONSOLE_HOST="${CLOUDRON_APP_ORIGIN}"
export CONSOLE_SECURE="true"
export SESSION_DOMAIN="${CLOUDRON_APP_DOMAIN}"
export SESSION_SECURE_COOKIE="true"
export SANCTUM_STATEFUL_DOMAINS="${CLOUDRON_APP_DOMAIN}"
export TRUSTED_PROXIES="*"
export OCTANE_SERVER="frankenphp"
export OCTANE_HTTPS="true"

# MySQL (addon multipleDatabases : Fleetbase crée <prefix>fleetbase et
# <prefix>fleetbase_sandbox lui-même via `artisan mysql:createdb`).
unset DATABASE_URL CACHE_URL REDIS_URL
export DB_CONNECTION="mysql"
export DB_HOST="${CLOUDRON_MYSQL_HOST}"
export DB_PORT="${CLOUDRON_MYSQL_PORT}"
export DB_USERNAME="${CLOUDRON_MYSQL_USERNAME}"
export DB_PASSWORD="${CLOUDRON_MYSQL_PASSWORD}"
export DB_DATABASE="${CLOUDRON_MYSQL_DATABASE_PREFIX}fleetbase"
export MYSQL_ATTR_SSL_VERIFY_SERVER_CERT="false"

# Redis : cache, files d'attente, OTP.
export REDIS_CLIENT="phpredis"
export REDIS_HOST="${CLOUDRON_REDIS_HOST}"
export REDIS_PORT="${CLOUDRON_REDIS_PORT}"
export REDIS_PASSWORD="${CLOUDRON_REDIS_PASSWORD}"
export CACHE_DRIVER="redis"
export QUEUE_CONNECTION="redis"
export SESSION_DRIVER="file"
export CACHE_PATH="/app/data/storage/framework/cache"

# SocketCluster tourne dans le conteneur ; l'API y publie en clair en local,
# les navigateurs y accèdent via nginx sur wss://<domaine>/socketcluster/.
export BROADCAST_DRIVER="socketcluster"
export SOCKETCLUSTER_HOST="127.0.0.1"
export SOCKETCLUSTER_PORT="38000"
export SOCKETCLUSTER_SECURE="false"
export SOCKETCLUSTER_PATH="/socketcluster/"
export SOCKETCLUSTER_OPTIONS="{\"origins\":\"https://${CLOUDRON_APP_DOMAIN}:*,wss://${CLOUDRON_APP_DOMAIN}:*\"}"

# Courriel via l'addon sendmail (port 2525, pas de TLS).
export MAIL_MAILER="smtp"
export MAIL_HOST="${CLOUDRON_MAIL_SMTP_SERVER}"
export MAIL_PORT="${CLOUDRON_MAIL_SMTP_PORT}"
export MAIL_USERNAME="${CLOUDRON_MAIL_SMTP_USERNAME}"
export MAIL_PASSWORD="${CLOUDRON_MAIL_SMTP_PASSWORD}"
export MAIL_ENCRYPTION="null"
export MAIL_FROM_ADDRESS="${CLOUDRON_MAIL_FROM}"
export MAIL_FROM_NAME="${CLOUDRON_MAIL_FROM_DISPLAY_NAME:-Fleetbase}"

# Fichiers sur le disque local (/app/data/storage/app/public, servi sur /storage).
export FILESYSTEM_DRIVER="public"

# Registre d'extensions Fleetbase et routage OSRM public (modifiables dans /app/data/env).
export REGISTRY_HOST="https://registry.fleetbase.io"
export REGISTRY_PREINSTALLED_EXTENSIONS="true"
export OSRM_HOST="https://router.project-osrm.org"
# registry-bridge écrit un .npmrc dans le "chemin de la console" à chaque
# `registry:init` ; la vraie console (/app/code/console) est en lecture seule.
export CONSOLE_PATH="/run/fleetbase/console"

FLEETBASE_INSTANCE_ID="$(cat /app/data/fleetbase-id 2>/dev/null || true)"
export FLEETBASE_INSTANCE_ID

# FrankenPHP/Caddy et Composer veulent un HOME inscriptible.
export HOME="/run/fleetbase/home"
export XDG_DATA_HOME="/run/fleetbase/home/.local/share"
export XDG_CONFIG_HOME="/run/fleetbase/home/.config"

# Réglages de l'administrateur (APP_KEY, clés d'API tierces, surcharges).
if [[ -f /app/data/env ]]; then
    set -a
    # shellcheck disable=SC1091
    source /app/data/env
    set +a
fi
