import { useMutation, useQuery } from '@tanstack/react-query';

import { NotFoundError, TooManyRequestsError, ValidationError } from '@/shared/http/errors';

import type { TotpEnrollment } from '../domain/activation';
import { useAuthGateway } from './authGatewayContext';
import { useSetCurrentUser } from './useCurrentUser';

export type ActivationError = 'passwordInvalid' | 'codeInvalid' | 'tooManyAttempts' | 'failed';

export type Activation =
  | { status: 'checking' }
  | { status: 'checkFailed'; retry: () => void }
  | { status: 'invalid' }
  /** The code was accepted: the account is active and the user is logged in. */
  | { status: 'activated' }
  | {
      status: 'password';
      username: string;
      error: ActivationError | null;
      pending: boolean;
      submitPassword: (password: string) => void;
    }
  | {
      status: 'totp';
      enrollment: TotpEnrollment;
      error: ActivationError | null;
      pending: boolean;
      submitCode: (code: string) => void;
    };

/**
 * The activation flow of /activate/:token (docs/architecture.md#3-user-onboarding). The link
 * can stop working at any step (it expired, or an admin made a new one): it then becomes
 * `invalid`. Once the code is accepted the user is logged in.
 */
export function useActivation(token: string): Activation {
  const gateway = useAuthGateway();
  const setCurrentUser = useSetCurrentUser();

  const link = useQuery({
    queryKey: ['auth', 'activationLink', token],
    queryFn: () => gateway.getActivationLink(token),
    retry: false,
    staleTime: Infinity,
  });
  const password = useMutation({
    mutationFn: (submitted: string) => gateway.setInitialPassword(token, submitted),
  });
  const code = useMutation({
    mutationFn: (submitted: string) =>
      gateway.confirmTotpEnrollment(token, submitted.replace(/\s/g, '')),
    onSuccess: setCurrentUser,
  });

  if (
    link.error instanceof NotFoundError ||
    password.error instanceof NotFoundError ||
    code.error instanceof NotFoundError
  ) {
    return { status: 'invalid' };
  }
  if (code.isSuccess) {
    return { status: 'activated' };
  }
  if (link.isPending) {
    return { status: 'checking' };
  }
  if (link.isError) {
    return { status: 'checkFailed', retry: () => void link.refetch() };
  }

  if (password.data === undefined) {
    return {
      status: 'password',
      username: link.data.username,
      error: password.isError ? activationErrorFor(password.error, 'passwordInvalid') : null,
      pending: password.isPending,
      submitPassword: (submitted) => {
        password.mutate(submitted);
      },
    };
  }

  return {
    status: 'totp',
    enrollment: password.data,
    error: code.isError ? activationErrorFor(code.error, 'codeInvalid') : null,
    pending: code.isPending,
    submitCode: (submitted) => {
      code.mutate(submitted);
    },
  };
}

function activationErrorFor(error: Error, invalidInput: ActivationError): ActivationError {
  if (error instanceof ValidationError) {
    return invalidInput;
  }
  return error instanceof TooManyRequestsError ? 'tooManyAttempts' : 'failed';
}
