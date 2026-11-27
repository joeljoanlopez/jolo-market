# Shortcuts for the development stack and its checks (E9, PD-58). `make` lists the targets.
# Each recipe is the command the README documents, so it can still be run by hand.

.DEFAULT_GOAL := help
SHELL := /bin/sh

COMPOSE := docker compose

.PHONY: help dev-stack-only init start stop restart logs shell migrate check check-backend check-frontend test-unit test-deploy ci-scope

help: ## List every target with its description
	@awk 'BEGIN { FS = ":.*## " } /^[a-z-]+:.*## / { printf "  %-15s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# Every target that runs Docker depends on it, so none of them touches the production stack (PD-60).
dev-stack-only:
	@if [ -f .env ] && grep -Eq '^[[:space:]]*COMPOSE_FILE=.*compose\.prod\.yaml' .env; then \
		echo "The Makefile is for the development stack only: the root .env lists compose.prod.yaml in COMPOSE_FILE." >&2; \
		exit 1; \
	fi

# Idempotent (PD-59): it never overwrites .env or backend/.env.local. The frontend dependencies are installed on the
# host before the first `up`: otherwise Docker creates frontend/node_modules as root.
init: dev-stack-only ## Set up a fresh clone: env files, dependencies, stack and migrations
	@[ -f .env ] || { cp .env.example .env && echo "Created .env from .env.example"; }
	@[ -f backend/.env.local ] || { touch backend/.env.local && echo "Created an empty backend/.env.local"; }
	@if command -v npm >/dev/null 2>&1; then \
		echo "cd frontend && npm ci"; cd frontend && npm ci; \
	else \
		echo "Warning: Node is not installed, so the frontend dependencies were not installed on the host. Install Node (see frontend/.nvmrc) and run: cd frontend && npm ci" >&2; \
	fi
	$(COMPOSE) up -d --build --wait
	$(COMPOSE) exec php composer install
	$(COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction

start: dev-stack-only ## Build and start the stack and wait until it is healthy
	$(COMPOSE) up -d --build --wait

stop: dev-stack-only ## Stop the stack (keeps the database and the other volumes)
	$(COMPOSE) down

restart: dev-stack-only ## Stop the stack and start it again
	$(COMPOSE) down
	$(COMPOSE) up -d --build --wait

logs: dev-stack-only ## Follow the logs of every service, or of one with s=<service>
	$(COMPOSE) logs -f $(s)

shell: dev-stack-only ## Open a shell in the php container
	$(COMPOSE) exec php sh

migrate: dev-stack-only ## Apply the database migrations
	$(COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction

# Runs all three even when one fails, so one run shows every problem, and fails when any of them failed.
check: dev-stack-only ## Run the backend check, the frontend check and the deploy script tests
	@status=0; \
	$(MAKE) --no-print-directory check-backend || status=1; \
	$(MAKE) --no-print-directory check-frontend || status=1; \
	$(MAKE) --no-print-directory test-deploy || status=1; \
	exit $$status

check-backend: dev-stack-only ## Run PHP-CS-Fixer, PHPStan, Deptrac and every PHPUnit suite
	$(COMPOSE) exec php composer check

test-unit: dev-stack-only ## Run the backend unit suite only (fast TDD loop)
	$(COMPOSE) exec php composer test:unit

# On the host, like CI.
check-frontend: ## Run Prettier, ESLint, tsc and Vitest on the host
	@command -v npm >/dev/null 2>&1 || { \
		echo "Node is not installed: install it (see frontend/.nvmrc) and run: cd frontend && npm ci" >&2; \
		exit 1; \
	}
	cd frontend && npm run check

test-deploy: dev-stack-only ## Run ShellCheck and the bats tests of deploy/ and the Makefile
	deploy/test.sh

# On the host, without Docker (PD-111).
ci-scope: ## Print which CI jobs the current branch runs, compared with origin/main
	deploy/ci-scope.sh
