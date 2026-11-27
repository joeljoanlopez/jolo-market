import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it, vi } from 'vitest';

import { formatPrice } from '@/features/catalog/domain/format';
import { aProduct, asShown } from '@/features/catalog/ui/components/testProduct';
import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { suggestionOptionId, suggestionOptions } from '../../domain/suggestions';
import { ProductSuggestions } from './ProductSuggestions';

const copy = es.lists.suggestions;
const milk = aProduct({ id: 'milk', name: 'Leche entera', brand: 'Hacendado', priceCents: 125 });
const eggs = aProduct({
  id: 'eggs',
  name: 'Huevos',
  brand: null,
  supermarket: { id: 'bonpreu', name: 'Bonpreu' },
});

type Props = ComponentProps<typeof ProductSuggestions>;

function renderSuggestions(overrides: Partial<Props> = {}) {
  const props: Props = {
    id: 'suggestions',
    text: 'leche',
    search: { status: 'success', options: suggestionOptions([milk, eggs]) },
    highlighted: null,
    seeAllHref: '/search?q=leche&list=l1',
    onPickText: vi.fn(),
    onPickProduct: vi.fn(),
    onRetry: vi.fn(),
    ...overrides,
  };
  renderWithProviders(
    <MemoryRouter>
      <ProductSuggestions {...props} />
    </MemoryRouter>,
  );
  return props;
}

function options() {
  return within(screen.getByRole('listbox')).getAllByRole('option');
}

/** The image of an option, hidden from assistive technology (the option names the product). */
function imageIn(index: number, name: string | RegExp = /.*/u) {
  return within(screen.getAllByRole('option')[index] ?? document.body).queryByRole('img', {
    name,
    hidden: true,
  });
}

describe('ProductSuggestions', () => {
  it('offers the text first, then each product with its brand, supermarket and prices (PD-80)', () => {
    renderSuggestions();

    const [text, first, second] = options();
    expect(text).toHaveTextContent('Añadir «leche» como texto');
    expect(first).toHaveTextContent('Leche entera');
    expect(first).toHaveTextContent('Hacendado');
    expect(first).toHaveTextContent('Mercadona');
    expect(asShown(first?.textContent ?? '')).toContain(asShown(formatPrice(125)));
    expect(asShown(first?.textContent ?? '')).toContain('0,83');
    expect(second).toHaveTextContent('Bonpreu');
  });

  it('links to every result of the search (PD-82)', () => {
    renderSuggestions();

    expect(screen.getByRole('link', { name: copy.seeAll })).toHaveAttribute(
      'href',
      '/search?q=leche&list=l1',
    );
  });

  it('marks the highlighted option as selected, with a stable id', () => {
    renderSuggestions({ highlighted: 1 });

    const highlighted = options()[1];
    expect(highlighted).toHaveAttribute('aria-selected', 'true');
    expect(highlighted).toHaveAttribute('id', suggestionOptionId('suggestions', 1));
    expect(options()[0]).toHaveAttribute('aria-selected', 'false');
  });

  it('emits the picked product or the text', async () => {
    const props = renderSuggestions();
    const user = userEvent.setup();

    await user.click(options()[2] as HTMLElement);
    await user.click(options()[0] as HTMLElement);

    expect(props.onPickProduct).toHaveBeenCalledWith(eggs);
    expect(props.onPickText).toHaveBeenCalledOnce();
  });

  it('says it is searching while the products load', () => {
    renderSuggestions({ search: { status: 'loading', options: suggestionOptions([]) } });

    expect(screen.getByRole('status')).toHaveTextContent(copy.loading);
    expect(options()).toHaveLength(1);
  });

  it('says there are no products and still offers the text (PD-80)', () => {
    renderSuggestions({ search: { status: 'success', options: suggestionOptions([]) } });

    expect(screen.getByText('No hay productos para «leche».')).toBeVisible();
    expect(options()[0]).toHaveTextContent('Añadir «leche» como texto');
  });

  it('shows the error with a retry and still offers the text (PD-80)', async () => {
    const props = renderSuggestions({
      search: { status: 'error', options: suggestionOptions([]) },
    });
    const user = userEvent.setup();

    expect(screen.getByRole('alert')).toHaveTextContent(copy.error);
    await user.click(screen.getByRole('button', { name: copy.retry }));

    expect(props.onRetry).toHaveBeenCalledOnce();
    expect(options()[0]).toHaveTextContent('Añadir «leche» como texto');
  });

  it('shows each product with its image at 48 px, or the placeholder, and the text without one (PD-97)', () => {
    renderSuggestions({
      search: {
        status: 'success',
        options: suggestionOptions([milk, { ...eggs, imageUrl: null }]),
      },
    });

    expect(imageIn(1)).toHaveAttribute('src', 'https://img.example/leche.jpg');
    expect(imageIn(1)).toHaveClass('size-12');
    expect(imageIn(2, es.catalog.product.noImage)).toHaveClass('size-12');
    expect(imageIn(0)).not.toBeInTheDocument();
  });

  it('picks the product when its image is clicked, which is not a link (PD-97)', async () => {
    const props = renderSuggestions();

    const image = imageIn(1);
    expect(image?.closest('a')).toBeNull();
    await userEvent.click(image ?? document.body);

    expect(props.onPickProduct).toHaveBeenCalledWith(milk);
  });
});
