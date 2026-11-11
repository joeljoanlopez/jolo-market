import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { anAdminUser } from '@/test/FakeUserAdministration';
import { renderWithProviders } from '@/test/render';

import { UserActionButtons } from './UserActionButtons';

const actions = es.admin.users.actions;
const maria = anAdminUser({ id: 'u2', username: 'maria', role: 'USER' });

describe('UserActionButtons', () => {
  it('emits a confirmed action only after the confirmation', async () => {
    const onAction = vi.fn();
    renderWithProviders(
      <UserActionButtons
        user={maria}
        isOwnAccount={false}
        pending={false}
        error={null}
        onAction={onAction}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: actions.disable }));
    expect(onAction).not.toHaveBeenCalled();
    await userEvent.click(screen.getByRole('button', { name: actions.confirm }));

    expect(onAction).toHaveBeenCalledWith('disable');
  });

  it('emits an immediate action at once', async () => {
    const onAction = vi.fn();
    renderWithProviders(
      <UserActionButtons
        user={maria}
        isOwnAccount={false}
        pending={false}
        error={null}
        onAction={onAction}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: actions.makeAdmin }));

    expect(onAction).toHaveBeenCalledWith('changeRole');
  });

  it('disables the buttons while an action runs and shows its error', () => {
    renderWithProviders(
      <UserActionButtons
        user={maria}
        isOwnAccount={false}
        pending
        error="No se puede."
        onAction={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: actions.makeAdmin })).toBeDisabled();
    expect(screen.getByRole('alert')).toHaveTextContent('No se puede.');
  });
});
