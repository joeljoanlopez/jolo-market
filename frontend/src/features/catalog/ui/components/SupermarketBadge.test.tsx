import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { renderWithProviders } from '@/test/render';

import { SupermarketBadge } from './SupermarketBadge';

describe('SupermarketBadge', () => {
  it.each([
    ['mercadona', 'Mercadona'],
    ['bonpreu', 'Bonpreu'],
    ['aldi', 'Aldi'],
  ])('shows %s with its own colors', (id, name) => {
    renderWithProviders(<SupermarketBadge supermarket={{ id, name }} />);

    expect(screen.getByText(name)).toHaveClass(`bg-${id}`, `text-${id}-foreground`);
  });

  it('uses neutral colors for a supermarket without its own', () => {
    renderWithProviders(<SupermarketBadge supermarket={{ id: 'lidl', name: 'Lidl' }} />);

    expect(screen.getByText('Lidl')).toHaveClass('bg-surface', 'text-foreground');
  });
});
