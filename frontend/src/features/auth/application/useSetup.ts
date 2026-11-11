import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { TooManyRequestsError, ValidationError } from '@/shared/http/errors';

import { useAuthGateway } from './authGatewayContext';

const setupQueryKey = ['auth', 'setupRequired'] as const;

export type SetupStatus = 'checking' | 'required' | 'done';

/**
 * Whether the first admin still has to be created (PD-14). Checked once per visit, and only when
 * `enabled` (a logged-in user proves users exist). If the check fails the app carries on as
 * usual: the login page still works, and a fresh instance is noticed on the next visit.
 */
export function useSetupStatus(enabled = true): SetupStatus {
  const gateway = useAuthGateway();
  const query = useQuery({
    queryKey: setupQueryKey,
    queryFn: () => gateway.isSetupRequired(),
    enabled,
    staleTime: Infinity,
    retry: false,
  });

  if (!enabled || query.isError) {
    return 'done';
  }
  if (query.isPending) {
    return 'checking';
  }
  return query.data ? 'required' : 'done';
}

export type SetupError = 'invalid' | 'tooManyAttempts' | 'failed';

export interface CompleteSetup {
  submit: (code: string, username: string) => void;
  pending: boolean;
  error: SetupError | null;
  /** The activation token of the new admin, once created. */
  activationToken: string | null;
}

export function useCompleteSetup(): CompleteSetup {
  const gateway = useAuthGateway();
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: ({ code, username }: { code: string; username: string }) =>
      gateway.completeSetup(code.trim(), username.trim()),
    onSuccess: () => {
      queryClient.setQueryData(setupQueryKey, false);
    },
  });

  return {
    submit: (code, username) => {
      mutation.mutate({ code, username });
    },
    pending: mutation.isPending,
    error: mutation.isError ? setupErrorFor(mutation.error) : null,
    activationToken: mutation.data ?? null,
  };
}

function setupErrorFor(error: Error): SetupError {
  if (error instanceof ValidationError) {
    return 'invalid';
  }
  return error instanceof TooManyRequestsError ? 'tooManyAttempts' : 'failed';
}
