import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';
import { testProductDetail } from '@/test/testProductDetail';

import { formatPrice } from '../../domain/format';
import { ProductDetailView } from './ProductDetailView';
import { asShown } from './testProduct';

describe('ProductDetailView', () => {
  it('shows the product with its supermarket, packaging, price, actions and history', () => {
    renderWithProviders(
      <ProductDetailView
        product={testProductDetail({ imageUrl: 'https://img.example/leche.jpg' })}
        actions={<button type="button">Acción</button>}
      >
        <p>Historial</p>
      </ProductDetailView>,
    );

    expect(screen.getByRole('heading', { level: 1, name: 'Leche entera' })).toBeInTheDocument();
    expect(screen.getByText('Hacendado')).toBeInTheDocument();
    expect(screen.getByText('Mercadona')).toBeInTheDocument();
    expect(screen.getByText('1,5 L')).toBeInTheDocument();
    expect(screen.getByText(asShown(formatPrice(125)))).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Foto de Leche entera' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Acción' })).toBeInTheDocument();
    expect(screen.getByText('Historial')).toBeInTheDocument();
    expect(screen.queryByText(es.catalog.detail.unavailable)).not.toBeInTheDocument();
  });

  it('flags an unavailable product', () => {
    renderWithProviders(
      <ProductDetailView product={testProductDetail({ available: false })}>
        <p>Historial</p>
      </ProductDetailView>,
    );

    expect(screen.getByText(es.catalog.detail.unavailable)).toBeInTheDocument();
  });
});
