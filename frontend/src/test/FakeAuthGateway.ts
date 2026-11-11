import type { AuthGateway, Credentials } from '@/features/auth/application/AuthGateway';
import type { ActivationLink, TotpEnrollment } from '@/features/auth/domain/activation';
import type { CurrentUser } from '@/features/auth/domain/currentUser';
import {
  ConflictError,
  NotFoundError,
  TooManyRequestsError,
  UnauthorizedError,
  ValidationError,
} from '@/shared/http/errors';

export const maria: CurrentUser = { id: 'user-maria', username: 'maria', role: 'USER' };
export const admin: CurrentUser = { id: 'user-admin', username: 'admin', role: 'ADMIN' };

export const FAKE_PASSWORD = 'Correct-horse-1';
export const FAKE_CODE = '123456';
export const FAKE_SECRET = 'JBSWY3DPEHPK3PXP';
export const FAKE_ACTIVATION_TOKEN = 'activation-token-of-pedro-0123456789abcdef';
export const FAKE_NEW_DEVICE_SECRET = 'NEWDEVICESECRET234';
export const FAKE_NEW_DEVICE_CODE = '654321';
export const FAKE_SETUP_CODE = 'SETUP-CODE-1234';
export const pedro: CurrentUser = { id: 'user-pedro', username: 'pedro', role: 'USER' };

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
  /** When set, logOut rejects with it and the session stays open. */
  logOutError: Error | null = null;
  /** Pending users by activation token. A password of at least 12 characters is accepted. */
  activations = new Map<string, { user: CurrentUser; expiresAt: Date; passwordSet: boolean }>([
    [
      FAKE_ACTIVATION_TOKEN,
      { user: pedro, expiresAt: new Date('2026-11-13T10:00:00Z'), passwordSet: false },
    ],
  ]);
  /** A fresh instance: no users yet, so the first admin must be created through /setup. */
  setupRequired = false;
  setupChecks = 0;
  passwordChanges = 0;
  reEnrollmentPending = false;
  reEnrolled = 0;
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
    if (this.logOutError !== null) {
      return Promise.reject(this.logOutError);
    }
    this.loggedOut++;
    this.currentUser = null;
    return Promise.resolve();
  }

  getActivationLink(token: string): Promise<ActivationLink> {
    const activation = this.activations.get(token);
    return activation === undefined
      ? Promise.reject(notFound())
      : Promise.resolve({ username: activation.user.username, expiresAt: activation.expiresAt });
  }

  setInitialPassword(token: string, password: string): Promise<TotpEnrollment> {
    const activation = this.activations.get(token);
    if (activation === undefined) {
      return Promise.reject(notFound());
    }
    if (password.length < 12) {
      return Promise.reject(invalid());
    }
    activation.passwordSet = true;
    return Promise.resolve({
      secret: FAKE_SECRET,
      provisioningUri: `otpauth://totp/jolo-market:${activation.user.username}?secret=${FAKE_SECRET}`,
    });
  }

  confirmTotpEnrollment(token: string, code: string): Promise<CurrentUser> {
    if (this.limited) {
      return Promise.reject(tooManyRequests());
    }
    const activation = this.activations.get(token);
    if (activation === undefined) {
      return Promise.reject(notFound());
    }
    if (!activation.passwordSet || code !== FAKE_CODE) {
      return Promise.reject(invalid());
    }
    this.activations.delete(token);
    this.currentUser = activation.user;
    return Promise.resolve(activation.user);
  }

  isSetupRequired(): Promise<boolean> {
    this.setupChecks++;
    return Promise.resolve(this.setupRequired);
  }

  completeSetup(code: string, username: string): Promise<string> {
    if (this.limited) {
      return Promise.reject(tooManyRequests());
    }
    if (!this.setupRequired) {
      return Promise.reject(notFound());
    }
    if (code !== FAKE_SETUP_CODE || username.length < 3) {
      return Promise.reject(invalid());
    }
    this.setupRequired = false;
    const token = `activation-token-of-${username}-0123456789abcdef`;
    this.activations.set(token, {
      user: { id: `user-${username}`, username, role: 'ADMIN' },
      expiresAt: new Date('2026-11-13T10:00:00Z'),
      passwordSet: false,
    });
    return Promise.resolve(token);
  }

  changePassword({
    currentPassword,
    code,
    newPassword,
  }: {
    currentPassword: string;
    code: string;
    newPassword: string;
  }): Promise<void> {
    if (this.limited) {
      return Promise.reject(tooManyRequests());
    }
    if (currentPassword !== FAKE_PASSWORD || code !== FAKE_CODE || newPassword.length < 12) {
      return Promise.reject(invalid());
    }
    this.passwordChanges++;
    return Promise.resolve();
  }

  startTotpReEnrollment({
    currentPassword,
    code,
  }: {
    currentPassword: string;
    code: string;
  }): Promise<TotpEnrollment> {
    if (this.limited) {
      return Promise.reject(tooManyRequests());
    }
    if (currentPassword !== FAKE_PASSWORD || code !== FAKE_CODE) {
      return Promise.reject(invalid());
    }
    this.reEnrollmentPending = true;
    return Promise.resolve({
      secret: FAKE_NEW_DEVICE_SECRET,
      provisioningUri: `otpauth://totp/jolo-market:maria?secret=${FAKE_NEW_DEVICE_SECRET}`,
    });
  }

  confirmTotpReEnrollment(code: string): Promise<void> {
    if (!this.reEnrollmentPending) {
      return Promise.reject(
        new ConflictError({ type: 'about:blank', title: 'Conflict', status: 409 }),
      );
    }
    if (code !== FAKE_NEW_DEVICE_CODE) {
      return Promise.reject(invalid());
    }
    this.reEnrollmentPending = false;
    this.reEnrolled++;
    return Promise.resolve();
  }
}

export function unauthorized(): UnauthorizedError {
  return new UnauthorizedError({ type: 'about:blank', title: 'Unauthorized', status: 401 });
}

export function notFound(): NotFoundError {
  return new NotFoundError({ type: 'about:blank', title: 'Not Found', status: 404 });
}

export function invalid(): ValidationError {
  return new ValidationError({ type: 'about:blank', title: 'Unprocessable Content', status: 422 });
}

function tooManyRequests(): TooManyRequestsError {
  return new TooManyRequestsError({ type: 'about:blank', title: 'Too Many Requests', status: 429 });
}
