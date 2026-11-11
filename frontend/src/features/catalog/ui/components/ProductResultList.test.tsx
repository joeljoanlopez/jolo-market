import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { ProductResultList } from './ProductResultList';
import { aProduct } from './testProduct';

const products = [
  aProduct({ id: 'a', name: 'Leche A', unitPrice: { cents: 70, unit: 'l' } }),
  aProduct({ id: 'b', name: 'Leche B', unitPrice: { cents: 83, unit: 'l' } }),
  aProduct({ id: 'c', name: 'Leche en polvo', unitPrice: { cents: 900, unit: 'kg' } }),
  aProduct({ id: 'd', name: 'Leche sin precio', unitPrice: null }),
];

function highlightedNames(): string[] {
  return screen
    .getAllByRole('listitem')
    .filter((item) => within(item).queryByText(es.catalog.product.cheapest) !== null)
    .map((item) => within(item).getByRole('heading').textContent);
}

describe('ProductResultList', () => {
  it('lists the products in the given order', () => {
    renderWithProviders(
      <ProductResultList products={products} cheapestUnitPriceCents={{ l: 70, kg: 900 }} />,
    );

    expect(
      within(screen.getByRole('list', { name: es.catalog.search.results }))
        .getAllByRole('heading')
        .map((heading) => heading.textContent),
    ).toEqual(['Leche A', 'Leche B', 'Leche en polvo', 'Leche sin precio']);
  });

  it('highlights the cheapest unit price of each unit group', () => {
    renderWithProviders(
      <ProductResultList products={products} cheapestUnitPriceCents={{ l: 70, kg: 900 }} />,
    );

    expect(highlightedNames()).toEqual(['Leche A', 'Leche en polvo']);
  });

  it('highlights nothing when the cheapest of the query is on another page', () => {
    renderWithProviders(
      <ProductResultList products={products} cheapestUnitPriceCents={{ l: 50, kg: 400 }} />,
    );

    expect(highlightedNames()).toEqual([]);
  });
  it('shows the actions other features give for each product', () => {
    renderWithProviders(
      <ProductResultList
        products={products}
        cheapestUnitPriceCents={{}}
        renderActions={(product) => <button type="button">{`Acción ${product.name}`}</button>}
      />,
    );

    expect(screen.getByRole('button', { name: 'Acción Leche A' })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /^Acción / })).toHaveLength(products.length);
  });
});
