import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Checkbox } from './Checkbox';

describe('Checkbox', () => {
  it('is a labelled checkbox that reports its new state', async () => {
    const onChange = vi.fn();
    render(<Checkbox aria-label="pan" checked={false} onChange={onChange} />);

    await userEvent.click(screen.getByRole('checkbox', { name: 'pan' }));

    expect(onChange).toHaveBeenCalledOnce();
  });

  it('shows the checked state', () => {
    render(<Checkbox aria-label="pan" checked readOnly />);

    expect(screen.getByRole('checkbox', { name: 'pan' })).toBeChecked();
  });
});
