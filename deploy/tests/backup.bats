#!/usr/bin/env bats
# E6.6, E10.30: deploy/backup.sh, a compressed mysqldump of the instance database and an archive of the receipt photos.

load test_helper

setup() {
    setup_repos
    export FAKE_DOCKER_STDOUT='-- MySQL dump of market'
}

backup_files() {
    find "$1" -type f | sort
}

dump_file() {
    find "$1" -type f -name '*.sql.gz'
}

@test "it writes a compressed dump of the database to backups/ and prints its path" {
    run "$SERVER/deploy/backup.sh"

    [ "$status" -eq 0 ]
    local file
    file=$(dump_file "$SERVER/backups")
    [[ $(basename "$file") =~ ^jolo-market-[0-9]{8}T[0-9]{6}Z\.sql\.gz$ ]]
    [ "$(gzip -dc "$file")" = "-- MySQL dump of market" ]
    [[ $output == *"$file"* ]]
}

@test "it dumps a consistent snapshot with the credentials of the mysql container" {
    run "$SERVER/deploy/backup.sh"

    [[ $(docker_calls) == "docker compose exec -T mysql sh -c "*'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --set-gtid-purged=OFF --routines --triggers -uroot "$MYSQL_DATABASE"'* ]]
}

@test "it writes to the directory given as argument" {
    run "$SERVER/deploy/backup.sh" "$BATS_TEST_TMPDIR/elsewhere"

    [ "$status" -eq 0 ]
    [ -n "$(backup_files "$BATS_TEST_TMPDIR/elsewhere")" ]
}

@test "a failed dump fails and leaves no backup file behind" {
    export FAKE_DOCKER_FAIL_ON=mysqldump

    run "$SERVER/deploy/backup.sh"

    [ "$status" -eq 1 ]
    [ -z "$(backup_files "$SERVER/backups")" ]
}

@test "it runs Compose from the repository, wherever it is called from" {
    cd "$BATS_TEST_TMPDIR"

    run "$SERVER/deploy/backup.sh"

    [ "$status" -eq 0 ]
    [ "$(sort -u "$CALLS_LOG.cwd")" = "$SERVER" ]
    [ -n "$(backup_files "$SERVER/backups")" ]
}

@test "it archives the receipt photos next to the dump, with the same timestamp" {
    run "$SERVER/deploy/backup.sh"

    [ "$status" -eq 0 ]
    local dump archive
    dump=$(dump_file "$SERVER/backups")
    archive=$(find "$SERVER/backups" -type f -name '*-receipts.tar.gz')
    [[ $(basename "$archive") =~ ^jolo-market-[0-9]{8}T[0-9]{6}Z-receipts\.tar\.gz$ ]]
    [ "${archive%-receipts.tar.gz}" = "${dump%.sql.gz}" ]
    [[ $output == *"$archive"* ]]
}

@test "it archives the receipts volume through the php container" {
    run "$SERVER/deploy/backup.sh"

    [[ $(docker_calls) == *"docker compose exec -T php tar -czf - -C /var/receipts ."* ]]
}

@test "a failed photo archive fails and leaves no backup file behind" {
    export FAKE_DOCKER_FAIL_ON=tar

    run "$SERVER/deploy/backup.sh"

    [ "$status" -eq 1 ]
    [ -z "$(backup_files "$SERVER/backups")" ]
    [[ $output == *"receipt photos"* ]]
}

