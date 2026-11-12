#!/usr/bin/env bats
# E7.3: what runs after the containers are rebuilt (PD-50).

load test_helper

setup() {
    setup_repos
}

@test "it installs the backend dependencies, migrates the database and restarts the scheduler and the frontend, in that order" {
    run "$SERVER/deploy/post-deploy.sh"

    [ "$status" -eq 0 ]
    [ "$(docker_calls)" = "docker compose exec -T php composer install --no-interaction --no-progress
docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
docker compose restart scheduler frontend" ]
}

@test "it stops at the first step that fails, with an error" {
    export FAKE_DOCKER_FAIL_ON=doctrine:migrations:migrate

    run "$SERVER/deploy/post-deploy.sh"

    [ "$status" -ne 0 ]
    [[ $(docker_calls) != *restart* ]]
}

@test "it runs Compose from the repository, wherever it is called from" {
    cd "$BATS_TEST_TMPDIR"

    run "$SERVER/deploy/post-deploy.sh"

    [ "$status" -eq 0 ]
    [ "$(sort -u "$CALLS_LOG.cwd")" = "$SERVER" ]
}
