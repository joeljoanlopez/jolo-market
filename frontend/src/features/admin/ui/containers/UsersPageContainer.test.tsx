import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { admin, FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { anAdminUser, FakeUserAdministration, LINK_EXPIRY } from '@/test/FakeUserAdministration';
import { renderApp } from '@/test/renderApp';

import { formatDateTime } from '../../domain/formatDateTime';

const copy = es.admin.users;

function renderAsAdmin(administration = new FakeUserAdministration()) {
  renderApp('/admin/users', { auth: FakeAuthGateway.loggedInAs(admin), administration });
  return administration;
}

function rowOf(username: string): HTMLElement {
  const row = within(screen.getByRole('list', { name: copy.list }))
    .getAllByRole('listitem')
    .find((item) => within(item).queryByRole('heading', { name: username }) !== null);
  if (row === undefined) {
    throw new Error(`No row for ${username}.`);
  }
  return row;
}

async function createUser(username: string, role: 'Usuario' | 'Administrador' = 'Usuario') {
  const user = userEvent.setup();
  await user.type(await screen.findByLabelText(copy.create.username), username);
  await user.selectOptions(screen.getByLabelText(copy.create.role), role);
  await user.click(screen.getByRole('button', { name: copy.create.submit }));
  return user;
}

describe('UsersPageContainer', () => {
  it('lists the users with their role and status, and the link expiry of pending ones', async () => {
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
    renderAsAdmin(administration);

    expect(await screen.findByRole('heading', { level: 1, name: copy.title })).toBeVisible();
    await screen.findByRole('list', { name: copy.list });
    expect(rowOf('admin')).toHaveTextContent(copy.roles.ADMIN);
    expect(rowOf('admin')).toHaveTextContent(copy.statuses.ACTIVE);
    expect(rowOf('luis')).toHaveTextContent(copy.statuses.DISABLED);
    expect(rowOf('pedro')).toHaveTextContent(copy.statuses.PENDING_ACTIVATION);
    expect(rowOf('pedro')).toHaveTextContent(
      `Enlace válido hasta el ${formatDateTime(LINK_EXPIRY)}`,
    );
  });

  it('creates a user and shows their activation link once, with a copy button', async () => {
    renderAsAdmin();

    const user = await createUser('pedro');

    const panel = await screen.findByRole('region', { name: 'Enlace de activación de pedro' });
    const link = `${window.location.origin}/activate/token-1-of-pedro`;
    expect(within(panel).getByLabelText(copy.link.label)).toHaveValue(link);
    expect(panel).toHaveTextContent(`Válido hasta el ${formatDateTime(LINK_EXPIRY)}.`);

    await user.click(within(panel).getByRole('button', { name: copy.link.copy }));
    await expect(navigator.clipboard.readText()).resolves.toBe(link);
    expect(within(panel).getByRole('status')).toHaveTextContent(copy.link.copied);

    // The new user is in the list, pending activation.
    await waitFor(() => {
      expect(rowOf('pedro')).toHaveTextContent(copy.statuses.PENDING_ACTIVATION);
    });
  });

  it('never shows the link again once the admin is done with it', async () => {
    renderAsAdmin();

    const user = await createUser('pedro');
    await user.click(await screen.findByRole('button', { name: copy.link.done }));

    expect(screen.queryByRole('region', { name: /Enlace de activación/ })).not.toBeInTheDocument();
    expect(screen.queryByText(/token-1-of-pedro/)).not.toBeInTheDocument();
    expect(screen.getByLabelText(copy.create.username)).toHaveValue('');
  });

  it('creates an admin when that role is chosen', async () => {
    const administration = renderAsAdmin();

    await createUser('ana', 'Administrador');

    await waitFor(() => {
      expect(administration.users.find((user) => user.username === 'ana')?.role).toBe('ADMIN');
    });
  });

  it('says when the username is taken', async () => {
    renderAsAdmin();

    await createUser('maria');

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.create.taken);
  });

  it('says when the username is not valid, and explains the format', async () => {
    renderAsAdmin();

    expect(await screen.findByLabelText(copy.create.username)).toHaveAccessibleDescription(
      copy.create.usernameHint,
    );
    await createUser('ab');

    expect(await screen.findByRole('alert')).toHaveTextContent(copy.create.invalid);
  });

  it('is the not-found page for a USER', async () => {
    renderApp('/admin/users', { auth: FakeAuthGateway.loggedInAs(maria) });

    expect(await screen.findByRole('heading', { level: 1, name: es.notFound.title })).toBeVisible();
  });
});

describe('admin navigation (PD-15)', () => {
  it('shows the users page link to an ADMIN', async () => {
    renderApp('/', { auth: FakeAuthGateway.loggedInAs(admin) });

    const header = screen.getByRole('banner');
    expect(await within(header).findByRole('link', { name: copy.nav })).toHaveAttribute(
      'href',
      '/admin/users',
    );
  });

  it('shows no admin entry point to a USER', async () => {
    renderApp('/', { auth: FakeAuthGateway.loggedInAs(maria) });

    const header = screen.getByRole('banner');
    expect(await within(header).findByText('maria')).toBeInTheDocument();
    expect(within(header).queryByRole('link', { name: copy.nav })).not.toBeInTheDocument();
  });
});
