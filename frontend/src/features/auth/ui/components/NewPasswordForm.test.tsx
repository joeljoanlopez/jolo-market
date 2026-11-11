import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { NewPasswordForm } from './NewPasswordForm';

describe('NewPasswordForm', () => {
  it('asks for a new password, explains the policy and emits the submit', async () => {
    const onPasswordChange = vi.fn();
    const onSubmit = vi.fn();
    renderWithProviders(
      <NewPasswordForm
        password="New-password-1"
        error={null}
        pending={false}
        submitLabel="Continuar"
        onPasswordChange={onPasswordChange}
        onSubmit={onSubmit}
      />,
    );

    const field = screen.getByLabelText(es.auth.activation.password);
    expect(field).toHaveAccessibleDescription(es.auth.activation.passwordHint);
    await userEvent.type(field, '!{Enter}');

    expect(onPasswordChange).toHaveBeenCalledWith('New-password-1!');
    expect(onSubmit).toHaveBeenCalledOnce();
  });
});
