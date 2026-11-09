import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { SupermarketFilter } from './SupermarketFilter';

const supermarkets = [
  { id: 'mercadona', name: 'Mercadona' },
  { id: 'aldi', name: 'Aldi' },
];

describe('SupermarketFilter', () => {
  it('offers every supermarket after "all"', () => {
    renderWithProviders(
      <SupermarketFilter supermarkets={supermarkets} value={undefined} onChange={vi.fn()} />,
    );

    const select = screen.getByRole('combobox', { name: es.catalog.search.supermarket });
    expect(
      within(select)
        .getAllByRole('option')
        .map((option) => option.textContent),
    ).toEqual([es.catalog.search.allSupermarkets, 'Mercadona', 'Aldi']);
    expect(select).toHaveValue('');
  });

  it('emits the chosen slug, and undefined for "all"', async () => {
    const onChange = vi.fn();
    renderWithProviders(
      <SupermarketFilter supermarkets={supermarkets} value="mercadona" onChange={onChange} />,
    );

    await userEvent.selectOptions(screen.getByRole('combobox'), 'Aldi');
    await userEvent.selectOptions(screen.getByRole('combobox'), es.catalog.search.allSupermarkets);

    expect(onChange.mock.calls).toEqual([['aldi'], [undefined]]);
  });
});
