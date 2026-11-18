import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { formatPrice } from '@/features/catalog/domain/format';
import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { SupermarketFilter } from './SupermarketFilter';

const copy = es.lists.filter;

const chips = [
  { supermarketId: 'bonpreu', supermarketName: 'Bonpreu', totalCents: 300 },
  { supermarketId: 'mercadona', supermarketName: 'Mercadona', totalCents: 125 },
];

/** Intl writes a non-breaking space before "€"; compare with plain spaces. */
function asShown(text: string): string {
  return text.replace(/\s/gu, ' ');
}

describe('SupermarketFilter', () => {
  it('offers "Todos" first, then each supermarket with its total, and marks "Todos" by default (PD-84)', () => {
    renderWithProviders(<SupermarketFilter chips={chips} selected={null} onSelect={vi.fn()} />);

    const group = screen.getByRole('group', { name: copy.label });
    const buttons = Array.from(group.querySelectorAll('button'));
    expect(buttons.map((button) => asShown(button.textContent))).toEqual([
      copy.all,
      `Bonpreu${asShown(formatPrice(300))}`,
      `Mercadona${asShown(formatPrice(125))}`,
    ]);
    expect(screen.getByRole('button', { name: copy.all })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: /Bonpreu/u })).toHaveAttribute(
      'aria-pressed',
      'false',
    );
  });

  it('marks the chosen supermarket', () => {
    renderWithProviders(
      <SupermarketFilter chips={chips} selected="mercadona" onSelect={vi.fn()} />,
    );

    expect(screen.getByRole('button', { name: /Mercadona/u })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(screen.getByRole('button', { name: copy.all })).toHaveAttribute('aria-pressed', 'false');
  });

  it('emits the supermarket chosen, and null for "Todos"', async () => {
    const onSelect = vi.fn();
    renderWithProviders(<SupermarketFilter chips={chips} selected="bonpreu" onSelect={onSelect} />);

    await userEvent.click(screen.getByRole('button', { name: /Mercadona/u }));
    await userEvent.click(screen.getByRole('button', { name: copy.all }));

    expect(onSelect.mock.calls).toEqual([['mercadona'], [null]]);
  });
});
