import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import { ConflictError, TooManyRequestsError, ValidationError } from '@/shared/http/errors';

import type { TotpEnrollment } from '../domain/activation';
import { meetsPasswordPolicy } from '../domain/passwordPolicy';
import { useAuthGateway } from './authGatewayContext';

export type ChangePasswordError = 'weak' | 'rejected' | 'tooManyAttempts' | 'failed';

export interface ChangePassword {
  submit: (change: { currentPassword: string; code: string; newPassword: string }) => void;
  pending: boolean;
  error: ChangePasswordError | null;
  done: boolean;
}

/**
 * PD-38. The new password is checked against PD-37 before sending, so a 422 from the API can only
 * mean a wrong current password or code.
 */
export function useChangePassword(onDone: () => void): ChangePassword {
  const gateway = useAuthGateway();
  const [weak, setWeak] = useState(false);
  const mutation = useMutation({
    mutationFn: (change: { currentPassword: string; code: string; newPassword: string }) =>
      gateway.changePassword({ ...change, code: change.code.replace(/\s/g, '') }),
    onSuccess: onDone,
  });

  return {
    submit: (change) => {
      const isWeak = !meetsPasswordPolicy(change.newPassword);
      setWeak(isWeak);
      if (isWeak) {
        mutation.reset();
        return;
      }
      mutation.mutate(change);
    },
    pending: mutation.isPending,
    error: weak ? 'weak' : mutation.isError ? profileErrorFor(mutation.error, 'rejected') : null,
    done: mutation.isSuccess,
  };
}

export type TotpReEnrollmentError =
  'rejected' | 'codeInvalid' | 'expired' | 'tooManyAttempts' | 'failed';

export type TotpReEnrollment =
  | {
      step: 'proof';
      pending: boolean;
      error: TotpReEnrollmentError | null;
      done: boolean;
      start: (proof: { currentPassword: string; code: string }) => void;
    }
  | {
      step: 'confirm';
      enrollment: TotpEnrollment;
      pending: boolean;
      error: TotpReEnrollmentError | null;
      confirm: (code: string) => void;
      cancel: () => void;
    };

/**
 * PD-38: prove the user (password + a code from the current device), then confirm the new device
 * with one of its codes. Until then the current device keeps working, so cancelling is safe.
 */
export function useTotpReEnrollment(): TotpReEnrollment {
  const gateway = useAuthGateway();
  const [expired, setExpired] = useState(false);

  const start = useMutation({
    mutationFn: (proof: { currentPassword: string; code: string }) =>
      gateway.startTotpReEnrollment({ ...proof, code: proof.code.replace(/\s/g, '') }),
    onMutate: () => {
      setExpired(false);
    },
  });
  const confirm = useMutation({
    mutationFn: (code: string) => gateway.confirmTotpReEnrollment(code.replace(/\s/g, '')),
    onSuccess: () => {
      start.reset();
    },
    onError: (error) => {
      // Nothing to confirm any more (e.g. it was started again elsewhere): start over.
      if (error instanceof ConflictError) {
        setExpired(true);
        start.reset();
      }
    },
  });

  if (start.data !== undefined) {
    return {
      step: 'confirm',
      enrollment: start.data,
      pending: confirm.isPending,
      error: confirm.isError ? profileErrorFor(confirm.error, 'codeInvalid') : null,
      confirm: (code) => {
        confirm.mutate(code);
      },
      cancel: () => {
        start.reset();
        confirm.reset();
      },
    };
  }

  return {
    step: 'proof',
    pending: start.isPending,
    error: expired ? 'expired' : start.isError ? profileErrorFor(start.error, 'rejected') : null,
    done: confirm.isSuccess,
    start: (proof) => {
      confirm.reset();
      start.mutate(proof);
    },
  };
}

function profileErrorFor<T extends string>(
  error: Error,
  invalidInput: T,
): T | 'tooManyAttempts' | 'failed' {
  if (error instanceof ValidationError) {
    return invalidInput;
  }
  return error instanceof TooManyRequestsError ? 'tooManyAttempts' : 'failed';
}
