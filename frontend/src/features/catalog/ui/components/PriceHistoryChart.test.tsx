import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { renderWithProviders } from '@/test/render';

import { formatPrice } from '../../domain/format';
import type { PricePoint } from '../../domain/priceHistory';
import { PriceHistoryChart } from './PriceHistoryChart';

function point(day: string, priceCents: number): PricePoint {
  return { capturedAt: new Date(`${day}T05:00:00Z`), priceCents, unitPrice: null };
}

function line(container: HTMLElement): string | null | undefined {
  return container.querySelector('path')?.getAttribute('d');
}

describe('PriceHistoryChart', () => {
  it('draws the price as steps over time, from the first to the last snapshot', () => {
    const { container } = renderWithProviders(
      <PriceHistoryChart
        points={[point('2026-11-01', 100), point('2026-11-03', 200), point('2026-11-05', 150)]}
      />,
    );

    // 600 × 200 view box with an 8-unit margin: x from 8 to 592, the highest price at y 8, the lowest at 192.
    expect(line(container)).toBe('M8 192 H300 V8 H592 V100');
  });

  it('draws consecutive equal prices as one flat segment', () => {
    const { container } = renderWithProviders(
      <PriceHistoryChart
        points={[
          point('2026-11-01', 100),
          point('2026-11-02', 100),
          point('2026-11-03', 200),
          point('2026-11-04', 200),
          point('2026-11-05', 200),
        ]}
      />,
    );

    expect(line(container)).toBe('M8 192 H300 V8 H592');
  });

  it('draws a price that never changed as a flat line in the middle', () => {
    const { container } = renderWithProviders(
      <PriceHistoryChart points={[point('2026-11-01', 125), point('2026-11-05', 125)]} />,
    );

    expect(line(container)).toBe('M8 100 H592');
  });

  it('draws a single snapshot as one point', () => {
    const { container } = renderWithProviders(
      <PriceHistoryChart points={[point('2026-11-01', 125)]} />,
    );

    expect(container.querySelector('path')).toBeNull();
    expect(container.querySelector('circle')).toHaveAttribute('cx', '300');
    expect(
      screen.getByRole('img', { name: `Precio el 01/11/2026: ${formatPrice(125)}.` }),
    ).toBeInTheDocument();
  });

  it('describes the period and the prices to screen readers', () => {
    renderWithProviders(
      <PriceHistoryChart points={[point('2026-11-01', 100), point('2026-11-05', 150)]} />,
    );

    expect(
      screen.getByRole('img', {
        // aria-label keeps the non-breaking space Intl writes before "€".
        name: `Precio del 01/11/2026 al 05/11/2026: entre ${formatPrice(100)} y ${formatPrice(150)}.`,
      }),
    ).toBeInTheDocument();
  });

  it('labels the time axis with the first and the last day', () => {
    renderWithProviders(
      <PriceHistoryChart points={[point('2026-11-01', 100), point('2026-11-05', 150)]} />,
    );

    expect(screen.getByText('01/11/2026')).toBeInTheDocument();
    expect(screen.getByText('05/11/2026')).toBeInTheDocument();
  });
});
