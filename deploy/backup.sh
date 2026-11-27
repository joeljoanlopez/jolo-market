#!/usr/bin/env bash
# Backup of the instance (E6.6, E10.30): a gzip-compressed mysqldump, taken through the running mysql container, and
# an archive of the receipt photos (PD-69), taken through the php container that mounts the receipts volume.
#
#   deploy/backup.sh               # writes backups/jolo-market-<UTC timestamp>.sql.gz and
#                                  # backups/jolo-market-<UTC timestamp>-receipts.tar.gz in the repository
#   deploy/backup.sh /srv/backups  # writes them there instead
#
# It uses the Compose files and credentials of the root .env, so it backs up whichever stack is running. The restore
# procedure is in the README (Backup and restore).
#
# Exit code: 0 when both files were written; 1 when the dump or the photo archive failed, and then no file is left
# behind: a backup is the database and its photos together.

set -euo pipefail

repo_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
target_dir=${1:-$repo_dir/backups}
# Relative to where it was called from, before moving to the repository.
mkdir -p "$target_dir"
target_dir=$(cd "$target_dir" && pwd)
cd "$repo_dir"

name="$target_dir/jolo-market-$(date -u +%Y%m%dT%H%M%SZ)"
dump="$name.sql.gz"
photos="$name-receipts.tar.gz"
trap 'rm -f "$dump.partial" "$photos.partial"' EXIT

# As root: with --single-transaction, mysqldump 9 needs the RELOAD or FLUSH_TABLES privilege, which the application
# user does not have. --single-transaction: a consistent snapshot of InnoDB tables without locking the app.
# --set-gtid-purged=OFF: a restore must not need SUPER, nor carry the GTIDs of the source server.
# shellcheck disable=SC2016 # the variables are expanded inside the mysql container, not here
if ! docker compose exec -T mysql sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --set-gtid-purged=OFF --routines --triggers -uroot "$MYSQL_DATABASE"' \
    | gzip >"$dump.partial"; then
    printf 'The database dump failed; no backup was written.\n' >&2
    exit 1
fi

if ! docker compose exec -T php tar -czf - -C /var/receipts . >"$photos.partial"; then
    printf 'The archive of the receipt photos failed; no backup was written.\n' >&2
    exit 1
fi

mv "$dump.partial" "$dump"
mv "$photos.partial" "$photos"
printf 'Backup written to %s\n' "$dump"
printf 'Receipt photos written to %s\n' "$photos"
