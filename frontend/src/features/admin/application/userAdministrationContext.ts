import { createContext, useContext } from 'react';

import type { UserAdministration } from './UserAdministration';

/** Injects the UserAdministration adapter; the app provides the API client, tests a fake. */
export const UserAdministrationContext = createContext<UserAdministration | null>(null);

export function useUserAdministration(): UserAdministration {
  const administration = useContext(UserAdministrationContext);
  if (administration === null) {
    throw new Error('useUserAdministration() needs a UserAdministrationContext provider.');
  }
  return administration;
}
