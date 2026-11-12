#!/usr/bin/env bats
# E7.2: deploy/auto-deploy.sh (PD-50).

load test_helper

setup() {
    setup_repos
}

@test "it does nothing while AUTO_DEPLOY is not true" {
    printf 'AUTO_DEPLOY=false\n' >"$SERVER/.env"
    merge_new_commit >/dev/null
    local before
    before=$(server_head)

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$before" ]
    [ -z "$(calls)" ]
}

@test "it does nothing without AUTO_DEPLOY or without a .env" {
    rm "$SERVER/.env"
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$status" -eq 0 ]
    [ -z "$(calls)" ]
}

@test "it does nothing when the copy is up to date" {
    run auto_deploy

    [ "$status" -eq 0 ]
    [ -z "$(docker_calls)" ]
}

@test "it deploys a new commit with a green CI: fast-forward, compose up, then the post-deploy steps" {
    local sha
    sha=$(merge_new_commit)

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$sha" ]
    [ "$(docker_calls | head -n 1)" = "docker compose up -d --build --remove-orphans" ]
    [[ $(docker_calls) == *"doctrine:migrations:migrate"* ]]
    [[ $output == *"Deployed ${sha:0:7}"* ]]
}

@test "it asks GitHub for the CI of the new commit of this repository" {
    local sha
    sha=$(merge_new_commit)

    run auto_deploy

    [[ $(calls) == *"gh api repos/$GITHUB_REPO/commits/$sha/check-runs?per_page=100"* ]]
}

@test "it reads AUTO_DEPLOY without running the .env: quotes, spaces and the last assignment count" {
    printf 'AUTO_DEPLOY=false\nINGESTION_SCHEDULE=0 5 * * *\n  AUTO_DEPLOY = "true"  \n' >"$SERVER/.env"
    local sha
    sha=$(merge_new_commit)

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$sha" ]
}

@test "it waits for a CI that is still running" {
    ci_running
    local before
    before=$(server_head)
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$before" ]
    [ -z "$(docker_calls)" ]
    [[ $output == *pending* ]]
}

@test "it waits for a CI that has not started yet" {
    ci_not_started
    local before
    before=$(server_head)
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$before" ]
}

@test "it does not deploy a commit with a failed check" {
    ci_red
    local before
    before=$(server_head)
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$before" ]
    [ -z "$(docker_calls)" ]
    [[ $output == *red* ]]
}

@test "it does not deploy a commit with a failed commit status" {
    export FAKE_STATUS='{"total_count":1,"state":"failure"}'
    local before
    before=$(server_head)
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$(server_head)" = "$before" ]
}

@test "it deploys the commit once its CI turns green" {
    ci_running
    local sha
    sha=$(merge_new_commit)
    run auto_deploy
    ci_green

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$sha" ]
}

@test "it uses curl when gh is not logged in" {
    export FAKE_GH_AUTH=1
    local sha
    sha=$(merge_new_commit)

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$sha" ]
    [[ $(calls) == *"curl https://api.github.com/repos/$GITHUB_REPO/commits/$sha/check-runs?per_page=100"* ]]
}

@test "it leaves a copy on another branch alone" {
    git -C "$SERVER" checkout --quiet -b hotfix
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(git -C "$SERVER" branch --show-current)" = hotfix ]
    [ -z "$(docker_calls)" ]
    [[ $output == *hotfix* ]]
}

@test "it never overwrites uncommitted changes in tracked files" {
    printf 'local edit\n' >>"$SERVER/app.txt"
    local before
    before=$(server_head)
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$before" ]
    [ "$(tail -n 1 "$SERVER/app.txt")" = "local edit" ]
    [ -z "$(docker_calls)" ]
}

@test "untracked files such as .env do not block a deploy" {
    printf 'scratch\n' >"$SERVER/notes.txt"
    local sha
    sha=$(merge_new_commit)

    run auto_deploy

    [ "$(server_head)" = "$sha" ]
}

@test "it leaves a copy with local commits alone" {
    printf 'local\n' >"$SERVER/local.txt"
    git -C "$SERVER" add local.txt
    git -C "$SERVER" commit --quiet -m local
    local before
    before=$(server_head)
    merge_new_commit >/dev/null

    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(server_head)" = "$before" ]
    [ -z "$(docker_calls)" ]
}

@test "a failed compose fails the run and is retried on the next one" {
    local sha
    sha=$(merge_new_commit)
    export FAKE_DOCKER_FAIL_ON="compose up"

    run auto_deploy

    [ "$status" -eq 1 ]
    [ "$(server_head)" = "$sha" ]

    unset FAKE_DOCKER_FAIL_ON
    : >"$CALLS_LOG"
    run auto_deploy

    [ "$status" -eq 0 ]
    [ "$(docker_calls | head -n 1)" = "docker compose up -d --build --remove-orphans" ]
    [[ $output == *"Deployed ${sha:0:7}"* ]]

    : >"$CALLS_LOG"
    run auto_deploy

    [ -z "$(docker_calls)" ]
}

@test "a failed post-deploy step (a migration) fails the run and is retried on the next one" {
    merge_new_commit >/dev/null
    export FAKE_DOCKER_FAIL_ON=doctrine:migrations:migrate

    run auto_deploy

    [ "$status" -eq 1 ]

    unset FAKE_DOCKER_FAIL_ON
    : >"$CALLS_LOG"
    run auto_deploy

    [ "$status" -eq 0 ]
    [[ $(docker_calls) == *"doctrine:migrations:migrate"* ]]
}

@test "a run exits at once while another one holds the lock" {
    merge_new_commit >/dev/null
    local git_dir
    git_dir=$(git -C "$SERVER" rev-parse --absolute-git-dir)
    exec 8>"$git_dir/auto-deploy.lock"
    flock -n 8

    run auto_deploy

    [ "$status" -eq 0 ]
    [ -z "$(docker_calls)" ]
    [[ $output == *"Another run"* ]]
}

@test "it fails when origin cannot be fetched" {
    rm -rf "$ORIGIN"

    run auto_deploy

    [ "$status" -eq 1 ]
}
