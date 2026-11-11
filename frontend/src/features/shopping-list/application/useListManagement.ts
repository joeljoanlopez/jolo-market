import { useMutation, useQueryClient } from '@tanstack/react-query';

import { shoppingListKeys } from './queryKeys';
import { useShoppingLists } from './shoppingListsContext';

export type ListAction = 'regenerateLink' | 'removeMember' | 'delete' | 'leave';

type ListCommand =
  | { action: 'regenerateLink' }
  | { action: 'removeMember'; userId: string }
  | { action: 'delete' }
  | { action: 'leave' };

export interface ListManagement {
  /** Owner: a new secret link; the old one stops working, members stay (PD-2). */
  regenerateLink: () => void;
  /** Owner. */
  removeMember: (userId: string) => void;
  /** Owner: removes the list for everyone (PD-44); `onDone` runs once it is gone. */
  deleteList: (onDone: () => void) => void;
  /** Member (PD-44); `onDone` runs once the user is out. */
  leaveList: (onDone: () => void) => void;
  pending: boolean;
  failedAction: ListAction | null;
}

/** Membership and lifetime of a list: link, members, delete and leave. One action at a time. */
export function useListManagement(listId: string): ListManagement {
  const lists = useShoppingLists();
  const queryClient = useQueryClient();
  const key = shoppingListKeys.detail(listId);

  const mutation = useMutation({
    mutationFn: async (command: ListCommand): Promise<void> => {
      switch (command.action) {
        case 'regenerateLink':
          await lists.regenerateShareToken(listId);
          return;
        case 'removeMember':
          return lists.removeMember(listId, command.userId);
        case 'delete':
          return lists.deleteList(listId);
        case 'leave':
          return lists.leaveList(listId);
      }
    },
    onSettled: (_result, _error, command) => {
      const reloads = [queryClient.invalidateQueries({ queryKey: shoppingListKeys.mine })];
      // After a delete or a leave the list is gone for this user: reading it would be a 404.
      if (command.action !== 'delete' && command.action !== 'leave') {
        reloads.push(queryClient.invalidateQueries({ queryKey: key }));
      }
      return Promise.all(reloads);
    },
  });

  const run = (command: ListCommand, onDone?: () => void) => {
    mutation.mutate(command, {
      onSuccess: () => {
        onDone?.();
        if (command.action === 'delete' || command.action === 'leave') {
          queryClient.removeQueries({ queryKey: key });
        }
      },
    });
  };

  return {
    regenerateLink: () => {
      run({ action: 'regenerateLink' });
    },
    removeMember: (userId) => {
      run({ action: 'removeMember', userId });
    },
    deleteList: (onDone) => {
      run({ action: 'delete' }, onDone);
    },
    leaveList: (onDone) => {
      run({ action: 'leave' }, onDone);
    },
    pending: mutation.isPending,
    failedAction: mutation.isError ? mutation.variables.action : null,
  };
}
