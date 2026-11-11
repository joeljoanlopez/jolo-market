import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { CreateUserForm } from './CreateUserForm';

describe('CreateUserForm', () => {
  it('emits the username, the role and the submit', async () => {
    const props = {
      username: 'pedr',
      role: 'USER' as const,
      error: null,
      pending: false,
      onUsernameChange: vi.fn(),
      onRoleChange: vi.fn(),
      onSubmit: vi.fn(),
    };
    renderWithProviders(<CreateUserForm {...props} />);

    await userEvent.type(screen.getByLabelText(es.admin.users.create.username), 'o');
    await userEvent.selectOptions(
      screen.getByLabelText(es.admin.users.create.role),
      es.admin.users.roles.ADMIN,
    );
    await userEvent.click(screen.getByRole('button', { name: es.admin.users.create.submit }));

    expect(props.onUsernameChange).toHaveBeenCalledWith('pedro');
    expect(props.onRoleChange).toHaveBeenCalledWith('ADMIN');
    expect(props.onSubmit).toHaveBeenCalledOnce();
  });
});
