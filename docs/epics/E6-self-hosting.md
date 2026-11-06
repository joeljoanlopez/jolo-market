# E6 — Self-hosting

**Goal:** someone can deploy their own instance on a single host with Docker Compose, configure it through `.env`, and keep it running safely.

Related decisions: PD-5, PD-9.

## Acceptance criteria

- `docker compose -f compose.prod.yaml up -d` starts a production-ready stack: built frontend served by nginx, php-fpm with opcache, the scheduler worker and MySQL with a persistent volume.
- Migrations run automatically (or through one documented command) on deploy.
- The README documents installation, configuration, upgrades, backups and restore.
- The README explains the first-start setup: where to find the setup code in the logs (`docker compose logs php`) and how to open `/setup`.
- The first ingestion can be triggered manually, and its status is visible in the logs.

## Tasks

- [ ] E6.1 Add multi-stage Dockerfiles: a PHP production image (no dev dependencies, opcache on) and a frontend build stage copied into nginx.
- [ ] E6.2 Add `compose.prod.yaml` with health checks, restart policies and named volumes.
- [ ] E6.3 Add an entrypoint that waits for MySQL and runs migrations.
- [ ] E6.4 Validate configuration at boot: fail fast with a clear message if `INSTANCE_POSTAL_CODE`, `ENABLED_SUPERMARKETS` or `TOTP_ENCRYPTION_KEY` is missing or invalid (covered by tests).
- [ ] E6.5 Add an nginx config with SPA fallback, cache headers for static assets and `/api` proxying.
- [ ] E6.6 Add a backup script (`mysqldump`) and document the restore procedure.
- [ ] E6.7 Write operator docs in the README: install, `.env` reference, reverse proxy and TLS example, how to restrict access (PD-9), upgrade and backup steps.
- [ ] E6.8 Add a CI job that builds the production images on every push to `main`.
