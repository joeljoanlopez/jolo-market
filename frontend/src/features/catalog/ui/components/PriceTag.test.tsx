import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { formatPrice } from '../../domain/format';
import { PriceTag } from './PriceTag';
import { asShown } from './testProduct';

describe('PriceTag', () => {
  it('shows the shelf price and the unit price', () => {
    renderWithProviders(<PriceTag priceCents={125} unitPrice={{ cents: 83, unit: 'l' }} />);

    expect(screen.getByText(asShown(formatPrice(125)))).toBeInTheDocument();
    expect(screen.getByText(asShown(`${formatPrice(83)}/L`))).toBeInTheDocument();
    expect(screen.queryByText(es.catalog.product.cheapest)).not.toBeInTheDocument();
  });

  it('says so when there is no unit price', () => {
    renderWithProviders(<PriceTag priceCents={125} unitPrice={null} />);

    expect(screen.getByText(es.catalog.product.noUnitPrice)).toBeInTheDocument();
  });

  it('highlights the cheapest unit price with a label and its colors', () => {
    renderWithProviders(
      <PriceTag priceCents={125} unitPrice={{ cents: 83, unit: 'l' }} highlighted />,
    );

    expect(screen.getByText(es.catalog.product.cheapest)).toBeInTheDocument();
    expect(screen.getByText(asShown(`${formatPrice(83)}/L`))).toHaveClass('bg-primary');
  });
});
