import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import type { Role } from '@/features/auth/domain/currentUser';
import { ConflictError, ValidationError } from '@/shared/http/errors';

import type { ActivationLinkGrant, AdminUser } from '../domain/adminUser';
import { useUserAdministration } from './userAdministrationContext';

export const usersQueryKey = ['admin', 'users'] as const;

export type UsersState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'success'; users: AdminUser[] };

export function useUsers(): UsersState {
  const administration = useUserAdministration();
  const query = useQuery({
    queryKey: usersQueryKey,
    queryFn: ({ signal }) => administration.listUsers({ signal }),
  });

  if (query.isPending) {
    return { status: 'loading' };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'success', users: query.data };
}

export type CreateUserError = 'taken' | 'invalid' | 'failed';

export interface CreateUser {
  create: (user: { username: string; role: Role }) => void;
  pending: boolean;
  error: CreateUserError | null;
  /** The link of the user just created. It is kept only until `dismiss()` (shown once). */
  grant: ActivationLinkGrant | null;
  dismiss: () => void;
}

export function useCreateUser(): CreateUser {
  const administration = useUserAdministration();
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: ({ username, role }: { username: string; role: Role }) =>
      administration.createUser({ username: username.trim(), role }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: usersQueryKey }),
  });

  return {
    create: (user) => {
      mutation.mutate(user);
    },
    pending: mutation.isPending,
    error: mutation.isError ? createUserErrorFor(mutation.error) : null,
    grant: mutation.data ?? null,
    dismiss: () => {
      mutation.reset();
    },
  };
}

function createUserErrorFor(error: Error): CreateUserError {
  if (error instanceof ConflictError) {
    return 'taken';
  }
  return error instanceof ValidationError ? 'invalid' : 'failed';
}
