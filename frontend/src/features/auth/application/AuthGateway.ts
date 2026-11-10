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
}
