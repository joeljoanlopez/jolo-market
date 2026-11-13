#!/bin/sh
# Entrypoint of the production php and scheduler containers (E6.3, E6.4).
#
# Every container checks the configuration first and stops with a clear message when it is wrong. The php container
# (command php-fpm) then waits for MySQL, applies the migrations and prints the first-admin setup code (PD-14). The
# scheduler starts after php is healthy (compose.prod.yaml), so it never runs against an unmigrated database.

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
fi

exec "$@"
