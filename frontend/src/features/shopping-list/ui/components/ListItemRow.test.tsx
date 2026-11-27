import { screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders as renderWithoutRouter } from '@/test/render';

import type { ListItem, ListProduct, ProductItem } from '../../domain/shoppingList';
import { ListItemRow } from './ListItemRow';

const MILK: ListProduct = {
  id: 'product-milk',
  name: 'Leche entera',
  brand: 'Hacendado',
  supermarketId: 'mercadona',
  priceCents: 125,
  imageUrl: 'https://img.example/leche.jpg',
  available: true,
};

const UNKNOWN: ListProduct = {
  id: 'gone',
  name: null,
  brand: null,
  supermarketId: null,
  priceCents: null,
  imageUrl: null,
  available: false,
};

function aProductItem(product: Partial<ListProduct> = {}, checked = false): ProductItem {
  return {
    id: 'item-1',
    position: 0,
    quantity: 1,
    checked,
    type: 'product',
    product: { ...MILK, ...product },
  };
}

const TEXT_ITEM: ListItem = {
  id: 'item-2',
  position: 0,
  quantity: 1,
  checked: false,
  type: 'text',
  text: 'pan',
};

/** The product name and image link to the product page, so the row needs a router. */
function renderRow(item: ListItem) {
  const ui: ReactElement = (
    <MemoryRouter>
      <ul>
        <ListItemRow
          item={item}
          supermarketName="Mercadona"
          onCheckedChange={() => undefined}
          onRemove={() => undefined}
        />
      </ul>
    </MemoryRouter>
  );
  return renderWithoutRouter(ui);
}

/** The image is hidden from assistive technology (the name link already serves it, PD-97). */
function image(name: string) {
  return screen.getByRole('img', { name, hidden: true });
}

describe('ListItemRow', () => {
  it('shows the image of a product item (PD-97)', () => {
    renderRow(aProductItem());

    expect(image('Foto de Leche entera')).toHaveAttribute('src', 'https://img.example/leche.jpg');
    expect(image('Foto de Leche entera')).toHaveClass('size-12');
  });

  it('shows the placeholder for a product without an image (PD-97)', () => {
    renderRow(aProductItem({ imageUrl: null }));

    expect(image(es.catalog.product.noImage)).toHaveClass('size-12');
  });

  it('links the image to the product page, out of the tab order and hidden from assistive technology (PD-48, PD-97)', () => {
    renderRow(aProductItem());

    const imageLink = image('Foto de Leche entera').closest('a');
    expect(imageLink).toHaveAttribute('href', '/product/product-milk');
    expect(imageLink).toHaveAttribute('tabindex', '-1');
    expect(imageLink).toHaveAttribute('aria-hidden', 'true');
    // Only the name link is announced.
    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(screen.getByRole('link', { name: 'Leche entera' })).toBeInTheDocument();
  });

  it('shows the placeholder without a link for a product the catalog no longer knows (PD-97)', () => {
    renderRow({ ...aProductItem(), product: UNKNOWN });

    expect(image(es.catalog.product.noImage).closest('a')).toBeNull();
    expect(screen.queryAllByRole('link', { hidden: true })).toHaveLength(0);
  });

  it('shows no image or placeholder for a free-text item (PD-97)', () => {
    renderRow(TEXT_ITEM);

    expect(screen.queryByRole('img', { hidden: true })).not.toBeInTheDocument();
  });

  it('fades the image of a checked item (PD-97)', () => {
    renderRow(aProductItem({}, true));

    expect(image('Foto de Leche entera')).toHaveClass('opacity-50');
  });

  it('keeps the image of an unavailable product unfaded, next to "No disponible" (PD-22, PD-97)', () => {
    renderRow(aProductItem({ available: false }));

    expect(image('Foto de Leche entera')).not.toHaveClass('opacity-50');
    expect(screen.getByText(es.lists.item.unavailable)).toBeInTheDocument();
  });
});
