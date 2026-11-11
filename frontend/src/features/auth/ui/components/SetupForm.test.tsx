import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { SetupForm } from './SetupForm';

describe('SetupForm', () => {
  it('emits the code, the username and the submit', async () => {
    const props = {
      code: 'CODE',
      username: 'admi',
      error: null,
      pending: false,
      onCodeChange: vi.fn(),
      onUsernameChange: vi.fn(),
      onSubmit: vi.fn(),
    };
    renderWithProviders(<SetupForm {...props} />);

    await userEvent.type(screen.getByLabelText(es.auth.setup.code), '1');
    await userEvent.type(screen.getByLabelText(es.auth.setup.username), 'n{Enter}');

    expect(props.onCodeChange).toHaveBeenCalledWith('CODE1');
    expect(props.onUsernameChange).toHaveBeenCalledWith('admin');
    expect(props.onSubmit).toHaveBeenCalledOnce();
  });

  it('shows the error it is given and cannot be sent while pending', () => {
    renderWithProviders(
      <SetupForm
        code=""
        username=""
        error="El código no es válido."
        pending
        onCodeChange={vi.fn()}
        onUsernameChange={vi.fn()}
        onSubmit={vi.fn()}
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('El código no es válido.');
    expect(screen.getByRole('button', { name: es.auth.setup.submit })).toBeDisabled();
  });
});
