import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Label } from './Label';

describe('Label', () => {
  it('labels the field it points to', () => {
    render(
      <>
        <Label htmlFor="q">Buscar</Label>
        <input id="q" />
      </>,
    );

    expect(screen.getByRole('textbox', { name: 'Buscar' })).toBeInTheDocument();
    expect(screen.getByText('Buscar')).toHaveClass('text-sm', 'font-medium');
  });
});
