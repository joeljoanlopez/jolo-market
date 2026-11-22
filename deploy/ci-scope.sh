#!/usr/bin/env bash
# Prints which CI jobs the current branch needs, from the files it changes since its merge-base with a base ref
# (PD-107 to PD-110). CI's "changes" job writes the output to $GITHUB_OUTPUT.
#
#   deploy/ci-scope.sh               # against origin/main
#   deploy/ci-scope.sh <base-ref>
#
# Output, one per line: backend=, frontend=, e2e=, deploy= with true or false.

set -euo pipefail

base_ref=${1:-origin/main}
backend=false
frontend=false
e2e=false
deploy=false

everything() {
    backend=true
    frontend=true
    e2e=true
    deploy=true
}

print_scope() {
    printf 'backend=%s\nfrontend=%s\ne2e=%s\ndeploy=%s\n' "$backend" "$frontend" "$e2e" "$deploy"
}

if ! merge_base=$(git merge-base "$base_ref" HEAD 2>/dev/null); then
    echo "ci-scope: no merge-base with $base_ref, so every job runs." >&2
    everything
    print_scope
    exit 0
fi

# With --no-renames a rename lists its old and new paths, so both locations trigger their jobs.
while IFS= read -r path; do
    case $path in
        # Documentation first: a *.md file anywhere triggers nothing (PD-109).
        docs/* | *.md | .claude/* | LICENSE) ;;
        .github/workflows/* | deploy/ci-scope.sh) everything ;;
        frontend/*) frontend=true e2e=true ;;
        # PD-110: deploy/test.sh ShellChecks it.
        docker/php/entrypoint.sh) backend=true e2e=true deploy=true ;;
        backend/* | compose.yaml | .env.example | docker/php/* | docker/mysql/*) backend=true e2e=true ;;
        docker/nginx/*) e2e=true ;;
        deploy/* | Makefile | docker/bats/*) deploy=true ;;
        *) everything ;;
    esac
done < <(git diff --name-only --no-renames "$merge_base" HEAD)

print_scope
