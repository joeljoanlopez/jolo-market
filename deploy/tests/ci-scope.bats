#!/usr/bin/env bats
# E12.1: deploy/ci-scope.sh tells CI which jobs a branch needs (PD-107 to PD-110).
#
# Each test builds a temporary repository whose origin/main is the first commit, then commits the branch's changes on
# top of it.

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    export GIT_AUTHOR_NAME=test GIT_AUTHOR_EMAIL=test@example.test
    export GIT_COMMITTER_NAME=test GIT_COMMITTER_EMAIL=test@example.test
    export GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=init.defaultBranch GIT_CONFIG_VALUE_0=main

    git init --quiet "$REPO"
    cd "$REPO"
    printf 'base\n' >base.txt
    git add .
    git commit --quiet -m base
    git update-ref refs/remotes/origin/main HEAD
    git checkout --quiet -b feature
}

# Commits a change to each given path on the branch.
change() {
    local path
    for path in "$@"; do
        mkdir -p "$(dirname "$path")"
        printf 'change\n' >>"$path"
    done
    git add .
    git commit --quiet -m change
}

ci_scope() {
    /code/deploy/ci-scope.sh "$@"
}

@test "a branch with only documentation runs no job" {
    change docs/architecture.md README.md frontend/README.md .claude/skills/x/SKILL.md LICENSE

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=false\nfrontend=false\ne2e=false\ndeploy=false' ]
}

@test "a branch with no change since main runs no job" {
    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=false\nfrontend=false\ne2e=false\ndeploy=false' ]
}

@test "a frontend change runs the frontend check and end-to-end" {
    change frontend/src/main.tsx

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=false\nfrontend=true\ne2e=true\ndeploy=false' ]
}

@test "each backend file runs the backend check and end-to-end" {
    local path
    for path in backend/src/Kernel.php compose.yaml .env.example docker/php/Dockerfile docker/mysql/init.sql; do
        git checkout --quiet -B "feature-$path" origin/main
        change "$path"

        run ci_scope

        [ "$status" -eq 0 ]
        [ "$output" = $'backend=true\nfrontend=false\ne2e=true\ndeploy=false' ] || { echo "$path: $output"; return 1; }
    done
}

@test "an nginx change runs only end-to-end" {
    change docker/nginx/default.conf

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=false\nfrontend=false\ne2e=true\ndeploy=false' ]
}

@test "each deploy file runs only the deploy scripts" {
    local path
    for path in deploy/backup.sh Makefile docker/bats/Dockerfile; do
        git checkout --quiet -B "feature-$path" origin/main
        change "$path"

        run ci_scope

        [ "$status" -eq 0 ]
        [ "$output" = $'backend=false\nfrontend=false\ne2e=false\ndeploy=true' ] || { echo "$path: $output"; return 1; }
    done
}

@test "the php entrypoint runs the backend check, end-to-end and the deploy scripts" {
    change docker/php/entrypoint.sh

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=true\nfrontend=false\ne2e=true\ndeploy=true' ]
}

@test "a workflow change runs every job" {
    change .github/workflows/ci.yml

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=true\nfrontend=true\ne2e=true\ndeploy=true' ]
}

@test "a change to the scope script itself runs every job" {
    change deploy/ci-scope.sh

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=true\nfrontend=true\ne2e=true\ndeploy=true' ]
}

@test "a file that matches no rule runs every job" {
    change compose.prod.yaml

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=true\nfrontend=true\ne2e=true\ndeploy=true' ]
}

@test "the scope covers every commit of the branch, not only the last one" {
    change backend/src/Kernel.php
    change docs/architecture.md

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=true\nfrontend=false\ne2e=true\ndeploy=false' ]
}

@test "changes merged into main after the branch started do not count" {
    git checkout --quiet main
    change backend/src/Kernel.php
    git update-ref refs/remotes/origin/main HEAD
    git checkout --quiet feature
    change docs/architecture.md

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=false\nfrontend=false\ne2e=false\ndeploy=false' ]
}

@test "a deleted file counts as a change" {
    change frontend/src/old.tsx
    git update-ref refs/remotes/origin/main HEAD
    git rm --quiet frontend/src/old.tsx
    git commit --quiet -m delete

    run ci_scope

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=false\nfrontend=true\ne2e=true\ndeploy=false' ]
}

@test "it compares with the base ref it is given" {
    change frontend/src/main.tsx
    git branch other
    change backend/src/Kernel.php

    run ci_scope other

    [ "$status" -eq 0 ]
    [ "$output" = $'backend=true\nfrontend=false\ne2e=true\ndeploy=false' ]
}

@test "without a merge-base it runs every job and says why" {
    run ci_scope refs/remotes/origin/missing

    [ "$status" -eq 0 ]
    [[ $output == *"no merge-base"* ]]
    [[ $output == *$'backend=true\nfrontend=true\ne2e=true\ndeploy=true'* ]]
}
