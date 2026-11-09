# E3 — Identity & access

**Goal:** a closed instance where an admin creates users. Each user activates their account through a one-time link, sets a password and enrolls mandatory TOTP 2FA. Afterwards, the whole app requires login + 2FA.

Related decisions: PD-2, PD-9, PD-12, PD-13, PD-14, PD-15, PD-34, PD-35, PD-36, PD-37, PD-38, PD-39.

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

- [x] E3.1 Add the `Username` value object (allowed characters and length, PD-39, case-insensitive, normalized to lowercase), plus `Role` and `UserStatus` enums. Move `UserId` into the shared kernel.
- [x] E3.2 Add the `ActivationToken` value object (token hash + expiry) and the `ActivationTokenGenerator` port.
- [x] E3.3 Add the `User` aggregate: `createPending` (by admin), `setInitialPassword`, `startTotpEnrollment`, `confirmTotp` (→ `ACTIVE`). Invariants: no `ACTIVE` without password and confirmed TOTP; an activation token is single-use and expires.
- [x] E3.4 Add the ports `UserRepository`, `PasswordHasher`, `TotpService` and `SecretEncrypter`.
- [x] E3.5 Enforce the password policy (PD-37) in a `PlainPassword` value object.

### Application

- [x] E3.6 Add the `CreateUser` use case (admin only, via an explicit actor check), which returns the plain activation token once.
- [x] E3.7 Add the `ValidateActivationToken`, `SetInitialPassword` (returns TOTP provisioning URI) and `ConfirmTotpEnrollment` use cases.
- [x] E3.8 Add the `ListUsers` query (admin).
- [x] E3.9 Add a `UserDirectory` query service (id → username) for other contexts.
- [x] E3.10 Activation links last 72 hours (PD-34). Add the `RegenerateActivationLink` use case (admin, pending users only; the old link stops working).
- [x] E3.11 Add the `ResetUser` use case (PD-35): admin only, back to pending activation, password and TOTP removed, sessions ended, new activation link. Add the `app:user:reset <username>` console command for the server operator.
- [x] E3.12 Add the `DisableUser`, `EnableUser` and `ChangeUserRole` use cases (PD-36): no delete, the last active admin cannot be disabled or demoted, an admin cannot disable themselves; disabling ends the user's sessions.
- [x] E3.13 Add the `ChangePassword` and `StartTotpReEnrollment` / `ConfirmTotpReEnrollment` use cases (PD-38). Both end every other session of the user and keep the current one.

### Infrastructure

- [x] E3.14 Add a migration for the `user` table.
- [x] E3.15 Add a DBAL `UserRepository` (integration tests).
- [x] E3.16 Add adapters: `PasswordHasher` (Symfony `auto` hasher), `TotpService` (`spomky-labs/otphp`, the TOTP library scheb/2fa-totp is built on), `SecretEncrypter` (libsodium with `TOTP_ENCRYPTION_KEY`), and a secure `ActivationTokenGenerator`. Each has its own tests.
- [x] E3.17 Configure Symfony Security: a JSON login firewall with a session cookie (`HttpOnly`, `Secure`, `SameSite=Lax`), a user provider backed by the Identity application layer, and TOTP mandatory after password.
  - Note: confirmed on 2026-11-09 to use our own two-step flow (use cases + `Security::login()`) instead of `scheb/2fa-bundle`.
- [x] E3.18 Add CSRF protection for state-changing requests (SameSite plus a required custom header or a token). Functional tests prove that a cross-site request is rejected.
- [x] E3.19 Rate-limit login and 2FA attempts per username and per IP (Symfony RateLimiter). Functional tests check the lockout response.
- [ ] E3.20 Add the endpoints `/api/auth/*`, `/api/me/*` and `/api/admin/users/*`, with functional tests for each: happy path, wrong credentials, wrong code, expired/used token, non-admin access → 404.
  - Note: `/api/auth/*` (login, 2fa, logout, me, activation) are done; `/api/admin/users/*` and `/api/me/*` come in the next PR.
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
- [ ] E3.28b Add the admin actions on the users page: regenerate link, reset, disable/enable and change role (PD-34, PD-35, PD-36).
- [ ] E3.28c Add a profile page: change password and re-enroll TOTP (PD-38).
- [ ] E3.29 Add a Playwright journey: fresh instance → `/setup` with the code from the logs → admin activates → admin creates a user → user activates (TOTP from a known test secret) → logs out → logs in with 2FA.
