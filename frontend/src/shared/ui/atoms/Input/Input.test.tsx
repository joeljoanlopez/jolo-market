import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { Input } from './Input';

describe('Input', () => {
  it('renders a text box that accepts typing', async () => {
    render(<Input aria-label="Buscar" />);

    await userEvent.type(screen.getByRole('textbox', { name: 'Buscar' }), 'leche');

    expect(screen.getByRole('textbox')).toHaveValue('leche');
  });

  it('is valid by default', () => {
    render(<Input aria-label="Buscar" />);

    expect(screen.getByRole('textbox')).not.toHaveAttribute('aria-invalid');
    expect(screen.getByRole('textbox')).toHaveClass('border-border');
  });

  it('marks itself invalid for assistive technology and visually', () => {
    render(<Input aria-label="Buscar" invalid />);

    expect(screen.getByRole('textbox')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('textbox')).toHaveClass('border-danger');
  });
});
