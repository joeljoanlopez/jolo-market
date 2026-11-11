import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { NetworkError } from '@/shared/http/errors';
import es from '@/shared/i18n/locales/es.json';
import {
  FAKE_ACTIVATION_TOKEN,
  FAKE_CODE,
  FAKE_SECRET,
  FakeAuthGateway,
  maria,
  pedro,
} from '@/test/FakeAuthGateway';
import { locationOf, renderApp } from '@/test/renderApp';

const copy = es.auth.activation;
const path = `/activate/${FAKE_ACTIVATION_TOKEN}`;

async function setPassword(password = 'New-password-1!') {
  await userEvent.type(await screen.findByLabelText(copy.password), password);
  await userEvent.click(screen.getByRole('button', { name: copy.continue }));
}

async function confirmCode(code: string) {
  const field = await screen.findByLabelText(copy.code);
  await userEvent.clear(field);
  await userEvent.type(field, code);
  await userEvent.click(screen.getByRole('button', { name: copy.activate }));
}

describe('ActivationPageContainer', () => {
  it('greets the user of the link and explains the password policy', async () => {
    renderApp(path);

    expect(
      await screen.findByText('Hola, pedro. Elige una contraseña para tu cuenta.'),
    ).toBeVisible();
    expect(screen.getByLabelText(copy.password)).toHaveAccessibleDescription(copy.passwordHint);
    expect(screen.getByLabelText(copy.password)).toHaveAttribute('autocomplete', 'new-password');
  });

  it('activates the account: password, then QR code and manual secret, then a code', async () => {
    const auth = new FakeAuthGateway();
    const router = renderApp(path, { auth });

    await setPassword();
    expect(await screen.findByRole('heading', { level: 1, name: copy.totpTitle })).toBeVisible();
    expect(screen.getByRole('img', { name: copy.qrLabel })).toBeInTheDocument();
    expect(screen.getByText(FAKE_SECRET)).toBeInTheDocument();
    await confirmCode(FAKE_CODE);

    // The activation also logs the user in.
    await waitFor(() => {
      expect(locationOf(router)).toBe('/');
    });
    expect(await screen.findByRole('heading', { level: 1, name: es.home.title })).toBeVisible();
    expect(auth.currentUser).toEqual(pedro);
  });

  it('shows the invalid link state for an unknown, expired or used link', async () => {
    renderApp('/activate/not-a-valid-token');

    expect(await screen.findByRole('heading', { level: 1, name: copy.invalidTitle })).toBeVisible();
    expect(screen.getByText(copy.invalid)).toBeInTheDocument();
    expect(screen.queryByLabelText(copy.password)).not.toBeInTheDocument();
  });

  it('shows the invalid link state when the link stops working halfway', async () => {
    const auth = new FakeAuthGateway();
    renderApp(path, { auth });
    await screen.findByLabelText(copy.password);

    auth.activations.clear();
    await setPassword();

    expect(await screen.findByRole('heading', { level: 1, name: copy.invalidTitle })).toBeVisible();
  });

  it('keeps the user on the password step when the password breaks the policy', async () => {
    renderApp(path);

    await setPassword('short');

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.passwordInvalid);
    expect(screen.getByLabelText(copy.password)).toBeVisible();
  });

  it('lets the user try another code after a wrong one', async () => {
    const auth = new FakeAuthGateway();
    renderApp(path, { auth });

    await setPassword();
    await confirmCode('000000');
    expect(await screen.findByRole('alert')).toHaveTextContent(copy.codeInvalid);

    await confirmCode(FAKE_CODE);
    await waitFor(() => {
      expect(auth.currentUser).toEqual(pedro);
    });
  });

  it('tells the user to wait when there were too many codes', async () => {
    const auth = new FakeAuthGateway();
    renderApp(path, { auth });

    await setPassword();
    auth.limited = true;
    await confirmCode(FAKE_CODE);

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.tooManyAttempts);
  });

  it('offers a retry when the link could not be checked', async () => {
    const auth = new FakeAuthGateway();
    const getLink = auth.getActivationLink.bind(auth);
    let calls = 0;
    auth.getActivationLink = (token) => {
      calls++;
      return calls === 1 ? Promise.reject(new NetworkError()) : getLink(token);
    };
    renderApp(path, { auth });

    expect(await screen.findByText(copy.checkFailed)).toBeVisible();
    await userEvent.click(screen.getByRole('button', { name: copy.retry }));

    expect(await screen.findByLabelText(copy.password)).toBeVisible();
  });

  it('works for someone who is logged in as another user, who then becomes the new user', async () => {
    const auth = FakeAuthGateway.loggedInAs(maria);
    renderApp(path, { auth });

    await setPassword();
    await confirmCode(FAKE_CODE);

    await waitFor(() => {
      expect(auth.currentUser).toEqual(pedro);
    });
  });
});

describe('ActivationPageContainer while the code is being checked', () => {
  it('stays on the page until the code is accepted, also for someone already logged in', async () => {
    const auth = FakeAuthGateway.loggedInAs(maria);
    const confirm = auth.confirmTotpEnrollment.bind(auth);
    let release: () => void = () => undefined;
    auth.confirmTotpEnrollment = (token, code) =>
      new Promise((resolve, reject) => {
        release = () => {
          confirm(token, code).then(resolve, reject);
        };
      });
    const router = renderApp(path, { auth });

    await setPassword();
    await confirmCode(FAKE_CODE);

    expect(locationOf(router)).toBe(path);
    release();
    await waitFor(() => {
      expect(locationOf(router)).toBe('/');
    });
  });
});
