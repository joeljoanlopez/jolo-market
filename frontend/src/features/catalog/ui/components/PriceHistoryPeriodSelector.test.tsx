import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { renderWithProviders } from '@/test/render';

import { PriceHistoryPeriodSelector } from './PriceHistoryPeriodSelector';

describe('PriceHistoryPeriodSelector', () => {
  it('offers every period and marks the selected one', () => {
    renderWithProviders(<PriceHistoryPeriodSelector value="6m" onChange={vi.fn()} />);

    const group = screen.getByRole('group', { name: 'Periodo' });
    expect(group).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '30 días' })).toHaveAttribute(
      'aria-pressed',
      'false',
    );
    expect(screen.getByRole('button', { name: '6 meses' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: '1 año' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: 'Todo' })).toHaveAttribute('aria-pressed', 'false');
  });

  it('emits the period chosen', async () => {
    const onChange = vi.fn();
    renderWithProviders(<PriceHistoryPeriodSelector value="6m" onChange={onChange} />);

    await userEvent.click(screen.getByRole('button', { name: '1 año' }));

    expect(onChange).toHaveBeenCalledWith('1y');
  });
});
