import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { LoginCodeForm } from './LoginCodeForm';

function renderForm(overrides: Partial<Parameters<typeof LoginCodeForm>[0]> = {}) {
  const props = {
    code: '',
    error: null,
    pending: false,
    onCodeChange: vi.fn(),
    onSubmit: vi.fn(),
    onBack: vi.fn(),
    ...overrides,
  };
  renderWithProviders(<LoginCodeForm {...props} />);
  return props;
}

describe('LoginCodeForm', () => {
  it('asks for a one-time code with a numeric keyboard, explained by its hint', () => {
    renderForm();

    const field = screen.getByLabelText(es.auth.login.code);
    expect(field).toHaveAttribute('inputmode', 'numeric');
    expect(field).toHaveAttribute('autocomplete', 'one-time-code');
    expect(field).toHaveAccessibleDescription(es.auth.login.codeHint);
  });

  it('emits the code, the submit and going back', async () => {
    const props = renderForm({ code: '12345' });
    const user = userEvent.setup();

    await user.type(screen.getByLabelText(es.auth.login.code), '6');
    await user.click(screen.getByRole('button', { name: es.auth.login.submit }));
    await user.click(screen.getByRole('button', { name: es.auth.login.back }));

    expect(props.onCodeChange).toHaveBeenCalledWith('123456');
    expect(props.onSubmit).toHaveBeenCalledOnce();
    expect(props.onBack).toHaveBeenCalledOnce();
  });

  it('shows the error it is given', () => {
    renderForm({ error: 'El código no es válido.' });

    expect(screen.getByRole('alert')).toHaveTextContent('El código no es válido.');
  });
});
