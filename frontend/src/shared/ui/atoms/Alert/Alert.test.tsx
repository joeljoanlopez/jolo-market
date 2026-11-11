import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Alert } from './Alert';

describe('Alert', () => {
  it('announces its message to screen readers', () => {
    render(<Alert>Usuario o contraseña incorrectos.</Alert>);

    expect(screen.getByRole('alert')).toHaveTextContent('Usuario o contraseña incorrectos.');
  });

  it('uses the danger colors', () => {
    render(<Alert>Error</Alert>);

    expect(screen.getByRole('alert')).toHaveClass('border-danger', 'text-danger');
  });
});
