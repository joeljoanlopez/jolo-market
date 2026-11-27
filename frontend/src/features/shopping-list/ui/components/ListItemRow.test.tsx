import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders as renderWithoutRouter } from '@/test/render';

import type { SectionId } from '../../domain/sections';
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
  section: 'other',
};

const UNKNOWN: ListProduct = {
  id: 'gone',
  name: null,
  brand: null,
  supermarketId: null,
  priceCents: null,
  imageUrl: null,
  available: false,
  section: 'other',
};

function aProductItem(product: Partial<ListProduct> = {}, checked = false): ProductItem {
  return {
    id: 'item-1',
    position: 0,
    quantity: 1,
    checked,
    type: 'product',
    section: 'other',
    sectionOverride: null,
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
  section: 'other',
  sectionOverride: null,
};

/** The product name and image link to the product page, so the row needs a router. */
function renderRow(item: ListItem, onSectionChange: (section: SectionId | null) => void = vi.fn()) {
  const ui: ReactElement = (
    <MemoryRouter>
      <ul>
        <ListItemRow
          item={item}
          supermarketName="Mercadona"
          onCheckedChange={() => undefined}
          onRemove={() => undefined}
          onSectionChange={onSectionChange}
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

  describe('section selector (PD-92, PD-116)', () => {
    const sectionNames = Object.values(es.lists.sections);
    const selector = (name: string) =>
      screen.getByRole('combobox', { name: es.lists.item.section.replace('{{name}}', name) });
    const optionNames = (name: string) =>
      within(selector(name))
        .getAllByRole('option')
        .map((option) => option.textContent);

    it('offers the thirteen sections for a free-text item, with its current one chosen', () => {
      renderRow({ ...TEXT_ITEM, section: 'bakery', sectionOverride: 'bakery' });

      expect(optionNames('pan')).toEqual(sectionNames);
      expect(selector('pan')).toHaveValue('bakery');
      expect(selector('pan')).toHaveClass('h-8');
    });

    it('shows "Otros" for a free-text item without a choice', () => {
      renderRow(TEXT_ITEM);

      expect(selector('pan')).toHaveDisplayValue(es.lists.sections.other);
    });

    it('offers "Automática (<catalog section>)" first for a product item, chosen while it follows the catalog', () => {
      renderRow(aProductItem({ section: 'dairy-and-eggs' }));

      const automatic = es.lists.item.automaticSection.replace(
        '{{section}}',
        es.lists.sections['dairy-and-eggs'],
      );
      expect(optionNames('Leche entera')).toEqual([automatic, ...sectionNames]);
      expect(selector('Leche entera')).toHaveDisplayValue(automatic);
    });

    it('shows the section chosen by hand on a product item', () => {
      renderRow({ ...aProductItem(), section: 'drinks', sectionOverride: 'drinks' });

      expect(selector('Leche entera')).toHaveDisplayValue(es.lists.sections.drinks);
    });

    it('emits the chosen section, and null for "Automática"', async () => {
      const onSectionChange = vi.fn();
      renderRow(
        { ...aProductItem(), section: 'drinks', sectionOverride: 'drinks' },
        onSectionChange,
      );

      await userEvent.selectOptions(selector('Leche entera'), 'bakery');
      await userEvent.selectOptions(selector('Leche entera'), '');

      expect(onSectionChange.mock.calls).toEqual([['bakery'], [null]]);
    });

    it('emits null when a free-text item goes back to "Otros", its default', async () => {
      const onSectionChange = vi.fn();
      renderRow({ ...TEXT_ITEM, section: 'bakery', sectionOverride: 'bakery' }, onSectionChange);

      await userEvent.selectOptions(selector('pan'), 'other');

      expect(onSectionChange).toHaveBeenCalledWith(null);
    });

    it('is not shown on a checked item', () => {
      renderRow({ ...TEXT_ITEM, checked: true });

      expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });
  });
});
