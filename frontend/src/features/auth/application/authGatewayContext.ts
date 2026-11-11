import { createContext, useContext } from 'react';

import type { AuthGateway } from './AuthGateway';

/** Injects the AuthGateway adapter; the app provides the API client, tests a fake. */
export const AuthGatewayContext = createContext<AuthGateway | null>(null);

export function useAuthGateway(): AuthGateway {
  const gateway = useContext(AuthGatewayContext);
  if (gateway === null) {
    throw new Error('useAuthGateway() needs an AuthGatewayContext provider.');
  }
  return gateway;
}
