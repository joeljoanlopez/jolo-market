import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { formatPrice } from '@/features/catalog/domain/format';
import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { FakeReceiptsApi } from '@/test/FakeReceiptsApi';
import { FakeShoppingLists } from '@/test/FakeShoppingLists';
import { renderApp } from '@/test/renderApp';

import { RECEIPTS_REFRESH_INTERVAL_MS } from '../../application/useListReceipts';

const copy = es.receipts;

function asShown(text: string | null): string {
  return (text ?? '').replace(/\s/gu, ' ');
}

function setUp() {
  const lists = new FakeShoppingLists();
  const list = lists.seed();
  const receipts = new FakeReceiptsApi();
  receipts.lists.clear();
  receipts.lists.add(list.id);
  const catalog = new FakeProductCatalog();
  catalog.supermarkets = [
    { id: 'mercadona', name: 'Mercadona' },
    { id: 'aldi', name: 'Aldi' },
  ];
  return { lists, list, receipts, catalog };
}

function renderList({ lists, list, receipts, catalog }: ReturnType<typeof setUp>) {
  return renderApp(`/lists/${list.id}`, {
    auth: FakeAuthGateway.loggedInAs(maria),
    lists,
    catalog,
    receipts,
  });
}

describe('ListReceiptsContainer', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows the confirmed receipts and the spending of the list', async () => {
    const context = setUp();
    context.receipts.seed({
      listId: context.list.id,
      status: 'confirmed',
      date: '2026-11-14',
      totalCents: 2599,
    });

    renderList(context);

    const confirmed = await screen.findByRole('list', { name: copy.confirmed.title });
    expect(asShown(confirmed.textContent)).toContain(asShown(formatPrice(2599)));
    expect(
      await screen.findByRole('heading', { level: 4, name: /noviembre 2026/u }),
    ).toBeInTheDocument();
  });

  it('uploads a photo, shows it being read and then as a draft (PD-65)', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const context = setUp();
    renderList(context);

    await screen.findByRole('option', { name: 'Aldi' });
    await userEvent.selectOptions(screen.getByLabelText(copy.upload.supermarket), 'aldi');
    await userEvent.upload(
      screen.getByLabelText(copy.upload.photo),
      new File(['x'], 'ticket.jpg', { type: 'image/jpeg' }),
    );
    await userEvent.click(screen.getByRole('button', { name: copy.upload.submit }));

    const pending = await screen.findByRole('list', { name: copy.pending.title });
    await waitFor(() => {
      expect(within(pending).getByText(copy.pending.reading)).toBeInTheDocument();
    });
    expect(context.receipts.uploads).toEqual([
      { listId: context.list.id, supermarketId: 'aldi', fileName: 'ticket.jpg' },
    ]);

    const [receipt] = context.receipts.receipts.values();
    context.receipts.finishReading(receipt?.id ?? '');
    await act(() => vi.advanceTimersByTimeAsync(RECEIPTS_REFRESH_INTERVAL_MS));

    await waitFor(() => {
      expect(
        within(screen.getByRole('list', { name: copy.pending.title })).getByText(
          copy.pending.draft,
        ),
      ).toBeInTheDocument();
    });
  });

  it('shows the failure of an upload', async () => {
    const context = setUp();
    context.receipts.changeError = new Error('offline');
    renderList(context);

    await screen.findByRole('option', { name: 'Aldi' });
    await userEvent.selectOptions(screen.getByLabelText(copy.upload.supermarket), 'aldi');
    await userEvent.upload(
      screen.getByLabelText(copy.upload.photo),
      new File(['x'], 'ticket.jpg', { type: 'image/jpeg' }),
    );
    await userEvent.click(screen.getByRole('button', { name: copy.upload.submit }));

    expect(await screen.findByText(copy.upload.errors.failed)).toBeInTheDocument();
  });

  it('offers to retry when the receipts cannot be loaded', async () => {
    const context = setUp();
    const failing = vi.spyOn(context.receipts, 'listReceipts').mockRejectedValue(new Error('down'));
    renderList(context);

    const retry = await screen.findByRole('button', { name: copy.section.retry });
    failing.mockRestore();
    await userEvent.click(retry);

    expect(await screen.findByText(copy.confirmed.empty)).toBeInTheDocument();
  });
});
