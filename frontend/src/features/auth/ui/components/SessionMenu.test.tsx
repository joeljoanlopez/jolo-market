import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { SessionMenu } from './SessionMenu';

function renderMenu(menu: ReactElement) {
  return renderWithProviders(<MemoryRouter>{menu}</MemoryRouter>);
}

describe('SessionMenu', () => {
  it('shows the username and emits the logout', async () => {
    const onLogOut = vi.fn();
    renderMenu(
      <SessionMenu
        username="maria"
        showAdminLinks={false}
        pending={false}
        failed={false}
        onLogOut={onLogOut}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: es.auth.session.logOut }));

    expect(screen.getByText('maria')).toBeInTheDocument();
    expect(onLogOut).toHaveBeenCalledOnce();
  });

  it('cannot log out twice while the first logout is pending', () => {
    renderMenu(
      <SessionMenu
        username="maria"
        showAdminLinks={false}
        pending
        failed={false}
        onLogOut={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: es.auth.session.logOut })).toBeDisabled();
  });

  it('says when the logout failed', () => {
    renderMenu(
      <SessionMenu
        username="maria"
        showAdminLinks={false}
        pending={false}
        failed
        onLogOut={vi.fn()}
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent(es.auth.session.logOutFailed);
  });

  it('links to the profile, and to the admin pages only when asked', () => {
    renderMenu(
      <SessionMenu
        username="ana"
        showAdminLinks
        pending={false}
        failed={false}
        onLogOut={vi.fn()}
      />,
    );

    expect(screen.getByRole('link', { name: es.auth.profile.nav })).toHaveAttribute(
      'href',
      '/profile',
    );
    expect(screen.getByRole('link', { name: es.admin.users.nav })).toHaveAttribute(
      'href',
      '/admin/users',
    );
  });
});
