#!/bin/sh
# Entrypoint of the production php and scheduler containers (E6.3, E6.4).
#
# Every container checks the configuration first and stops with a clear message when it is wrong. The php container
# (command php-fpm) then waits for MySQL, applies the migrations, prints the first-admin setup code (PD-14) and builds
# the search index when it is missing or empty (PD-100). The scheduler starts after php is healthy (compose.prod.yaml),
# so it never runs against an unmigrated database.

set -eu

bin/console app:config:check --no-interaction

if [ "${1:-}" = php-fpm ]; then
    # Compose already waits for a healthy MySQL, but it can still be restarting after its first initialisation.
    tries=0
    until bin/console dbal:run-sql --no-interaction "SELECT 1" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "MySQL is still not reachable after 60 seconds; check DATABASE_URL and the mysql service." >&2
            exit 1
        fi
        echo "Waiting for MySQL..."
        sleep 2
    done

    bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    bin/console app:setup:ensure-code --no-interaction
    # A failure must not stop php-fpm: everything but search keeps working (PD-105), and the next ingestion or
    # app:catalog:reindex-search builds it.
    bin/console app:catalog:reindex-search --if-empty --no-interaction \
        || echo "The search index could not be built; search answers 503 until app:catalog:reindex-search succeeds." >&2
fi

exec "$@"
