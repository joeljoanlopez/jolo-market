import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { ChangePasswordForm } from './ChangePasswordForm';

const copy = es.auth.profile.password;
const fields = { currentPassword: 'old', code: '12345', newPassword: 'new' };

describe('ChangePasswordForm', () => {
  it('emits every field change and the submit', async () => {
    const onChange = vi.fn();
    const onSubmit = vi.fn();
    renderWithProviders(
      <ChangePasswordForm
        fields={fields}
        error={null}
        done={false}
        pending={false}
        onChange={onChange}
        onSubmit={onSubmit}
      />,
    );

    await userEvent.type(screen.getByLabelText(copy.code), '6');
    await userEvent.type(screen.getByLabelText(copy.new), '!{Enter}');

    expect(onChange).toHaveBeenCalledWith({ ...fields, code: '123456' });
    expect(onChange).toHaveBeenCalledWith({ ...fields, newPassword: 'new!' });
    expect(onSubmit).toHaveBeenCalledOnce();
    expect(screen.getByLabelText(copy.new)).toHaveAccessibleDescription(copy.newHint);
  });

  it('announces when the password was changed', () => {
    renderWithProviders(
      <ChangePasswordForm
        fields={fields}
        error={null}
        done
        pending={false}
        onChange={vi.fn()}
        onSubmit={vi.fn()}
      />,
    );

    expect(screen.getByRole('status')).toHaveTextContent(copy.done);
  });
});
