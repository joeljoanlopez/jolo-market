import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { formatPrice } from '../../domain/format';
import { ProductCard } from './ProductCard';
import { aProduct, asShown } from './testProduct';

describe('ProductCard', () => {
  it('shows the product with its supermarket, packaging and prices', () => {
    renderWithProviders(<ProductCard product={aProduct()} />);

    expect(screen.getByRole('heading', { name: 'Leche entera' })).toBeInTheDocument();
    expect(screen.getByText('Hacendado')).toBeInTheDocument();
    expect(screen.getByText('Mercadona')).toBeInTheDocument();
    expect(screen.getByText('1,5 L')).toBeInTheDocument();
    expect(screen.getByText(asShown(formatPrice(125)))).toBeInTheDocument();
    expect(screen.getByText(asShown(`${formatPrice(83)}/L`))).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Foto de Leche entera' })).toHaveAttribute(
      'src',
      'https://img.example/leche.jpg',
    );
  });

  it('shows placeholders for a missing image, brand and packaging', () => {
    renderWithProviders(
      <ProductCard product={aProduct({ imageUrl: null, brand: null, packaging: null })} />,
    );

    expect(screen.getByRole('img', { name: es.catalog.product.noImage })).toBeInTheDocument();
    expect(screen.queryByText('Hacendado')).not.toBeInTheDocument();
    expect(screen.getByText(es.catalog.product.noPackaging)).toBeInTheDocument();
  });

  it('highlights the price when it is the cheapest', () => {
    renderWithProviders(<ProductCard product={aProduct()} highlighted />);

    expect(screen.getByText(es.catalog.product.cheapest)).toBeInTheDocument();
  });
});
