# E3 — Identity & access

**Goal:** a closed instance where an admin creates users. Each user activates their account through a one-time link, sets a password and enrolls mandatory TOTP 2FA. Afterwards, the whole app requires login + 2FA.

Related decisions: PD-2, PD-9, PD-12, PD-13, PD-14, PD-15. Open questions: OQ-8, OQ-9, OQ-10, OQ-11, OQ-12.

## Acceptance criteria

- On first start (no users), the backend prints a one-time setup code in the logs. The SPA redirects to `/setup`, where the code plus a username create the first admin, who then follows the normal activation flow. Once any user exists, `/setup` returns `404`.
- An admin can create a user (username + role) and gets an activation link that is **shown once** and expires.
- Through the activation link, the user sets a password, scans a TOTP QR code and confirms a code. Only then is the account `ACTIVE`.
- Logging in requires username + password **and** a valid TOTP code. A wrong password or code gives the same generic error and is rate-limited.
- Every `/api` route except login and activation returns `401` without a fully authenticated session. Admin routes return `404` for non-admins.
- Used, expired or unknown activation tokens return `404`.
- Only hashes of passwords and activation tokens are stored. TOTP secrets are encrypted at rest.

## Tasks

### Domain (Identity)

- [ ] E3.1 Add the `Username` value object (allowed characters and length, case-insensitive, normalized to lowercase), plus `Role` and `UserStatus` enums. Move `UserId` into the shared kernel.
- [ ] E3.2 Add the `ActivationToken` value object (token hash + expiry) and the `ActivationTokenGenerator` port.
- [ ] E3.3 Add the `User` aggregate: `createPending` (by admin), `setInitialPassword`, `startTotpEnrollment`, `confirmTotp` (→ `ACTIVE`). Invariants: no `ACTIVE` without password and confirmed TOTP; an activation token is single-use and expires.
- [ ] E3.4 Add the ports `UserRepository`, `PasswordHasher`, `TotpService` and `SecretEncrypter`.
- [ ] E3.5 ❓ OQ-11 Agree on the password policy and enforce it in a `PlainPassword` value object.

### Application

- [ ] E3.6 Add the `CreateUser` use case (admin only, via an explicit actor check), which returns the plain activation token once.
- [ ] E3.7 Add the `ValidateActivationToken`, `SetInitialPassword` (returns TOTP provisioning URI) and `ConfirmTotpEnrollment` use cases.
- [ ] E3.8 Add the `ListUsers` query (admin).
- [ ] E3.9 Add a `UserDirectory` query service (id → username) for other contexts.
- [ ] E3.10 ❓ OQ-8 Agree on the activation link lifetime and on regenerating links for pending users.
- [ ] E3.11 ❓ OQ-9 Define the lost password / lost 2FA flow (admin reset? recovery codes?).
- [ ] E3.12 ❓ OQ-10 Define admin management beyond creation (disable, delete, change role).
- [ ] E3.13 ❓ OQ-12 Decide whether there is a self-service profile page (change password, re-enroll TOTP).

### Infrastructure

- [ ] E3.14 Add a migration for the `user` table.
- [ ] E3.15 Add a DBAL `UserRepository` (integration tests).
- [ ] E3.16 Add adapters: `PasswordHasher` (Symfony `auto` hasher), `TotpService` (scheb/2fa-totp), `SecretEncrypter` (libsodium with `TOTP_ENCRYPTION_KEY`), and a secure `ActivationTokenGenerator`. Each has its own tests.
- [ ] E3.17 Configure Symfony Security: a JSON login firewall with a session cookie (`HttpOnly`, `Secure`, `SameSite=Lax`), a user provider backed by the Identity application layer, and `scheb/2fa-bundle` making TOTP mandatory after password.
- [ ] E3.18 Add CSRF protection for state-changing requests (SameSite plus a required custom header or a token). Functional tests prove that a cross-site request is rejected.
- [ ] E3.19 Rate-limit login and 2FA attempts per username and per IP (Symfony RateLimiter). Functional tests check the lockout response.
- [ ] E3.20 Add the endpoints `/api/auth/*` and `/api/admin/users`, with functional tests for each: happy path, wrong credentials, wrong code, expired/used token, non-admin access → 404.
- [ ] E3.21 Protect every existing `/api` route (catalog endpoints from E2 included) and update their functional tests to use an authenticated test client helper.
- [ ] E3.22 Add first-admin setup (PD-14). At container startup, `app:setup:ensure-code` generates a setup code **only if there are no users**, stores its hash and prints the plain code in the logs (a new code on each start replaces the old one). Add a `CompleteSetup` use case (valid code + no users → ADMIN pending activation) and the `GET/POST /api/setup` endpoints. Functional tests cover: wrong code, users already exist (→ 404), and that the code cannot be reused.
- [ ] E3.22b Add the frontend `/setup` page: the app redirects there when `GET /api/setup` says it is required. It asks for the code and username, then continues to `/activate/:token`.

### Frontend

- [ ] E3.23 Add an `auth` feature: domain types (`CurrentUser`, `Role`) and an API client (MSW tests).
- [ ] E3.24 Add a `useCurrentUser` hook and route guards (authenticated, admin), and redirect to `/login` on `401` while keeping the original target (for `/join/:token`). The admin guard renders the **not-found page** for non-admins, never a "forbidden" message (PD-15).
- [ ] E3.24b Render admin navigation and actions only for `ADMIN` users (PD-15). Component tests check that a `USER` sees no admin entry points.
- [ ] E3.25 Add a login page in two steps: credentials, then TOTP code.
- [ ] E3.26 Add an activation page `/activate/:token`: set password → QR code + manual secret → confirm code. It has an invalid/expired link state.
- [ ] E3.27 Add an admin users page: list users with status, create user, and show the activation link once with a copy button.
- [ ] E3.28 Add logout in the app shell.
- [ ] E3.29 Add a Playwright journey: fresh instance → `/setup` with the code from the logs → admin activates → admin creates a user → user activates (TOTP from a known test secret) → logs out → logs in with 2FA.
