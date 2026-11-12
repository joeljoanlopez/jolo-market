#!/usr/bin/env bash
# Checks the deploy/ scripts with ShellCheck and runs their bats tests, both in Docker (PD-51).
#
#   deploy/test.sh                                # everything
#   deploy/test.sh deploy/tests/post-deploy.bats  # one test file (any bats arguments)

set -euo pipefail

repo_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$repo_dir"

docker run --rm -v "$repo_dir:/code:ro" -w /code koalaman/shellcheck:v0.10.0 \
    deploy/*.sh deploy/tests/fakes/*

image=$(docker build --quiet docker/bats)
if [[ $# -eq 0 ]]; then
    set -- deploy/tests
fi
docker run --rm -v "$repo_dir:/code:ro" -w /code "$image" "$@"
