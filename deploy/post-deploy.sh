#!/usr/bin/env bash
# Steps after `docker compose up` in an auto-deploy (E7, PD-50). The code is mounted from the host in the current
# stack (compose.yaml), so rebuilding the images does not pick up new dependencies or migrations by itself.
# E6 adapts these steps to the production images.
#
# Exit code: 0 when every step succeeded; the first failing step stops it with its own code.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

docker compose exec -T php composer install --no-interaction --no-progress
docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
# The scheduler is a long-running worker with the old code loaded; the frontend runs `npm ci` when it starts.
docker compose restart scheduler frontend
