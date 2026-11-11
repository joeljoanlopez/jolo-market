import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { admin, FakeAuthGateway, maria, unauthorized } from '@/test/FakeAuthGateway';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { locationOf, renderApp } from '@/test/renderApp';

import { RequireAdmin } from './RequireAdmin';
import { RequireAuth } from './RequireAuth';

const adminOnlyRoutes = [
  {
    element: <RequireAuth />,
    children: [
      {
        element: <RequireAdmin />,
        children: [{ path: 'admin', element: <h1>Admin area</h1> }],
      },
    ],
  },
  { path: 'login', element: <h1>Login</h1> },
];

describe('RequireAuth', () => {
  it('sends an anonymous visitor to /login and keeps where they were going', async () => {
    const router = renderApp('/search?q=leche');

    await waitFor(() => {
      expect(locationOf(router)).toBe('/login?next=%2Fsearch%3Fq%3Dleche');
    });
  });

  it('keeps a join link as the target, so the visitor comes back to it after login', async () => {
    const router = renderApp('/join/s3cr3t-token');

    await waitFor(() => {
      expect(locationOf(router)).toBe('/login?next=%2Fjoin%2Fs3cr3t-token');
    });
  });

  it('sends an anonymous visitor at the home page to /login without a target', async () => {
    const router = renderApp('/');

    await waitFor(() => {
      expect(locationOf(router)).toBe('/login');
    });
  });

  it('shows the page to a logged-in user', async () => {
    renderApp('/', { auth: FakeAuthGateway.loggedInAs(maria) });

    expect(
      await screen.findByRole('heading', { level: 1, name: es.home.title }),
    ).toBeInTheDocument();
  });

  it('sends the user to /login when the session ends while using the app', async () => {
    const auth = FakeAuthGateway.loggedInAs(maria);
    const catalog = new FakeProductCatalog();
    catalog.respond = () => {
      // The session expired on the server: every request now answers 401.
      auth.currentUser = null;
      return Promise.reject(unauthorized());
    };

    const router = renderApp('/search?q=leche', { auth, catalog });

    await waitFor(() => {
      expect(locationOf(router)).toBe('/login?next=%2Fsearch%3Fq%3Dleche');
    });
  });
});

describe('RequireAdmin', () => {
  it('shows an admin page to an ADMIN', async () => {
    renderApp('/admin', { auth: FakeAuthGateway.loggedInAs(admin), routes: adminOnlyRoutes });

    expect(await screen.findByRole('heading', { name: 'Admin area' })).toBeInTheDocument();
  });

  it('shows the not-found page to a USER, never a forbidden message', async () => {
    renderApp('/admin', { auth: FakeAuthGateway.loggedInAs(maria), routes: adminOnlyRoutes });

    expect(
      await screen.findByRole('heading', { level: 1, name: es.notFound.title }),
    ).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Admin area' })).not.toBeInTheDocument();
  });
});
