#!/bin/bash
set -euo pipefail

echo "==> Fleetbase: préparation des répertoires"
mkdir -p /run/fleetbase/bootstrap-cache /run/fleetbase/home /run/fleetbase/console /run/nginx /run/supervisor

if [[ ! -d /app/data/storage ]]; then
    cp -a /app/code/storage.skel /app/data/storage
fi
mkdir -p /app/data/storage/app/public \
         /app/data/storage/framework/cache/data \
         /app/data/storage/framework/sessions \
         /app/data/storage/framework/views \
         /app/data/storage/framework/testing \
         /app/data/storage/logs

[[ -f /app/data/fleetbase-id ]] || uuidgen > /app/data/fleetbase-id
[[ -f /app/data/env ]] || cp /app/code/env.template /app/data/env
[[ -f /app/data/fleetbase.config.json ]] || cp /app/code/fleetbase.config.json /app/data/fleetbase.config.json

if ! grep -q '^APP_KEY=' /app/data/env; then
    echo "==> Fleetbase: génération de APP_KEY"
    echo "APP_KEY=base64:$(openssl rand -base64 32)" >> /app/data/env
fi

chown -R www-data:www-data /app/data /run/fleetbase

# shellcheck disable=SC1091
source /app/code/env.sh

cd /fleetbase/api
artisan() { gosu www-data php artisan "$@"; }

echo "==> Fleetbase: base de données et migrations"
artisan mysql:createdb
artisan migrate --force
artisan sandbox:migrate --force
artisan fleetbase:seed
artisan fleetbase:create-permissions
artisan schedule-monitor:sync || true

echo "==> Fleetbase: caches"
artisan cache:clear || true
artisan config:clear
artisan route:clear
artisan config:cache
artisan route:cache
artisan queue:restart || true
artisan registry:init || echo "registry:init a échoué (registre d'extensions injoignable ?), on continue"

echo "==> Fleetbase: démarrage (nginx, API Octane, worker de file, SocketCluster)"
exec /usr/bin/supervisord -n -c /app/code/supervisord.conf
