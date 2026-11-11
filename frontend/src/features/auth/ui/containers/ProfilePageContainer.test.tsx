import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import {
  FAKE_CODE,
  FAKE_NEW_DEVICE_CODE,
  FAKE_NEW_DEVICE_SECRET,
  FAKE_PASSWORD,
  FakeAuthGateway,
  maria,
} from '@/test/FakeAuthGateway';
import { renderApp } from '@/test/renderApp';

const copy = es.auth.profile;

function renderProfile(auth = FakeAuthGateway.loggedInAs(maria)) {
  renderApp('/profile', { auth });
  return auth;
}

async function section(name: string): Promise<HTMLElement> {
  return screen.findByRole('region', { name });
}

async function changePassword(current: string, code: string, newPassword: string) {
  const form = within(await section(copy.password.title));
  await userEvent.type(form.getByLabelText(copy.password.current), current);
  await userEvent.type(form.getByLabelText(copy.password.code), code);
  await userEvent.type(form.getByLabelText(copy.password.new), newPassword);
  await userEvent.click(form.getByRole('button', { name: copy.password.submit }));
}

async function startReEnrollment(current = FAKE_PASSWORD, code = FAKE_CODE) {
  const form = within(await section(copy.totp.title));
  await userEvent.type(form.getByLabelText(copy.totp.current), current);
  await userEvent.type(form.getByLabelText(copy.totp.code), code);
  await userEvent.click(form.getByRole('button', { name: copy.totp.continue }));
}

async function confirmNewDevice(code: string) {
  const form = within(await section(copy.totp.title));
  const field = await form.findByLabelText(es.auth.activation.code);
  await userEvent.clear(field);
  await userEvent.type(field, code);
  await userEvent.click(form.getByRole('button', { name: copy.totp.submit }));
}

describe('ProfilePageContainer', () => {
  it('is linked from the header for every logged-in user', async () => {
    renderApp('/', { auth: FakeAuthGateway.loggedInAs(maria) });

    expect(
      await within(screen.getByRole('banner')).findByRole('link', { name: copy.nav }),
    ).toHaveAttribute('href', '/profile');
  });

  describe('change password', () => {
    it('changes the password and says the other sessions ended', async () => {
      const auth = renderProfile();

      await changePassword(FAKE_PASSWORD, FAKE_CODE, 'New-password-1!');

      expect(
        await within(await section(copy.password.title)).findByRole('status'),
      ).toHaveTextContent(copy.password.done);
      expect(auth.passwordChanges).toBe(1);
      expect(
        within(await section(copy.password.title)).getByLabelText(copy.password.new),
      ).toHaveValue('');
    });

    it('explains a weak new password before sending it', async () => {
      const auth = renderProfile();

      await changePassword(FAKE_PASSWORD, FAKE_CODE, 'weak');

      expect(
        await within(await section(copy.password.title)).findByRole('alert'),
      ).toHaveTextContent(copy.password.weak);
      expect(auth.passwordChanges).toBe(0);
    });

    it('says when the current password or the code is wrong', async () => {
      renderProfile();

      await changePassword('Wrong-password-1!', FAKE_CODE, 'New-password-1!');

      expect(
        await within(await section(copy.password.title)).findByRole('alert'),
      ).toHaveTextContent(copy.password.rejected);
    });

    it('tells the user to wait after too many attempts', async () => {
      const auth = FakeAuthGateway.loggedInAs(maria);
      auth.limited = true;
      renderProfile(auth);

      await changePassword(FAKE_PASSWORD, FAKE_CODE, 'New-password-1!');

      expect(
        await within(await section(copy.password.title)).findByRole('alert'),
      ).toHaveTextContent(copy.tooManyAttempts);
    });
  });

  describe('re-enroll TOTP', () => {
    it('proves the user, shows the new device and activates it with one of its codes', async () => {
      const auth = renderProfile();

      await startReEnrollment();
      const form = within(await section(copy.totp.title));
      expect(await form.findByRole('img', { name: es.auth.activation.qrLabel })).toBeVisible();
      expect(form.getByText(FAKE_NEW_DEVICE_SECRET)).toBeVisible();
      await confirmNewDevice(FAKE_NEW_DEVICE_CODE);

      expect(await form.findByRole('status')).toHaveTextContent(copy.totp.done);
      expect(auth.reEnrolled).toBe(1);
    });

    it('says when the password or the current code is wrong', async () => {
      renderProfile();

      await startReEnrollment('Wrong-password-1!');

      expect(await within(await section(copy.totp.title)).findByRole('alert')).toHaveTextContent(
        copy.totp.rejected,
      );
    });

    it('lets the user try another code from the new device', async () => {
      const auth = renderProfile();

      await startReEnrollment();
      await confirmNewDevice(FAKE_CODE);
      expect(await within(await section(copy.totp.title)).findByRole('alert')).toHaveTextContent(
        copy.totp.codeInvalid,
      );

      await confirmNewDevice(FAKE_NEW_DEVICE_CODE);
      await waitFor(() => {
        expect(auth.reEnrolled).toBe(1);
      });
    });

    it('can be cancelled, which keeps the current device', async () => {
      const auth = renderProfile();

      await startReEnrollment();
      const form = within(await section(copy.totp.title));
      await userEvent.click(await form.findByRole('button', { name: copy.totp.cancel }));

      expect(form.getByLabelText(copy.totp.current)).toHaveValue('');
      expect(form.queryByRole('img', { name: es.auth.activation.qrLabel })).not.toBeInTheDocument();
      expect(auth.reEnrolled).toBe(0);
    });

    it('starts again when the enrollment is no longer in progress', async () => {
      const auth = renderProfile();

      await startReEnrollment();
      auth.reEnrollmentPending = false;
      await confirmNewDevice(FAKE_NEW_DEVICE_CODE);

      const form = within(await section(copy.totp.title));
      expect(await form.findByRole('alert')).toHaveTextContent(copy.totp.expired);
      expect(form.getByLabelText(copy.totp.current)).toBeVisible();
    });
  });
});
