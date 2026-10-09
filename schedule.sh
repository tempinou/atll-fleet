#!/bin/bash
# Lancé chaque minute par l'addon scheduler de Cloudron (équivalent de la
# crontab `php artisan schedule:run` du docker-compose upstream).
set -euo pipefail
# shellcheck disable=SC1091
source /app/code/env.sh
cd /fleetbase/api
exec gosu www-data php artisan schedule:run --no-interaction
