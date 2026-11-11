import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { TotpEnrollmentForm } from './TotpEnrollmentForm';

const enrollment = {
  secret: 'JBSWY3DPEHPK3PXP',
  provisioningUri: 'otpauth://totp/jolo-market:pedro?secret=JBSWY3DPEHPK3PXP',
};

function renderForm(overrides: Partial<Parameters<typeof TotpEnrollmentForm>[0]> = {}) {
  const props = {
    enrollment,
    code: '12345',
    error: null,
    pending: false,
    submitLabel: 'Activar cuenta',
    onCodeChange: vi.fn(),
    onSubmit: vi.fn(),
    ...overrides,
  };
  renderWithProviders(<TotpEnrollmentForm {...props} />);
  return props;
}

describe('TotpEnrollmentForm', () => {
  it('shows the QR code and the secret to type by hand', () => {
    renderForm();

    expect(screen.getByRole('img', { name: es.auth.activation.qrLabel })).toBeInTheDocument();
    expect(screen.getByText(enrollment.secret)).toBeInTheDocument();
  });

  it('emits the code and the submit', async () => {
    const props = renderForm();

    await userEvent.type(screen.getByLabelText(es.auth.activation.code), '6');
    await userEvent.click(screen.getByRole('button', { name: 'Activar cuenta' }));

    expect(props.onCodeChange).toHaveBeenCalledWith('123456');
    expect(props.onSubmit).toHaveBeenCalledOnce();
  });

  it('shows the error it is given and cannot be sent while pending', () => {
    renderForm({ error: 'El código no es válido.', pending: true });

    expect(screen.getByRole('alert')).toHaveTextContent('El código no es válido.');
    expect(screen.getByRole('button', { name: 'Activar cuenta' })).toBeDisabled();
  });
});
