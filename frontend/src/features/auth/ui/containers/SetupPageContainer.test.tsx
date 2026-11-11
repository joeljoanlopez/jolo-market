import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FAKE_SETUP_CODE, FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { locationOf, renderApp } from '@/test/renderApp';

const copy = es.auth.setup;

function freshInstance(): FakeAuthGateway {
  const auth = new FakeAuthGateway();
  auth.setupRequired = true;
  return auth;
}

async function submitSetup(code = FAKE_SETUP_CODE, username = 'admin') {
  await userEvent.type(await screen.findByLabelText(copy.code), code);
  await userEvent.type(screen.getByLabelText(copy.username), username);
  await userEvent.click(screen.getByRole('button', { name: copy.submit }));
}

describe('setup of a fresh instance', () => {
  it.each(['/', '/search?q=leche', '/login'])(
    'sends a visitor at %s to /setup while the instance has no users',
    async (path) => {
      const router = renderApp(path, { auth: freshInstance() });

      await waitFor(() => {
        expect(locationOf(router)).toBe('/setup');
      });
      expect(await screen.findByRole('heading', { level: 1, name: copy.title })).toBeVisible();
    },
  );

  it('creates the first admin and continues to their activation link', async () => {
    const router = renderApp('/setup', { auth: freshInstance() });

    await submitSetup();

    await waitFor(() => {
      expect(locationOf(router)).toBe('/activate/activation-token-of-admin-0123456789abcdef');
    });
    expect(
      await screen.findByText('Hola, admin. Elige una contraseña para tu cuenta.'),
    ).toBeVisible();
  });

  it('explains the username format and shows an error for a wrong code', async () => {
    renderApp('/setup', { auth: freshInstance() });

    expect(await screen.findByLabelText(copy.username)).toHaveAccessibleDescription(
      copy.usernameHint,
    );
    await submitSetup('WRONG-CODE');

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.invalid);
  });

  it('tells the visitor to wait after too many attempts', async () => {
    const auth = freshInstance();
    auth.limited = true;
    renderApp('/setup', { auth });

    await submitSetup();

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.tooManyAttempts);
  });

  it('leaves /setup once the instance has users', async () => {
    const router = renderApp('/setup');

    await waitFor(() => {
      expect(locationOf(router)).toBe('/login');
    });
  });

  it('never asks about the setup for a logged-in user', async () => {
    const auth = FakeAuthGateway.loggedInAs(maria);
    renderApp('/', { auth });

    expect(await screen.findByRole('heading', { level: 1, name: es.home.title })).toBeVisible();
    expect(auth.setupChecks).toBe(0);
  });
});
