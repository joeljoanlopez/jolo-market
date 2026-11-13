#!/usr/bin/env bash
# Auto-deploy of jolo-market (E7, PD-50). Modelled on E11 of jolo-PM.
#
# Meant to run every few minutes from the user systemd timer (see deploy/install-auto-deploy.sh). When main on
# GitHub has a new commit whose CI is green, it fast-forwards to it and rebuilds the production stack, waiting until
# it is healthy: the php container applies the migrations when it starts (docker/php/entrypoint.sh). It does nothing
# unless the root .env has AUTO_DEPLOY=true, and it deploys only the production stack (PD-52): COMPOSE_FILE in the
# root .env must include compose.prod.yaml.
#
# Exit code: 0 when there was nothing to do, it skipped on purpose or it deployed; 1 when COMPOSE_FILE does not select
# the production stack, or the fetch, the merge, the CI lookup or compose failed.

set -euo pipefail

readonly BRANCH=main

log() { printf '%s\n' "$*"; }
err() { printf '%s\n' "$*" >&2; }

# Reads a variable of the .env without sourcing it: values such as cron expressions have unquoted spaces and
# asterisks the shell would expand. The last assignment wins, as in Docker Compose; surrounding spaces and quotes go.
env_value() {
    local name=$1 file=$2 line
    [[ -f $file ]] || return 0
    line=$(grep -E "^[[:space:]]*(export[[:space:]]+)?${name}[[:space:]]*=" "$file" | tail -n 1) || return 0
    line=${line#*=}
    line=$(printf '%s' "$line" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')
    line=${line#\"}; line=${line%\"}
    line=${line#\'}; line=${line%\'}
    printf '%s' "$line"
}

# True when a COMPOSE_FILE value lists compose.prod.yaml, alone or with override files (separated by ':'), and not
# compose.yaml: merged in, its bind mounts would hide the code baked into the production images.
selects_production_stack() {
    local file files production=false
    IFS=: read -r -a files <<<"$1"
    for file in "${files[@]}"; do
        case $file in
            compose.prod.yaml) production=true ;;
            compose.yaml) return 1 ;;
        esac
    done
    [[ $production == true ]]
}

# owner/name of the origin remote, over SSH or HTTPS.
github_repo() {
    local url
    url=$(git config --get remote.origin.url)
    url=${url%.git}
    case $url in
        git@github.com:*) printf '%s' "${url#git@github.com:}" ;;
        ssh://git@github.com/*) printf '%s' "${url#ssh://git@github.com/}" ;;
        https://github.com/*) printf '%s' "${url#https://github.com/}" ;;
        *) return 1 ;;
    esac
}

# GET on the GitHub API. Uses gh when it is installed and logged in; otherwise anonymous curl (the repository is
# public) and jq to filter.
github_api() {
    local path=$1 filter=$2
    if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
        gh api "$path" --jq "$filter"
    else
        command -v jq >/dev/null 2>&1 || { err "Without a logged-in gh, jq is needed to read the GitHub API."; return 1; }
        curl -fsSL -H 'Accept: application/vnd.github+json' \
            -H 'X-GitHub-Api-Version: 2022-11-28' \
            "https://api.github.com/$path" | jq -r "$filter"
    fi
}

# CI state of a commit: prints success, pending or failure. It looks at the check runs (GitHub Actions) and the
# classic commit statuses. No check at all counts as pending: the CI has not started yet.
ci_state() {
    local repo=$1 sha=$2 checks statuses
    checks=$(github_api "repos/$repo/commits/$sha/check-runs?per_page=100" \
        '[.check_runs[] | if .status != "completed" then "pending"
            elif (.conclusion | IN("success", "neutral", "skipped")) then "success"
            else "failure" end] | join(" ")') || return 1
    # The combined status is "pending" when there is no status at all: that one is ignored.
    statuses=$(github_api "repos/$repo/commits/$sha/status" \
        'if .total_count == 0 then "" else .state end') || return 1

    local all="$checks $statuses"
    if [[ $all == *failure* || $all == *error* ]]; then
        printf 'failure'
    elif [[ -z ${checks// /} || $all == *pending* ]]; then
        printf 'pending'
    else
        printf 'success'
    fi
}

main() {
    local repo_dir
    repo_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
    cd "$repo_dir"

    local enabled
    enabled=$(env_value AUTO_DEPLOY .env)
    if [[ $enabled != true ]]; then
        return 0
    fi

    if ! selects_production_stack "$(env_value COMPOSE_FILE .env)"; then
        err "Auto-deploy only deploys the production stack: set COMPOSE_FILE=compose.prod.yaml in the root .env."
        return 1
    fi

    # The lock and the pending-deploy marker live in .git, so they are never committed.
    local git_dir pending_file
    git_dir=$(git rev-parse --absolute-git-dir)
    pending_file="$git_dir/auto-deploy.pending"

    exec 9>"$git_dir/auto-deploy.lock"
    if ! flock -n 9; then
        log "Another run is still going; exiting."
        return 0
    fi

    local current
    current=$(git symbolic-ref --quiet --short HEAD || echo '(detached HEAD)')
    if [[ $current != "$BRANCH" ]]; then
        log "The copy is on '$current', not on '$BRANCH': not deploying."
        return 0
    fi
    if [[ -n $(git status --porcelain --untracked-files=no) ]]; then
        log "There are uncommitted changes in tracked files: not deploying."
        git status --short --untracked-files=no
        return 0
    fi

    if ! git fetch --quiet origin "$BRANCH"; then
        err "git fetch of origin/$BRANCH failed."
        return 1
    fi

    local head remote
    head=$(git rev-parse HEAD)
    remote=$(git rev-parse "origin/$BRANCH")
    if [[ $head == "$remote" ]]; then
        # The last deploy failed after the merge: HEAD is up to date but the containers are not. Its CI was green.
        if [[ -f $pending_file && $(<"$pending_file") == "$head" ]]; then
            log "Retrying the deploy of ${head:0:7}, which failed on the previous run."
            deploy "$head" "$pending_file"
            return
        fi
        return 0
    fi
    if ! git merge-base --is-ancestor HEAD "origin/$BRANCH"; then
        log "The copy has commits that are not in origin/$BRANCH: not deploying."
        return 0
    fi

    local gh_repo state
    if ! gh_repo=$(github_repo); then
        err "The origin remote does not point to GitHub: $(git config --get remote.origin.url)"
        return 1
    fi
    if ! state=$(ci_state "$gh_repo" "$remote"); then
        err "Could not read the CI of ${remote:0:7}."
        return 1
    fi
    case $state in
        pending)
            log "The CI of ${remote:0:7} is pending; trying again on the next run."
            return 0 ;;
        failure)
            log "The CI of ${remote:0:7} is red: not deploying."
            return 0 ;;
    esac

    log "Deploying ${head:0:7} → ${remote:0:7}:"
    git log --oneline "$head..$remote"

    if ! git merge --ff-only --quiet "origin/$BRANCH"; then
        err "git merge --ff-only origin/$BRANCH failed."
        return 1
    fi
    deploy "$remote" "$pending_file"
}

# Rebuilds the containers of the new commit and waits until they are healthy (configuration valid, migrations
# applied). A container that keeps restarting may never be reported as failed, so the wait is bounded: a hung run
# would hold the lock for good. Until it succeeds, the commit stays in the pending marker and the next run retries it.
deploy() {
    local sha=$1 pending_file=$2
    printf '%s\n' "$sha" >"$pending_file"
    if ! docker compose up -d --build --remove-orphans --wait --wait-timeout 600; then
        err "docker compose up failed with ${sha:0:7}, or the stack did not become healthy; trying again on the next run."
        return 1
    fi
    rm -f "$pending_file"
    log "Deployed ${sha:0:7}."
}

# Everything runs inside main: bash reads the whole function before running it, so the merge can replace this very
# file without breaking the run in progress.
main "$@"
exit $?
