import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Text } from './Text';

describe('Text', () => {
  it('renders a paragraph by default', () => {
    render(<Text>Hola</Text>);

    expect(screen.getByText('Hola').tagName).toBe('P');
  });

  it('renders the requested element', () => {
    render(<Text as="h1">Hola</Text>);

    expect(screen.getByRole('heading', { level: 1, name: 'Hola' })).toBeInTheDocument();
  });

  it('uses the default tone and base size', () => {
    render(<Text>Hola</Text>);

    expect(screen.getByText('Hola')).toHaveClass('text-foreground', 'text-base');
  });

  it('supports the muted tone', () => {
    render(<Text tone="muted">Hola</Text>);

    expect(screen.getByText('Hola')).toHaveClass('text-muted');
    expect(screen.getByText('Hola')).not.toHaveClass('text-foreground');
  });

  it('supports title sizes and weights', () => {
    render(
      <Text size="2xl" weight="bold">
        Hola
      </Text>,
    );

    expect(screen.getByText('Hola')).toHaveClass('text-2xl', 'font-bold');
  });
});
