import { useMutation, useQueryClient } from '@tanstack/react-query';

import { ConflictError } from '@/shared/http/errors';

import type { ActivationLinkGrant, AdminUser } from '../domain/adminUser';
import { useUserAdministration } from './userAdministrationContext';
import { usersQueryKey } from './useUsers';

export type UserAction = 'regenerateLink' | 'reset' | 'disable' | 'enable' | 'changeRole';

export interface UserActions {
  run: (action: UserAction, user: AdminUser) => void;
  /** The user whose action is running, if any. */
  pendingUserId: string | null;
  /** The last action that failed: `refused` is a rule of PD-34 to PD-36 (409). */
  failure: { userId: string; action: UserAction; refused: boolean } | null;
  /** A new link from a regenerate or a reset, kept only until `dismissGrant()` (shown once). */
  grant: ActivationLinkGrant | null;
  dismissGrant: () => void;
}

export function useUserActions(): UserActions {
  const administration = useUserAdministration();
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: ({
      action,
      user,
    }: {
      action: UserAction;
      user: AdminUser;
    }): Promise<ActivationLinkGrant | undefined> => {
      switch (action) {
        case 'regenerateLink':
          return administration.regenerateActivationLink(user.id);
        case 'reset':
          return administration.resetUser(user.id);
        case 'disable':
          return administration.disableUser(user.id).then(() => undefined);
        case 'enable':
          return administration.enableUser(user.id).then(() => undefined);
        case 'changeRole':
          return administration
            .changeRole(user.id, user.role === 'ADMIN' ? 'USER' : 'ADMIN')
            .then(() => undefined);
      }
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: usersQueryKey }),
  });

  const variables = mutation.variables;
  return {
    run: (action, user) => {
      mutation.mutate({ action, user });
    },
    pendingUserId: mutation.isPending && variables !== undefined ? variables.user.id : null,
    failure:
      mutation.isError && variables !== undefined
        ? {
            userId: variables.user.id,
            action: variables.action,
            refused: mutation.error instanceof ConflictError,
          }
        : null,
    grant: mutation.data ?? null,
    dismissGrant: () => {
      mutation.reset();
    },
  };
}
