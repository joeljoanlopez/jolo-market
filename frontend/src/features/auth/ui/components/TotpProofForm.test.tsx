import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { TotpProofForm } from './TotpProofForm';

const copy = es.auth.profile.totp;

describe('TotpProofForm', () => {
  it('emits the password, the code of the current device and the submit', async () => {
    const props = {
      currentPassword: 'pw',
      code: '12345',
      error: null,
      done: false,
      pending: false,
      onCurrentPasswordChange: vi.fn(),
      onCodeChange: vi.fn(),
      onSubmit: vi.fn(),
    };
    renderWithProviders(<TotpProofForm {...props} />);

    await userEvent.type(screen.getByLabelText(copy.current), 'x');
    await userEvent.type(screen.getByLabelText(copy.code), '6{Enter}');

    expect(props.onCurrentPasswordChange).toHaveBeenCalledWith('pwx');
    expect(props.onCodeChange).toHaveBeenCalledWith('123456');
    expect(props.onSubmit).toHaveBeenCalledOnce();
  });
});
