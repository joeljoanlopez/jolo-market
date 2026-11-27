import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { formatPrice } from '@/features/catalog/domain/format';
import { aProduct } from '@/features/catalog/ui/components/testProduct';
import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { FakeReceiptsApi } from '@/test/FakeReceiptsApi';
import { locationOf, renderApp } from '@/test/renderApp';

import type { Receipt, ReceiptLine } from '../../domain/receipt';

const copy = es.receipts;

function asShown(text: string | null): string {
  return (text ?? '').replace(/\s/gu, ' ');
}

const milkLine: ReceiptLine = {
  id: 'line-milk',
  kind: 'product',
  text: 'LECHE ENT.',
  quantity: { unit: 'units', value: 6 },
  amountCents: 534,
  linkedProduct: null,
  suggestedProduct: {
    id: 'product-milk',
    name: 'Leche entera',
    brand: 'Hacendado',
    available: true,
  },
};

function setUp(receipt: Partial<Receipt> = {}) {
  const receipts = new FakeReceiptsApi();
  const seeded = receipts.seed({
    date: '2026-11-14',
    totalCents: 534,
    lines: [milkLine],
    ...receipt,
  });
  const catalog = new FakeProductCatalog();
  catalog.respond = (params) =>
    Promise.resolve({
      items: [
        aProduct({
          id: 'product-bread',
          name: 'Pan de molde',
          supermarket: { id: 'mercadona', name: 'Mercadona' },
        }),
      ],
      page: 1,
      perPage: 20,
      total: params.supermarket === 'mercadona' ? 1 : 0,
      cheapestUnitPriceCents: {},
      approximate: false,
    });
  return { receipts, catalog, receiptId: seeded.id };
}

function renderReview(
  { receipts, catalog, receiptId }: ReturnType<typeof setUp>,
  listId = 'list-1',
) {
  return renderApp(`/lists/${listId}/receipts/${receiptId}`, {
    auth: FakeAuthGateway.loggedInAs(maria),
    receipts,
    catalog,
  });
}

async function lineRow(text: string) {
  const lines = await screen.findByRole('list', { name: copy.review.lines });
  const row = within(lines)
    .getAllByRole('listitem')
    .find((item) => item.textContent.includes(text));
  if (row === undefined) {
    throw new Error(`No line "${text}".`);
  }
  return row;
}

describe('ReceiptPageContainer', () => {
  it('shows the photo, the date, the total and the lines with their product', async () => {
    const context = setUp();
    renderReview(context);

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: copy.review.title.replace('{{supermarket}}', 'Mercadona'),
      }),
    ).toBeInTheDocument();
    expect(screen.getByRole('img', { name: copy.review.photo })).toHaveAttribute(
      'src',
      `/api/lists/list-1/receipts/${context.receiptId}/photo`,
    );
    expect(screen.getByLabelText(copy.review.date)).toHaveValue('2026-11-14');
    expect(screen.getByLabelText(copy.review.total)).toHaveValue('5,34');
    const row = await lineRow('LECHE ENT.');
    expect(asShown(row.textContent)).toContain('6 ud');
    expect(asShown(row.textContent)).toContain(asShown(formatPrice(534)));
    expect(row).toHaveTextContent(copy.line.suggested);
    expect(row).toHaveTextContent('Leche entera');
  });

  it('edits the date and the total', async () => {
    const context = setUp({ date: null, totalCents: null });
    renderReview(context);

    await userEvent.type(await screen.findByLabelText(copy.review.date), '2026-11-13');
    await userEvent.type(screen.getByLabelText(copy.review.total), '12,50');
    await userEvent.click(screen.getByRole('button', { name: copy.review.save }));

    await waitFor(() => {
      expect(context.receipts.calls).toContain('update {"date":"2026-11-13","totalCents":1250}');
    });
  });

  it('accepts a suggestion, links another product through the search of its supermarket, and leaves a line unlinked (PD-67)', async () => {
    const context = setUp();
    renderReview(context);

    await userEvent.click(
      within(await lineRow('LECHE ENT.')).getByRole('button', { name: copy.line.accept }),
    );
    await waitFor(() => {
      expect(context.receipts.calls).toContain('link line-milk product-milk');
    });
    expect(await lineRow('LECHE ENT.')).toHaveTextContent(copy.line.linked);

    await userEvent.click(
      within(await lineRow('LECHE ENT.')).getByRole('button', { name: copy.line.change }),
    );
    await userEvent.type(
      screen.getByLabelText(copy.line.search.replace('{{supermarket}}', 'Mercadona')),
      'pan molde',
    );
    await userEvent.click(
      await screen.findByRole('button', {
        name: copy.line.pick.replace('{{name}}', 'Pan de molde'),
      }),
    );
    await waitFor(() => {
      expect(context.receipts.calls).toContain('link line-milk product-bread');
    });
    expect(context.catalog.searches.at(-1)).toMatchObject({ supermarket: 'mercadona' });

    await userEvent.click(
      within(await lineRow('LECHE ENT.')).getByRole('button', { name: copy.line.leaveUnlinked }),
    );
    await waitFor(() => {
      expect(context.receipts.calls).toContain('unlink line-milk');
    });
  });

  it('adds a product line in kg and a discount line, edits and removes lines', async () => {
    const context = setUp();
    renderReview(context);

    const add = await screen.findByRole('form', { name: copy.line.add });
    await userEvent.type(within(add).getByLabelText(copy.line.text), 'PLATANO');
    await userEvent.selectOptions(within(add).getByLabelText(copy.line.unit), 'kg');
    await userEvent.type(within(add).getByLabelText(copy.line.quantity), '0,532');
    await userEvent.type(within(add).getByLabelText(copy.line.amount), '1,20');
    await userEvent.click(within(add).getByRole('button', { name: copy.line.add }));
    expect(asShown((await lineRow('PLATANO')).textContent)).toContain('0,532 kg');

    await userEvent.selectOptions(within(add).getByLabelText(copy.line.kind), 'discount');
    expect(within(add).queryByLabelText(copy.line.quantity)).not.toBeInTheDocument();
    await userEvent.type(within(add).getByLabelText(copy.line.text), 'DTO');
    await userEvent.type(within(add).getByLabelText(copy.line.amount), '0,50');
    await userEvent.click(within(add).getByRole('button', { name: copy.line.add }));
    expect(await lineRow('DTO')).toHaveTextContent(copy.line.discount);

    await userEvent.click(
      within(await lineRow('PLATANO')).getByRole('button', { name: copy.line.edit }),
    );
    const edit = screen.getByRole('form', { name: copy.line.edit });
    await userEvent.clear(within(edit).getByLabelText(copy.line.text));
    await userEvent.type(within(edit).getByLabelText(copy.line.text), 'PLATANO CANARIAS');
    await userEvent.click(within(edit).getByRole('button', { name: copy.line.save }));
    await waitFor(() => {
      expect(
        context.receipts.calls.some((call) => call.includes('"text":"PLATANO CANARIAS"')),
      ).toBe(true);
    });

    await userEvent.click(
      within(await lineRow('DTO')).getByRole('button', { name: copy.line.remove }),
    );
    await waitFor(() => {
      expect(screen.queryByText('DTO')).not.toBeInTheDocument();
    });
  });

  it('refuses an amount or a quantity it cannot read', async () => {
    const context = setUp();
    renderReview(context);

    const add = await screen.findByRole('form', { name: copy.line.add });
    await userEvent.type(within(add).getByLabelText(copy.line.text), 'PAN');
    await userEvent.type(within(add).getByLabelText(copy.line.quantity), '1,5');
    await userEvent.type(within(add).getByLabelText(copy.line.amount), '1');
    await userEvent.click(within(add).getByRole('button', { name: copy.line.add }));

    expect(within(add).getByText(copy.review.errors.quantity)).toBeInTheDocument();
    expect(context.receipts.calls.filter((call) => call.startsWith('add'))).toEqual([]);
  });

  it('warns about a difference but still confirms (PD-66)', async () => {
    const context = setUp({ totalCents: 600 });
    renderReview(context);

    expect(
      await screen.findByText(
        asShown(copy.review.difference.replace('{{amount}}', formatPrice(66))),
        { normalizer: asShown },
      ),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: copy.review.confirm }));

    await waitFor(() => {
      expect(screen.getByText(copy.review.confirmedStatus)).toBeInTheDocument();
    });
    expect(screen.getByRole('button', { name: copy.review.confirmAgain })).toBeInTheDocument();
  });

  it('says why a receipt without a date cannot be confirmed', async () => {
    const context = setUp({ date: null });
    renderReview(context);

    await userEvent.click(await screen.findByRole('button', { name: copy.review.confirm }));

    expect(await screen.findByText(copy.review.errors.invalid)).toBeInTheDocument();
  });

  it('deletes the receipt after asking, and goes back to the list (PD-42)', async () => {
    const context = setUp();
    const router = renderReview(context);

    await userEvent.click(await screen.findByRole('button', { name: copy.review.delete }));
    expect(screen.getByText(copy.review.confirmDelete)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: copy.review.confirmDeleteAction }));

    await waitFor(() => {
      expect(locationOf(router)).toBe('/lists/list-1');
    });
    expect(context.receipts.receipts.has(context.receiptId)).toBe(false);
  });

  it('shows a receipt still being read without its editor', async () => {
    const context = setUp({ status: 'reading', date: null, totalCents: null, lines: [] });
    renderReview(context);

    expect(await screen.findByText(copy.review.reading)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: copy.review.confirm })).not.toBeInTheDocument();
  });

  it('shows the not-found page to someone who is not a member', async () => {
    const context = setUp();
    renderReview(context, 'list-9');

    expect(await screen.findByRole('heading', { name: es.notFound.title })).toBeInTheDocument();
  });
});
