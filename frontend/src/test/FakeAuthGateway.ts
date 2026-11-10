import type { AuthGateway, Credentials } from '@/features/auth/application/AuthGateway';
import type { CurrentUser } from '@/features/auth/domain/currentUser';
import { TooManyRequestsError, UnauthorizedError } from '@/shared/http/errors';

export const maria: CurrentUser = { id: 'user-maria', username: 'maria', role: 'USER' };
export const admin: CurrentUser = { id: 'user-admin', username: 'admin', role: 'ADMIN' };

export const FAKE_PASSWORD = 'Correct-horse-1';
export const FAKE_CODE = '123456';

/**
 * In-memory session. `users` can log in with FAKE_PASSWORD and FAKE_CODE; anything else fails
 * like the API does (same UnauthorizedError for every wrong part).
 */
export class FakeAuthGateway implements AuthGateway {
  currentUser: CurrentUser | null = null;
  users: CurrentUser[] = [maria, admin];
  /** When true, every login step answers 429. */
  limited = false;
  loggedOut = 0;
  private pending: CurrentUser | null = null;

  static loggedInAs(user: CurrentUser): FakeAuthGateway {
    const gateway = new FakeAuthGateway();
    gateway.currentUser = user;
    return gateway;
  }

  getCurrentUser(): Promise<CurrentUser | null> {
    return Promise.resolve(this.currentUser);
  }

  logIn({ username, password }: Credentials): Promise<void> {
    if (this.limited) {
      return Promise.reject(tooManyRequests());
    }
    const user = this.users.find((candidate) => candidate.username === username);
    if (user === undefined || password !== FAKE_PASSWORD) {
      return Promise.reject(unauthorized());
    }
    this.pending = user;
    return Promise.resolve();
  }

  verifyLoginCode(code: string): Promise<CurrentUser> {
    if (this.limited) {
      return Promise.reject(tooManyRequests());
    }
    if (this.pending === null || code !== FAKE_CODE) {
      return Promise.reject(unauthorized());
    }
    this.currentUser = this.pending;
    this.pending = null;
    return Promise.resolve(this.currentUser);
  }

  logOut(): Promise<void> {
    this.loggedOut++;
    this.currentUser = null;
    return Promise.resolve();
  }
}

export function unauthorized(): UnauthorizedError {
  return new UnauthorizedError({ type: 'about:blank', title: 'Unauthorized', status: 401 });
}

function tooManyRequests(): TooManyRequestsError {
  return new TooManyRequestsError({ type: 'about:blank', title: 'Too Many Requests', status: 429 });
}
