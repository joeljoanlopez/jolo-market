import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import { TooManyRequestsError, UnauthorizedError } from '@/shared/http/errors';

import type { Credentials } from './AuthGateway';
import { useAuthGateway } from './authGatewayContext';
import { useSetCurrentUser } from './useCurrentUser';

export type LoginStep = 'credentials' | 'code';
export type LoginError = 'invalidCredentials' | 'invalidCode' | 'tooManyAttempts' | 'failed';

export interface Login {
  step: LoginStep;
  error: LoginError | null;
  pending: boolean;
  submitCredentials(credentials: Credentials): void;
  submitCode(code: string): void;
  /** Back to the credentials step, e.g. after the 2FA-pending login expired. */
  back(): void;
}

/**
 * The two-step login (docs/architecture.md#4-login). Once the code is accepted the session user
 * is stored, so whatever reads useCurrentUser() sees the user at once.
 */
export function useLogin(): Login {
  const gateway = useAuthGateway();
  const setCurrentUser = useSetCurrentUser();
  const [step, setStep] = useState<LoginStep>('credentials');

  const credentials = useMutation({
    mutationFn: (submitted: Credentials) => gateway.logIn(submitted),
    onSuccess: () => {
      setStep('code');
    },
  });
  const code = useMutation({
    mutationFn: (submitted: string) => gateway.verifyLoginCode(submitted),
    onSuccess: setCurrentUser,
  });

  const current = step === 'credentials' ? credentials : code;

  return {
    step,
    error: current.isError ? loginErrorFor(current.error, step) : null,
    pending: current.isPending,
    submitCredentials: (submitted) => {
      credentials.mutate({ username: submitted.username.trim(), password: submitted.password });
    },
    submitCode: (submitted) => {
      code.mutate(submitted.replace(/\s/g, ''));
    },
    back: () => {
      credentials.reset();
      code.reset();
      setStep('credentials');
    },
  };
}

function loginErrorFor(error: Error, step: LoginStep): LoginError {
  if (error instanceof UnauthorizedError) {
    return step === 'credentials' ? 'invalidCredentials' : 'invalidCode';
  }
  return error instanceof TooManyRequestsError ? 'tooManyAttempts' : 'failed';
}
