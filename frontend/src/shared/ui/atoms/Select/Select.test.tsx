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
});
