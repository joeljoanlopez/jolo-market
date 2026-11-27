#!/usr/bin/env bash
# Sets the native status of the GitHub issue an epic was requested in, from the name of the epic's branch (PD-115).
# CI's "Issue status" workflow calls it; run it from the repository root, with the branch checked out.
#
#   deploy/issue-status.sh branch-created <branch>
#   deploy/issue-status.sh pull-request-opened <branch> <pull request body>
#
# Epic branches are <type>/e<n>-<slug>, where <slug> is the slug of docs/epics/E<n>-<slug>.md. The issue is the one in the
# epic's "Requested in [issue #m]" link. A branch, an epic or an issue that does not fit is skipped without failing.
#
#   branch-created        <type> other than docs -> In progress (docs/ is /new-epic's branch, not an implementation)
#   pull-request-opened   docs/ branch           -> Todo, or In review when the body closes the issue (a docs epic)
#                         any other epic branch  -> In review

set -euo pipefail

usage() {
    echo "Usage: $0 branch-created <branch> | pull-request-opened <branch> <pull request body>" >&2
    exit 2
}

[[ $# -ge 2 ]] || usage
event=$1
branch=$2
body=${3:-}

if [[ ! $branch =~ ^([a-z]+)/(e[0-9]+-.+)$ ]]; then
    exit 0
fi
type=${BASH_REMATCH[1]}
slug=${BASH_REMATCH[2]}

# The epic file of the branch: its lowercase name without .md is the branch slug.
epic_file=
for file in docs/epics/E[0-9]*.md; do
    name=$(basename "$file" .md)
    if [[ ${name,,} == "$slug" ]]; then
        epic_file=$file
    fi
done
if [[ -z $epic_file ]]; then
    echo "issue-status: no epic file for $branch, skipped." >&2
    exit 0
fi

if [[ ! $(<"$epic_file") =~ issue\ \#([0-9]+) ]]; then
    echo "issue-status: $epic_file has no \"Requested in issue\" link, skipped." >&2
    exit 0
fi
issue=${BASH_REMATCH[1]}

case $event in
    branch-created)
        [[ $type != docs ]] || exit 0
        new_status="In progress"
        ;;
    pull-request-opened)
        if [[ $type != docs ]] || grep -qiE "(close[sd]?|fix(es|ed)?|resolve[sd]?) #$issue\b" <<<"$body"; then
            new_status="In review"
        else
            new_status="Todo"
        fi
        ;;
    *) usage ;;
esac

repo=${GITHUB_REPOSITORY:-joeljoanlopez/jolo-market}
if ! printf '{"status":"%s"}' "$new_status" | gh api -X PATCH "repos/$repo/issues/$issue" --input - >/dev/null; then
    echo "issue-status: could not set issue #$issue to \"$new_status\"." >&2
    exit 1
fi
echo "issue-status: issue #$issue is now \"$new_status\"."
