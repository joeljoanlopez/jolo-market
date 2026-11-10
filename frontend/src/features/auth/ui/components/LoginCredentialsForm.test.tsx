import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { LoginCredentialsForm } from './LoginCredentialsForm';

function renderForm(overrides: Partial<Parameters<typeof LoginCredentialsForm>[0]> = {}) {
  const props = {
    username: 'maria',
    password: 'secret',
    error: null,
    pending: false,
    onUsernameChange: vi.fn(),
    onPasswordChange: vi.fn(),
    onSubmit: vi.fn(),
    ...overrides,
  };
  renderWithProviders(<LoginCredentialsForm {...props} />);
  return props;
}

describe('LoginCredentialsForm', () => {
  it('emits each change and the submit, without reloading the page', async () => {
    const props = renderForm();
    const user = userEvent.setup();

    await user.type(screen.getByLabelText(es.auth.login.username), 'a');
    await user.type(screen.getByLabelText(es.auth.login.password), 'b{Enter}');

    expect(props.onUsernameChange).toHaveBeenCalledWith('mariaa');
    expect(props.onPasswordChange).toHaveBeenCalledWith('secretb');
    expect(props.onSubmit).toHaveBeenCalledOnce();
  });

  it('lets the password manager fill the fields', () => {
    renderForm();

    expect(screen.getByLabelText(es.auth.login.username)).toHaveAttribute(
      'autocomplete',
      'username',
    );
    expect(screen.getByLabelText(es.auth.login.password)).toHaveAttribute(
      'autocomplete',
      'current-password',
    );
  });

  it('shows the error it is given', () => {
    renderForm({ error: 'Usuario o contraseña incorrectos.' });

    expect(screen.getByRole('alert')).toHaveTextContent('Usuario o contraseña incorrectos.');
  });

  it('cannot be sent twice while the first attempt is pending', () => {
    renderForm({ pending: true });

    expect(screen.getByRole('button', { name: es.auth.login.continue })).toBeDisabled();
  });
});
