import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import type { PriceHistoryState } from '../../application/usePriceHistory';
import { formatPrice } from '../../domain/format';
import { PriceHistoryPanel } from './PriceHistoryPanel';
import { asShown } from './testProduct';

function success(prices: number[], isFetching = false): PriceHistoryState {
  return {
    status: 'success',
    isFetching,
    history: {
      productId: 'milk',
      points: prices.map((priceCents, index) => ({
        capturedAt: new Date(Date.UTC(2026, 10, index + 1, 5)),
        priceCents,
        unitPrice: null,
      })),
    },
  };
}

function renderPanel(state: PriceHistoryState, onPeriodChange = vi.fn()) {
  return renderWithProviders(
    <PriceHistoryPanel
      state={state}
      period="6m"
      onPeriodChange={onPeriodChange}
      currentPriceCents={130}
    />,
  );
}

describe('PriceHistoryPanel', () => {
  it('shows the chart and the minimum, maximum and current price of the period', () => {
    renderPanel(success([120, 140, 130]));

    expect(screen.getByRole('heading', { name: es.catalog.history.title })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: /^Precio del/u })).toBeInTheDocument();
    expect(screen.getByText(asShown(formatPrice(120)))).toBeInTheDocument();
    expect(screen.getByText(asShown(formatPrice(140)))).toBeInTheDocument();
    expect(screen.getByText(asShown(formatPrice(130)))).toBeInTheDocument();
  });

  it('says there are no prices in the period, and still shows the current price', () => {
    renderPanel(success([]));

    expect(screen.getByText(es.catalog.history.empty)).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.queryByText(es.catalog.history.min)).not.toBeInTheDocument();
    expect(screen.getByText(es.catalog.history.current)).toBeInTheDocument();
  });

  it('is busy while another period loads', () => {
    renderPanel(success([120], true));

    expect(screen.getByRole('region', { name: es.catalog.history.title })).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('shows that it is loading', () => {
    renderPanel({ status: 'loading' });

    expect(screen.getByText(es.catalog.history.loading)).toBeInTheDocument();
  });

  it('shows an error with a retry', async () => {
    const retry = vi.fn();
    renderPanel({ status: 'error', retry });

    expect(screen.getByRole('alert')).toHaveTextContent(es.catalog.history.error);
    await userEvent.click(screen.getByRole('button', { name: es.catalog.history.retry }));
    expect(retry).toHaveBeenCalled();
  });

  it('lets the user choose another period', async () => {
    const onPeriodChange = vi.fn();
    renderPanel(success([120]), onPeriodChange);

    await userEvent.click(screen.getByRole('button', { name: es.catalog.history.period['30d'] }));

    expect(onPeriodChange).toHaveBeenCalledWith('30d');
  });
});
