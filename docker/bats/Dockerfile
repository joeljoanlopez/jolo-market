# Test runner of the deploy/ shell scripts and the root Makefile (PD-51). bats-core ships flock; the tests also need
# git, jq and make.
FROM bats/bats:1.11.1
RUN apk add --no-cache git jq make
