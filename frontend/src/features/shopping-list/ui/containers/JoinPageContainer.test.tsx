import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FAKE_CODE, FAKE_PASSWORD, FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeShoppingLists, JOAN } from '@/test/FakeShoppingLists';
import { locationOf, renderApp } from '@/test/renderApp';

const copy = es.lists.join;

describe('JoinPageContainer', () => {
  it('joins the list and opens it', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ name: 'Cena', owner: JOAN });
    const router = renderApp(`/join/${stored.shareToken}`, {
      auth: FakeAuthGateway.loggedInAs(maria),
      lists,
    });

    expect(await screen.findByRole('heading', { level: 1, name: 'Cena' })).toBeVisible();
    expect(locationOf(router)).toBe(`/lists/${stored.id}`);
    expect(stored.members).toEqual([lists.currentUser]);
  });

  it('says the link does not work when the token is unknown or revoked', async () => {
    renderApp('/join/revoked-token', { auth: FakeAuthGateway.loggedInAs(maria) });

    expect(await screen.findByRole('heading', { level: 1, name: copy.invalidTitle })).toBeVisible();
    expect(screen.getByText(copy.invalid)).toBeVisible();
    expect(screen.getByRole('link', { name: copy.backHome })).toHaveAttribute('href', '/');
  });

  it('goes through login first when there is no session, then joins', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ name: 'Cena', owner: JOAN });
    const router = renderApp(`/join/${stored.shareToken}`, { auth: new FakeAuthGateway(), lists });
    const user = userEvent.setup();

    await waitFor(() => {
      expect(locationOf(router)).toBe(
        `/login?${new URLSearchParams({ next: `/join/${stored.shareToken}` }).toString()}`,
      );
    });
    expect(stored.members).toEqual([]);
    await user.type(await screen.findByLabelText(es.auth.login.username), 'maria');
    await user.type(screen.getByLabelText(es.auth.login.password), FAKE_PASSWORD);
    await user.click(screen.getByRole('button', { name: es.auth.login.continue }));
    await user.type(await screen.findByLabelText(es.auth.login.code), FAKE_CODE);
    await user.click(screen.getByRole('button', { name: es.auth.login.submit }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Cena' })).toBeVisible();
    expect(locationOf(router)).toBe(`/lists/${stored.id}`);
  });
});
