import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FAKE_CODE, FAKE_PASSWORD, FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { locationOf, renderApp } from '@/test/renderApp';

const copy = es.auth.login;

async function submitCredentials(username = 'maria', password = FAKE_PASSWORD) {
  const user = userEvent.setup();
  await user.type(await screen.findByLabelText(copy.username), username);
  await user.type(screen.getByLabelText(copy.password), password);
  await user.click(screen.getByRole('button', { name: copy.continue }));
  return user;
}

async function submitCode(code: string) {
  const user = userEvent.setup();
  const field = await screen.findByLabelText(copy.code);
  await user.clear(field);
  await user.type(field, code);
  await user.click(screen.getByRole('button', { name: copy.submit }));
}

describe('LoginPageContainer', () => {
  it('logs in with the password and then the code, and goes back to the requested page', async () => {
    const auth = new FakeAuthGateway();
    const router = renderApp('/login?next=%2Fsearch%3Fq%3Dleche', { auth });

    await submitCredentials();
    expect(await screen.findByRole('heading', { level: 1, name: copy.codeTitle })).toBeVisible();
    await submitCode(FAKE_CODE);

    await waitFor(() => {
      expect(locationOf(router)).toBe('/search?q=leche');
    });
    expect(auth.currentUser).toEqual(maria);
  });

  it('goes to the home page when there is no requested page', async () => {
    const router = renderApp('/login');

    await submitCredentials();
    await submitCode(FAKE_CODE);

    expect(
      await screen.findByRole('heading', { level: 1, name: es.home.title }),
    ).toBeInTheDocument();
    expect(locationOf(router)).toBe('/');
  });

  it('never follows a requested page on another site', async () => {
    const router = renderApp('/login?next=%2F%2Fevil.example%2F');

    await submitCredentials();
    await submitCode(FAKE_CODE);

    await waitFor(() => {
      expect(locationOf(router)).toBe('/');
    });
  });

  it('shows the same error for a wrong password and an unknown user, and stays on the first step', async () => {
    renderApp('/login');

    await submitCredentials('maria', 'Wrong-password-1');
    expect(await screen.findByRole('alert')).toHaveTextContent(copy.invalidCredentials);

    await submitCredentials('nobody');
    expect(await screen.findByRole('alert')).toHaveTextContent(copy.invalidCredentials);
    expect(screen.getByRole('heading', { level: 1, name: copy.title })).toBeVisible();
  });

  it('lets the user try another code after a wrong one', async () => {
    const auth = new FakeAuthGateway();
    renderApp('/login', { auth });

    await submitCredentials();
    await submitCode('000000');
    expect(await screen.findByRole('alert')).toHaveTextContent(copy.invalidCode);
    expect(auth.currentUser).toBeNull();

    await submitCode(FAKE_CODE);
    await waitFor(() => {
      expect(auth.currentUser).toEqual(maria);
    });
  });

  it('goes back to the first step, with the password cleared', async () => {
    renderApp('/login');

    const user = await submitCredentials();
    await user.click(await screen.findByRole('button', { name: copy.back }));

    expect(await screen.findByLabelText(copy.username)).toHaveValue('maria');
    expect(screen.getByLabelText(copy.password)).toHaveValue('');
  });

  it('tells the user to wait when there were too many attempts', async () => {
    const auth = new FakeAuthGateway();
    auth.limited = true;
    renderApp('/login', { auth });

    await submitCredentials();

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.tooManyAttempts);
  });

  it('sends a user who is already logged in to the requested page', async () => {
    const router = renderApp('/login?next=%2Fsearch', { auth: FakeAuthGateway.loggedInAs(maria) });

    await waitFor(() => {
      expect(locationOf(router)).toBe('/search');
    });
  });
});
