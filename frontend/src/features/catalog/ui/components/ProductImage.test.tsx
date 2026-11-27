import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { ProductImage } from './ProductImage';

describe('ProductImage', () => {
  it('shows the product image, loaded lazily', () => {
    renderWithProviders(<ProductImage src="https://img.example/leche.jpg" name="Leche entera" />);

    const image = screen.getByRole('img', { name: 'Foto de Leche entera' });
    expect(image).toHaveAttribute('src', 'https://img.example/leche.jpg');
    expect(image).toHaveAttribute('loading', 'lazy');
  });

  it('shows the placeholder when the product has no image', () => {
    renderWithProviders(<ProductImage src={null} name="Leche entera" />);

    expect(screen.getByRole('img', { name: es.catalog.product.noImage })).toBeInTheDocument();
  });

  it('falls back to the placeholder when the image fails to load (PD-98)', () => {
    renderWithProviders(<ProductImage src="https://img.example/gone.jpg" name="Leche entera" />);

    fireEvent.error(screen.getByRole('img', { name: 'Foto de Leche entera' }));

    expect(screen.getByRole('img', { name: es.catalog.product.noImage })).toBeInTheDocument();
    expect(screen.queryByRole('img', { name: 'Foto de Leche entera' })).not.toBeInTheDocument();
  });

  it('shows a new image after a failed one', () => {
    const { rerender } = renderWithProviders(
      <ProductImage src="https://img.example/gone.jpg" name="Leche entera" />,
    );
    fireEvent.error(screen.getByRole('img', { name: 'Foto de Leche entera' }));

    rerender(<ProductImage src="https://img.example/new.jpg" name="Leche entera" />);

    expect(screen.getByRole('img', { name: 'Foto de Leche entera' })).toHaveAttribute(
      'src',
      'https://img.example/new.jpg',
    );
  });

  it('can load the image eagerly', () => {
    renderWithProviders(
      <ProductImage src="https://img.example/leche.jpg" name="Leche entera" loading="eager" />,
    );

    expect(screen.getByRole('img', { name: 'Foto de Leche entera' })).toHaveAttribute(
      'loading',
      'eager',
    );
  });

  it.each([
    ['sm', 'size-12'],
    ['md', 'size-16'],
    ['lg', 'size-24'],
  ] as const)('has the %s size', (size, expectedClass) => {
    renderWithProviders(<ProductImage src={null} name="Leche entera" size={size} />);

    expect(screen.getByRole('img')).toHaveClass(expectedClass);
  });

  it('fades the image and the placeholder (PD-97)', () => {
    renderWithProviders(
      <>
        <ProductImage src="https://img.example/leche.jpg" name="Leche entera" faded />
        <ProductImage src={null} name="Pan" faded />
      </>,
    );

    for (const image of screen.getAllByRole('img')) {
      expect(image).toHaveClass('opacity-50');
    }
  });

  it('is not faded by default', () => {
    renderWithProviders(<ProductImage src={null} name="Leche entera" />);

    expect(screen.getByRole('img')).not.toHaveClass('opacity-50');
  });
});
