# Shared setup of the deploy/ tests (PD-51).
#
# Each test gets, in its own temporary folder:
#   origin.git  a bare repository standing for GitHub. The copies reach it through
#               url.<path>.insteadOf, so their origin URL is the real GitHub one.
#   dev         a clone where the tests "merge" new commits and push them.
#   server      the clone that deploys, with deploy/ copied from this repository.
# docker, gh and curl are fakes (deploy/tests/fakes) that record their calls in $CALLS_LOG.

readonly GITHUB_URL=https://github.com/joeljoanlopez/jolo-market.git
readonly GITHUB_REPO=joeljoanlopez/jolo-market

setup_repos() {
    ORIGIN="$BATS_TEST_TMPDIR/origin.git"
    DEV="$BATS_TEST_TMPDIR/dev"
    SERVER="$BATS_TEST_TMPDIR/server"
    CALLS_LOG="$BATS_TEST_TMPDIR/calls.log"
    export CALLS_LOG
    : >"$CALLS_LOG"
    : >"$CALLS_LOG.cwd"

    export GIT_AUTHOR_NAME=test GIT_AUTHOR_EMAIL=test@example.test
    export GIT_COMMITTER_NAME=test GIT_COMMITTER_EMAIL=test@example.test
    # The copies resolve the GitHub URL to the bare repository, for every git command.
    export GIT_CONFIG_COUNT=2
    export GIT_CONFIG_KEY_0="url.$ORIGIN.insteadOf" GIT_CONFIG_VALUE_0="$GITHUB_URL"
    export GIT_CONFIG_KEY_1=init.defaultBranch GIT_CONFIG_VALUE_1=main

    git init --quiet --bare "$ORIGIN"
    git clone --quiet "$GITHUB_URL" "$DEV" 2>/dev/null
    mkdir -p "$DEV/deploy"
    cp /code/deploy/*.sh "$DEV/deploy/"
    printf '.env\n' >"$DEV/.gitignore"
    printf 'app\n' >"$DEV/app.txt"
    git -C "$DEV" add .
    git -C "$DEV" commit --quiet -m initial
    git -C "$DEV" push --quiet origin main

    git clone --quiet "$GITHUB_URL" "$SERVER"
    printf 'AUTO_DEPLOY=true\n' >"$SERVER/.env"

    PATH="/code/deploy/tests/fakes:$PATH"
    ci_green
}

# Pushes a new commit to origin/main, as a merged pull request would, and prints its sha.
merge_new_commit() {
    printf '%s\n' "${1:-change}" >>"$DEV/app.txt"
    git -C "$DEV" commit --quiet -am "${1:-change}"
    git -C "$DEV" push --quiet origin main
    git -C "$DEV" rev-parse HEAD
}

server_head() {
    git -C "$SERVER" rev-parse HEAD
}

auto_deploy() {
    "$SERVER/deploy/auto-deploy.sh"
}

ci_green() {
    export FAKE_CHECK_RUNS='{"check_runs":[{"status":"completed","conclusion":"success"},{"status":"completed","conclusion":"skipped"}]}'
    # GitHub answers "pending" for the combined status when there is no status at all.
    export FAKE_STATUS='{"total_count":0,"state":"pending"}'
}

ci_running() {
    export FAKE_CHECK_RUNS='{"check_runs":[{"status":"completed","conclusion":"success"},{"status":"in_progress","conclusion":null}]}'
}

ci_not_started() {
    export FAKE_CHECK_RUNS='{"check_runs":[]}'
}

ci_red() {
    export FAKE_CHECK_RUNS='{"check_runs":[{"status":"completed","conclusion":"success"},{"status":"completed","conclusion":"failure"}]}'
}

calls() {
    cat "$CALLS_LOG"
}

docker_calls() {
    grep '^docker ' "$CALLS_LOG" || true
}
