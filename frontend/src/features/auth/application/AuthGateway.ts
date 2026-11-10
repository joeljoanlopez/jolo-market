import type { ActivationLink, TotpEnrollment } from '../domain/activation';
import type { CurrentUser } from '../domain/currentUser';

export interface Credentials {
  username: string;
  password: string;
}

/**
 * Port to the session API, implemented in infrastructure/.
 *
 * Login takes two steps: `logIn` checks the password and leaves the session waiting for the
 * TOTP code; `verifyLoginCode` completes it. Both reject with `UnauthorizedError` on any
 * failure (the API never says which part was wrong) and `TooManyRequestsError` when limited.
 */
export interface AuthGateway {
  /** The user of the current session, or `null` when there is no fully authenticated one. */
  getCurrentUser(options?: { signal?: AbortSignal }): Promise<CurrentUser | null>;
  logIn(credentials: Credentials): Promise<void>;
  verifyLoginCode(code: string): Promise<CurrentUser>;
  logOut(): Promise<void>;

  /** Rejects with `NotFoundError` for an unknown, expired or used link. */
  getActivationLink(token: string): Promise<ActivationLink>;
  /**
   * Sets the first password and starts the TOTP enrollment. Rejects with `ValidationError` when
   * the password breaks the policy (PD-37) and `NotFoundError` when the link no longer works.
   */
  setInitialPassword(token: string, password: string): Promise<TotpEnrollment>;
  /**
   * Activates the account with a code from the new device and logs the user in. Rejects with
   * `ValidationError` for a wrong code and `NotFoundError` when the link no longer works.
   */
  confirmTotpEnrollment(token: string, code: string): Promise<CurrentUser>;

  /** Whether the instance still has no users, so the first admin must be created (PD-14). */
  isSetupRequired(): Promise<boolean>;
  /**
   * Creates the first admin, pending activation, and returns their activation token. Rejects
   * with `ValidationError` for a wrong code or username and `NotFoundError` once users exist.
   */
  completeSetup(code: string, username: string): Promise<string>;

  /**
   * Profile (PD-38): both changes end every other session of the user and keep this one. A wrong
   * current password or code rejects with `ValidationError`.
   */
  changePassword(change: {
    currentPassword: string;
    code: string;
    newPassword: string;
  }): Promise<void>;
  /** Starts enrolling a new device; the current one keeps working until it is confirmed. */
  startTotpReEnrollment(proof: { currentPassword: string; code: string }): Promise<TotpEnrollment>;
  /**
   * Confirms the new device with one of its codes. A wrong code rejects with `ValidationError`,
   * no enrollment in progress with `ConflictError`.
   */
  confirmTotpReEnrollment(code: string): Promise<void>;
}
