import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { admin, FakeAuthGateway } from '@/test/FakeAuthGateway';
import { anAdminUser, FakeUserAdministration, LINK_EXPIRY } from '@/test/FakeUserAdministration';
import { renderApp } from '@/test/renderApp';

const copy = es.admin.users;
const actions = copy.actions;

function withUsers(): FakeUserAdministration {
  const administration = new FakeUserAdministration();
  administration.users.push(
    anAdminUser({
      id: 'user-pedro',
      username: 'pedro',
      role: 'USER',
      status: 'PENDING_ACTIVATION',
      activationExpiresAt: LINK_EXPIRY,
    }),
    anAdminUser({ id: 'user-luis', username: 'luis', role: 'USER', status: 'DISABLED' }),
  );
  return administration;
}

async function actionsOf(username: string): Promise<HTMLElement> {
  return screen.findByRole('group', { name: `Acciones de ${username}` });
}

function renderPage(administration = withUsers()) {
  renderApp('/admin/users', { auth: FakeAuthGateway.loggedInAs(admin), administration });
  return administration;
}

describe('user actions on the users page', () => {
  it('offers to each user only the actions that apply to their status', async () => {
    renderPage();

    const pending = await actionsOf('pedro');
    expect(within(pending).getByRole('button', { name: actions.regenerateLink })).toBeVisible();
    expect(within(pending).queryByRole('button', { name: actions.disable })).toBeNull();

    const active = await actionsOf('maria');
    expect(within(active).getByRole('button', { name: actions.reset })).toBeVisible();
    expect(within(active).getByRole('button', { name: actions.disable })).toBeVisible();
    expect(within(active).getByRole('button', { name: actions.makeAdmin })).toBeVisible();
    expect(within(active).queryByRole('button', { name: actions.enable })).toBeNull();

    const disabled = await actionsOf('luis');
    expect(within(disabled).getByRole('button', { name: actions.enable })).toBeVisible();
    expect(within(disabled).queryByRole('button', { name: actions.reset })).toBeNull();
  });

  it('never offers an admin to reset or disable their own account', async () => {
    renderPage();

    const own = await actionsOf('admin');
    expect(within(own).queryByRole('button', { name: actions.reset })).toBeNull();
    expect(within(own).queryByRole('button', { name: actions.disable })).toBeNull();
    expect(within(own).getByRole('button', { name: actions.makeUser })).toBeVisible();
  });

  it('generates a new link for a pending user and shows it once', async () => {
    renderPage();

    await userEvent.click(
      within(await actionsOf('pedro')).getByRole('button', { name: actions.regenerateLink }),
    );

    const panel = await screen.findByRole('region', { name: 'Enlace de activación de pedro' });
    expect(within(panel).getByLabelText(copy.link.label)).toHaveValue(
      `${window.location.origin}/activate/token-1-of-pedro`,
    );
  });

  it('asks before resetting, and shows the new link once confirmed', async () => {
    const administration = renderPage();

    await userEvent.click(
      within(await actionsOf('maria')).getByRole('button', { name: actions.reset }),
    );
    const row = await actionsOf('maria');
    expect(row).toHaveTextContent(
      '¿Resetear a maria? Perderá su contraseña y su app de autenticación',
    );
    expect(administration.users.find((user) => user.username === 'maria')?.status).toBe('ACTIVE');

    await userEvent.click(within(row).getByRole('button', { name: actions.confirm }));

    expect(
      await screen.findByRole('region', { name: 'Enlace de activación de maria' }),
    ).toBeVisible();
    expect(administration.users.find((user) => user.username === 'maria')?.status).toBe(
      'PENDING_ACTIVATION',
    );
  });

  it('cancels a disable without changing anything', async () => {
    const administration = renderPage();

    await userEvent.click(
      within(await actionsOf('maria')).getByRole('button', { name: actions.disable }),
    );
    await userEvent.click(
      within(await actionsOf('maria')).getByRole('button', { name: actions.cancel }),
    );

    expect(
      within(await actionsOf('maria')).getByRole('button', { name: actions.disable }),
    ).toBeVisible();
    expect(administration.users.find((user) => user.username === 'maria')?.status).toBe('ACTIVE');
  });

  it('disables after confirming, and enables at once', async () => {
    const administration = renderPage();

    await userEvent.click(
      within(await actionsOf('maria')).getByRole('button', { name: actions.disable }),
    );
    await userEvent.click(
      within(await actionsOf('maria')).getByRole('button', { name: actions.confirm }),
    );

    await waitFor(async () => {
      expect(
        within(await actionsOf('maria')).getByRole('button', { name: actions.enable }),
      ).toBeVisible();
    });
    expect(administration.users.find((user) => user.username === 'maria')?.status).toBe('DISABLED');

    await userEvent.click(
      within(await actionsOf('maria')).getByRole('button', { name: actions.enable }),
    );
    await waitFor(() => {
      expect(administration.users.find((user) => user.username === 'maria')?.status).toBe('ACTIVE');
    });
  });

  it('changes the role at once', async () => {
    const administration = renderPage();

    await userEvent.click(
      within(await actionsOf('maria')).getByRole('button', { name: actions.makeAdmin }),
    );

    await waitFor(async () => {
      expect(
        within(await actionsOf('maria')).getByRole('button', { name: actions.makeUser }),
      ).toBeVisible();
    });
    expect(administration.users.find((user) => user.username === 'maria')?.role).toBe('ADMIN');
  });

  it('explains a refused action next to the user', async () => {
    const administration = withUsers();
    administration.conflicts.add('user-admin');
    renderPage(administration);

    await userEvent.click(
      within(await actionsOf('admin')).getByRole('button', { name: actions.makeUser }),
    );

    const row = await actionsOf('admin');
    expect(await within(row).findByRole('alert')).toHaveTextContent(actions.errors.changeRole);
  });
});
