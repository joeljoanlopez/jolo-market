import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { NetworkError } from '@/shared/http/errors';
import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria, unauthorized } from '@/test/FakeAuthGateway';
import { locationOf, renderApp } from '@/test/renderApp';

const copy = es.auth.session;

describe('SessionMenuContainer', () => {
  it('shows who is logged in, in the app header', async () => {
    renderApp('/', { auth: FakeAuthGateway.loggedInAs(maria) });

    const header = screen.getByRole('banner');
    expect(await within(header).findByText('maria')).toBeInTheDocument();
    expect(within(header).getByRole('button', { name: copy.logOut })).toBeInTheDocument();
  });

  it('logs out and goes to the login page, without a page to come back to', async () => {
    const auth = FakeAuthGateway.loggedInAs(maria);
    const router = renderApp('/search?q=leche', { auth });

    await userEvent.click(await screen.findByRole('button', { name: copy.logOut }));

    await waitFor(() => {
      expect(locationOf(router)).toBe('/login');
    });
    expect(auth.loggedOut).toBe(1);
    expect(screen.queryByRole('button', { name: copy.logOut })).not.toBeInTheDocument();
  });

  it('treats a session the server already ended as logged out', async () => {
    const auth = FakeAuthGateway.loggedInAs(maria);
    auth.logOutError = unauthorized();
    const router = renderApp('/', { auth });

    await userEvent.click(await screen.findByRole('button', { name: copy.logOut }));

    await waitFor(() => {
      expect(locationOf(router)).toBe('/login');
    });
  });

  it('keeps the session and says so when the logout did not reach the server', async () => {
    const auth = FakeAuthGateway.loggedInAs(maria);
    auth.logOutError = new NetworkError();
    const router = renderApp('/', { auth });

    await userEvent.click(await screen.findByRole('button', { name: copy.logOut }));

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.logOutFailed);
    expect(locationOf(router)).toBe('/');
  });

  it('shows no session menu on the login page', async () => {
    renderApp('/login');

    expect(await screen.findByRole('heading', { name: es.auth.login.title })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: copy.logOut })).not.toBeInTheDocument();
  });
});
