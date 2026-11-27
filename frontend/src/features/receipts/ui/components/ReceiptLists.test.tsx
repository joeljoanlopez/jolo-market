import { screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import { formatPrice } from '@/features/catalog/domain/format';
import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import type { MonthlySpending as Month, ReceiptSummary } from '../../domain/receipt';
import { ConfirmedReceiptsList } from './ConfirmedReceiptsList';
import { MonthlySpending } from './MonthlySpending';
import { PendingReceiptsList } from './PendingReceiptsList';

const copy = es.receipts;

function asShown(text: string | null): string {
  return (text ?? '').replace(/\s/gu, ' ');
}

function summary(overrides: Partial<ReceiptSummary>): ReceiptSummary {
  return {
    id: 'r1',
    status: 'confirmed',
    supermarketId: 'mercadona',
    supermarketName: 'Mercadona',
    date: '2026-11-14',
    totalCents: 2599,
    uploader: { id: 'u1', username: 'maria' },
    uploadedAt: new Date('2026-11-14T18:00:00Z'),
    ...overrides,
  };
}

const href = (id: string) => `/lists/l1/receipts/${id}`;

describe('PendingReceiptsList', () => {
  it('shows the receipts being read and the drafts, each linking to its review', () => {
    renderWithProviders(
      <MemoryRouter>
        <PendingReceiptsList
          receipts={[
            summary({ id: 'r2', status: 'reading', date: null, totalCents: null }),
            summary({
              status: 'draft',
              supermarketName: 'Aldi',
              uploader: { id: 'u2', username: 'joan' },
            }),
          ]}
          reviewHref={href}
        />
      </MemoryRouter>,
    );

    const list = screen.getByRole('list', { name: copy.pending.title });
    const items = within(list).getAllByRole('listitem');
    expect(items[0]).toHaveTextContent(copy.pending.reading);
    expect(items[1]).toHaveTextContent(copy.pending.draft);
    expect(
      screen.getByRole('link', {
        name: copy.pending.review
          .replace('{{supermarket}}', 'Aldi')
          .replace('{{username}}', 'joan'),
      }),
    ).toHaveAttribute('href', '/lists/l1/receipts/r1');
  });

  it('shows nothing without pending receipts', () => {
    const { container } = renderWithProviders(
      <MemoryRouter>
        <PendingReceiptsList receipts={[]} reviewHref={href} />
      </MemoryRouter>,
    );

    expect(container).toBeEmptyDOMElement();
  });
});

describe('ConfirmedReceiptsList', () => {
  it('shows date, supermarket, total and uploader of each confirmed receipt', () => {
    renderWithProviders(
      <MemoryRouter>
        <ConfirmedReceiptsList receipts={[summary({})]} reviewHref={href} />
      </MemoryRouter>,
    );

    const item = within(screen.getByRole('list', { name: copy.confirmed.title })).getByRole(
      'listitem',
    );
    expect(asShown(item.textContent)).toContain('14/11/2026');
    expect(asShown(item.textContent)).toContain('Mercadona');
    expect(asShown(item.textContent)).toContain(asShown(formatPrice(2599)));
    expect(item).toHaveTextContent(copy.confirmed.uploadedBy.replace('{{username}}', 'maria'));
    expect(within(item).getByRole('link')).toHaveAttribute('href', '/lists/l1/receipts/r1');
  });

  it('says when there are no confirmed receipts', () => {
    renderWithProviders(
      <MemoryRouter>
        <ConfirmedReceiptsList receipts={[]} reviewHref={href} />
      </MemoryRouter>,
    );

    expect(screen.getByText(copy.confirmed.empty)).toBeInTheDocument();
  });
});

describe('MonthlySpending', () => {
  const months: Month[] = [
    {
      month: '2026-11',
      totalCents: 3000,
      supermarkets: [
        { supermarketId: 'aldi', supermarketName: 'Aldi', totalCents: 1000 },
        { supermarketId: 'mercadona', supermarketName: 'Mercadona', totalCents: 2000 },
      ],
    },
    {
      month: '2026-10',
      totalCents: 500,
      supermarkets: [{ supermarketId: 'aldi', supermarketName: 'Aldi', totalCents: 500 }],
    },
  ];

  it('shows each month, newest first, with its total and the breakdown per supermarket (PD-70)', () => {
    renderWithProviders(<MonthlySpending months={months} />);

    const november = screen.getByRole('list', {
      name: copy.spending.month.replace('{{month}}', 'noviembre 2026'),
    });
    expect(asShown(within(november).getAllByRole('listitem')[0]?.textContent ?? '')).toBe(
      `Aldi${asShown(formatPrice(1000))}`,
    );
    expect(
      screen.getAllByRole('heading', { level: 4 }).map((heading) => asShown(heading.textContent)),
    ).toEqual([
      `noviembre 2026${asShown(formatPrice(3000))}`,
      `octubre 2026${asShown(formatPrice(500))}`,
    ]);
  });

  it('says spending appears once receipts are confirmed', () => {
    renderWithProviders(<MonthlySpending months={[]} />);

    expect(screen.getByText(copy.spending.empty)).toBeInTheDocument();
  });
});
