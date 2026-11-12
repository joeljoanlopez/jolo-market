import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { renderWithProviders } from '@/test/render';

import { formatPrice } from '../../domain/format';
import { PriceSummary } from './PriceSummary';
import { asShown } from './testProduct';

function valueOf(term: string): string | null | undefined {
  return screen.getByText(term).nextElementSibling?.textContent;
}

describe('PriceSummary', () => {
  it('shows the minimum, the maximum and the current price', () => {
    renderWithProviders(
      <PriceSummary range={{ minCents: 99, maxCents: 140 }} currentPriceCents={125} />,
    );

    expect(asShown(valueOf('Mínimo') ?? '')).toBe(asShown(formatPrice(99)));
    expect(asShown(valueOf('Máximo') ?? '')).toBe(asShown(formatPrice(140)));
    expect(asShown(valueOf('Actual') ?? '')).toBe(asShown(formatPrice(125)));
  });

  it('shows only the current price without snapshots', () => {
    renderWithProviders(<PriceSummary range={null} currentPriceCents={125} />);

    expect(screen.queryByText('Mínimo')).not.toBeInTheDocument();
    expect(screen.queryByText('Máximo')).not.toBeInTheDocument();
    expect(asShown(valueOf('Actual') ?? '')).toBe(asShown(formatPrice(125)));
  });
});
