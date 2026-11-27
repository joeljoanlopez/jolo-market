#!/usr/bin/env bats
# PD-115: deploy/issue-status.sh sets the native status of the GitHub issue an epic was requested in, from the epic's
# branch name. Each test runs in a temporary repository with docs/epics/ files; gh is a fake that records its calls.

load test_helper

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    CALLS_LOG="$BATS_TEST_TMPDIR/calls.log"
    export CALLS_LOG GITHUB_REPOSITORY=$GITHUB_REPO
    : >"$CALLS_LOG"
    PATH="/code/deploy/tests/fakes:$PATH"

    mkdir -p "$REPO/docs/epics"
    cd "$REPO"
    epic E13-filter-list-by-supermarket 59
    epic E12-path-filtered-ci 40
    epic E12-product-search-in-lists 41
    epic E3-identity-and-access ""
}

# Writes an epic file; a non-empty issue number adds the "Requested in" link the real epics carry.
epic() {
    local goal='**Goal:** something useful.'
    if [[ -n $2 ]]; then
        goal="$goal Requested in [issue #$2](https://github.com/$GITHUB_REPO/issues/$2)."
    fi
    printf '# %s\n\n%s\n' "$1" "$goal" >"docs/epics/$1.md"
}

issue_status() {
    /code/deploy/issue-status.sh "$@"
}

patches() {
    grep -A1 '^gh api -X PATCH' "$CALLS_LOG" | grep -v '^--$' || true
}

@test "creating an implementation branch sets the epic's issue to In progress" {
    run issue_status branch-created feat/e13-filter-list-by-supermarket

    [ "$status" -eq 0 ]
    [ "$(patches)" = "gh api -X PATCH repos/$GITHUB_REPO/issues/59 --input -
patch-body {\"status\":\"In progress\"}" ]
}

@test "creating a docs branch never sets In progress" {
    run issue_status branch-created docs/e13-filter-list-by-supermarket

    [ "$status" -eq 0 ]
    [ -z "$(patches)" ]
}

@test "opening the pull request of an implementation branch sets the issue to In review" {
    run issue_status pull-request-opened feat/e13-filter-list-by-supermarket "Goal and tasks."

    [ "$status" -eq 0 ]
    [[ "$(patches)" == *"issues/59 "*'{"status":"In review"}' ]]
}

@test "opening the pull request that adds an epic sets the issue to Todo" {
    run issue_status pull-request-opened docs/e13-filter-list-by-supermarket "Adds the epic."

    [ "$status" -eq 0 ]
    [[ "$(patches)" == *"issues/59 "*'{"status":"Todo"}' ]]
}

@test "a docs branch whose pull request closes the issue is an implementation, so it sets In review" {
    run issue_status pull-request-opened docs/e13-filter-list-by-supermarket "Implements it. Closes #59"

    [ "$status" -eq 0 ]
    [[ "$(patches)" == *'{"status":"In review"}' ]]
}

@test "a reused epic number picks the epic file whose slug matches the branch" {
    run issue_status branch-created feat/e12-product-search-in-lists

    [ "$status" -eq 0 ]
    [[ "$(patches)" == *"issues/41 "* ]]
}

@test "an epic without an issue changes nothing" {
    run issue_status branch-created feat/e3-identity-and-access

    [ "$status" -eq 0 ]
    [ -z "$(patches)" ]
}

@test "a branch that is not an epic branch changes nothing" {
    run issue_status branch-created feat/something-else

    [ "$status" -eq 0 ]
    [ -z "$(patches)" ]
}

@test "a branch whose epic file does not exist changes nothing" {
    run issue_status branch-created feat/e99-does-not-exist

    [ "$status" -eq 0 ]
    [ -z "$(patches)" ]
}

@test "it fails when GitHub refuses the change, so the workflow turns red" {
    export FAKE_GH_PATCH_FAIL=1

    run issue_status branch-created feat/e13-filter-list-by-supermarket

    [ "$status" -ne 0 ]
    [[ "$output" == *"issue #59"* ]]
}

@test "an unknown event is a usage error" {
    run issue_status something-else feat/e13-filter-list-by-supermarket

    [ "$status" -eq 2 ]
    [[ "$output" == *"Usage"* ]]
}
