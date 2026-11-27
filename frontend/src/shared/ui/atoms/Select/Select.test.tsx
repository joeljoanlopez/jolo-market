import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Select } from './Select';

describe('Select', () => {
  it('renders a select box that emits the chosen option', async () => {
    const onChange = vi.fn();
    render(
      <Select aria-label="Supermercado" value="" onChange={onChange}>
        <option value="">Todos</option>
        <option value="aldi">Aldi</option>
      </Select>,
    );

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Supermercado' }), 'aldi');

    expect(onChange).toHaveBeenCalledOnce();
    expect(screen.getByRole('combobox')).toHaveClass('border-border');
  });

  it('has a compact size that fits inside a row', () => {
    render(
      <Select aria-label="Sección" size="sm" defaultValue="a">
        <option value="a">A</option>
      </Select>,
    );

    expect(screen.getByRole('combobox')).toHaveClass('h-8', 'text-sm', 'w-auto');
    expect(screen.getByRole('combobox')).not.toHaveClass('w-full');
  });
});
