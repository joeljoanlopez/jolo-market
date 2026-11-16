#!/usr/bin/env bats
# E9: the root Makefile of the development stack (PD-58 to PD-60).
#
# Each test runs the Makefile in a temporary copy of the repository with fake docker and npm on the PATH. They record
# their calls in $CALLS_LOG. deploy/test.sh is replaced by a stub that records its call too.

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    FAKES="$BATS_TEST_TMPDIR/bin"
    CALLS_LOG="$BATS_TEST_TMPDIR/calls.log"
    export CALLS_LOG
    : >"$CALLS_LOG"
    : >"$CALLS_LOG.cwd"

    mkdir -p "$REPO/frontend" "$REPO/backend" "$REPO/deploy" "$FAKES"
    cp /code/Makefile /code/.env.example "$REPO/"
    printf '#!/bin/sh\necho "deploy/test.sh $*" >>"$CALLS_LOG"\nexit "${FAKE_DEPLOY_TEST_STATUS:-0}"\n' \
        >"$REPO/deploy/test.sh"
    chmod +x "$REPO/deploy/test.sh"

    ln -s /code/deploy/tests/fakes/docker "$FAKES/docker"
    ln -s /code/deploy/tests/fakes/npm "$FAKES/npm"
    PATH="$FAKES:$PATH"
    cd "$REPO"
}

calls() {
    cat "$CALLS_LOG"
}

without_node() {
    rm "$FAKES/npm"
}

production_env() {
    printf 'HTTP_PORT=8080\nCOMPOSE_FILE=compose.prod.yaml\n' >.env
}

@test "make with no target lists every target with its description" {
    run make

    [ "$status" -eq 0 ]
    local target
    for target in help init start stop restart logs shell migrate check check-backend check-frontend test-unit \
        test-deploy; do
        [[ $output =~ (^|$'\n')\ *$target\ +[A-Z][^$'\n']+ ]] || { echo "missing: $target"; return 1; }
    done
    [ -z "$(calls)" ]
}

@test "start builds and starts the stack and waits until it is healthy" {
    run make start

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose up -d --build --wait" ]
    [ "$(sort -u "$CALLS_LOG.cwd")" = "$REPO" ]
}

@test "stop stops the stack and keeps its volumes" {
    run make stop

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose down" ]
}

@test "restart stops the stack and starts it again" {
    run make restart

    [ "$status" -eq 0 ]
    [ "$(calls)" = $'docker compose down\ndocker compose up -d --build --wait' ]
}

@test "logs follows the logs of every service" {
    run make logs

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose logs -f" ]
}

@test "logs follows the logs of one service with s=<service>" {
    run make logs s=php

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose logs -f php" ]
}

@test "shell opens a shell in the php container" {
    run make shell

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose exec php sh" ]
}

@test "migrate applies the database migrations" {
    run make migrate

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose exec php bin/console doctrine:migrations:migrate --no-interaction" ]
}

@test "a failed docker command fails the target" {
    export FAKE_DOCKER_FAIL_ON=up

    run make start

    [ "$status" -ne 0 ]
}

@test "every target that runs docker refuses the production stack and runs nothing" {
    production_env
    local target
    for target in init start stop restart logs shell migrate check check-backend test-unit test-deploy; do
        run make "$target"

        [ "$status" -ne 0 ] || { echo "ran: $target"; return 1; }
        [[ $output == *"development stack only"* ]] || { echo "no message: $target"; return 1; }
    done
    [ -z "$(calls)" ]
}

@test "the production guard reads only an uncommented COMPOSE_FILE" {
    printf '#COMPOSE_FILE=compose.prod.yaml\n' >.env

    run make start

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose up -d --build --wait" ]
}

@test "the production guard spots compose.prod.yaml among other compose files" {
    printf 'COMPOSE_FILE=compose.yaml:compose.prod.yaml\n' >.env

    run make start

    [ "$status" -ne 0 ]
    [ -z "$(calls)" ]
}

@test "init creates the root .env and an empty backend/.env.local on a fresh clone" {
    run make init

    [ "$status" -eq 0 ]
    cmp .env .env.example
    [ -f backend/.env.local ]
    [ ! -s backend/.env.local ]
}

@test "init never overwrites .env or backend/.env.local" {
    printf 'HTTP_PORT=9090\n' >.env
    printf 'APP_SECRET=mine\n' >backend/.env.local

    run make init

    [ "$status" -eq 0 ]
    [ "$(cat .env)" = "HTTP_PORT=9090" ]
    [ "$(cat backend/.env.local)" = "APP_SECRET=mine" ]
}

@test "init installs the frontend dependencies on the host, then starts the stack, installs the backend dependencies and migrates" {
    run make init

    [ "$status" -eq 0 ]
    [ "$(calls)" = "npm ci
docker compose up -d --build --wait
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction" ]
    [ "$(head -n 1 "$CALLS_LOG.cwd")" = "$REPO/frontend" ]
}

@test "init without Node warns and sets up the rest" {
    without_node

    run make init

    [ "$status" -eq 0 ]
    [[ $output == *"Node is not installed"* ]]
    [ "$(calls)" = "docker compose up -d --build --wait
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction" ]
}

@test "init stops at the first failed step" {
    export FAKE_DOCKER_FAIL_ON="composer install"

    run make init

    [ "$status" -ne 0 ]
    [[ $(calls) != *"doctrine:migrations:migrate"* ]]
}

@test "a second init changes nothing that is already set up" {
    make init
    cp .env "$BATS_TEST_TMPDIR/env.first"
    cp backend/.env.local "$BATS_TEST_TMPDIR/env.local.first"

    run make init

    [ "$status" -eq 0 ]
    cmp .env "$BATS_TEST_TMPDIR/env.first"
    cmp backend/.env.local "$BATS_TEST_TMPDIR/env.local.first"
}

@test "check-backend runs composer check in the php container" {
    run make check-backend

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose exec php composer check" ]
}

@test "test-unit runs the unit suite in the php container" {
    run make test-unit

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose exec php composer test:unit" ]
}

@test "check-frontend runs npm run check in frontend/ on the host" {
    run make check-frontend

    [ "$status" -eq 0 ]
    [ "$(calls)" = "npm run check" ]
    [ "$(cat "$CALLS_LOG.cwd")" = "$REPO/frontend" ]
}

@test "check-frontend fails with a clear message when Node is missing" {
    without_node

    run make check-frontend

    [ "$status" -ne 0 ]
    [[ $output == *"Node is not installed"* ]]
}

@test "check-frontend runs even with the production stack, since it does not use Docker" {
    production_env

    run make check-frontend

    [ "$status" -eq 0 ]
    [ "$(calls)" = "npm run check" ]
}

@test "test-deploy runs the deploy script tests" {
    run make test-deploy

    [ "$status" -eq 0 ]
    [ "$(calls)" = "deploy/test.sh " ]
}

@test "check runs the backend check, the frontend check and the deploy script tests" {
    run make check

    [ "$status" -eq 0 ]
    [ "$(calls)" = "docker compose exec php composer check
npm run check
deploy/test.sh " ]
}

@test "check runs every check and fails when the backend check fails" {
    export FAKE_DOCKER_FAIL_ON="composer check"

    run make check

    [ "$status" -ne 0 ]
    [[ $(calls) == *"npm run check"* ]]
    [[ $(calls) == *"deploy/test.sh"* ]]
}

@test "check fails when the frontend check fails" {
    export FAKE_NPM_FAIL_ON=check

    run make check

    [ "$status" -ne 0 ]
}

@test "check fails when the deploy script tests fail" {
    export FAKE_DEPLOY_TEST_STATUS=1

    run make check

    [ "$status" -ne 0 ]
}
