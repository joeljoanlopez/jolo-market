import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { anAdminUser } from '@/test/FakeUserAdministration';
import { renderWithProviders } from '@/test/render';

import { UserList } from './UserList';

describe('UserList', () => {
  it('shows each user with role and status, and places the actions it is given', () => {
    renderWithProviders(
      <UserList
        users={[anAdminUser(), anAdminUser({ id: 'u2', username: 'maria', role: 'USER' })]}
        renderActions={(user) => <button type="button">{`Acción ${user.username}`}</button>}
      />,
    );

    const items = within(screen.getByRole('list', { name: es.admin.users.list })).getAllByRole(
      'listitem',
    );
    expect(items).toHaveLength(2);
    expect(items[1]).toHaveTextContent(`maria${es.admin.users.roles.USER}`);
    expect(
      within(items[1] as HTMLElement).getByRole('button', { name: 'Acción maria' }),
    ).toBeVisible();
  });
});
