#!/usr/bin/env bash
# Backup of the instance database (E6.6): a gzip-compressed mysqldump, taken through the running mysql container.
#
#   deploy/backup.sh               # writes backups/jolo-market-<UTC timestamp>.sql.gz in the repository
#   deploy/backup.sh /srv/backups  # writes it there instead
#
# It uses the Compose files and credentials of the root .env, so it backs up whichever stack is running. The restore
# procedure is in the README (Backup and restore).
#
# Exit code: 0 when the backup was written; 1 when the dump failed, and then no file is left behind.

set -euo pipefail

repo_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
target_dir=${1:-$repo_dir/backups}
# Relative to where it was called from, before moving to the repository.
mkdir -p "$target_dir"
target_dir=$(cd "$target_dir" && pwd)
cd "$repo_dir"

file="$target_dir/jolo-market-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
partial="$file.partial"
trap 'rm -f "$partial"' EXIT

# As root: with --single-transaction, mysqldump 9 needs the RELOAD or FLUSH_TABLES privilege, which the application
# user does not have. --single-transaction: a consistent snapshot of InnoDB tables without locking the app.
# --set-gtid-purged=OFF: a restore must not need SUPER, nor carry the GTIDs of the source server.
# shellcheck disable=SC2016 # the variables are expanded inside the mysql container, not here
if ! docker compose exec -T mysql sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --set-gtid-purged=OFF --routines --triggers -uroot "$MYSQL_DATABASE"' \
    | gzip >"$partial"; then
    printf 'The database dump failed; no backup was written.\n' >&2
    exit 1
fi

mv "$partial" "$file"
printf 'Backup written to %s\n' "$file"
