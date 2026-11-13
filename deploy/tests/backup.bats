#!/usr/bin/env bats
# E6.6: deploy/backup.sh, a compressed mysqldump of the instance database.

load test_helper

setup() {
    setup_repos
    export FAKE_DOCKER_STDOUT='-- MySQL dump of market'
}

backup_files() {
    find "$1" -type f | sort
}

@test "it writes a compressed dump of the database to backups/ and prints its path" {
    run "$SERVER/deploy/backup.sh"

    [ "$status" -eq 0 ]
    local file
    file=$(backup_files "$SERVER/backups")
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
