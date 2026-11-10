import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FormField } from './FormField';

describe('FormField', () => {
  it('labels its input', () => {
    render(<FormField label="Usuario" name="username" />);

    expect(screen.getByLabelText('Usuario')).toHaveAttribute('name', 'username');
  });

  it('describes the input with its hint', () => {
    render(<FormField label="Código" name="code" hint="Seis dígitos." />);

    expect(screen.getByLabelText('Código')).toHaveAccessibleDescription('Seis dígitos.');
  });

  it('marks the input invalid and describes it with the error', () => {
    render(<FormField label="Contraseña" name="password" hint="Mínimo 12." error="Muy corta." />);

    const input = screen.getByLabelText('Contraseña');
    expect(input).toBeInvalid();
    expect(input).toHaveAccessibleDescription('Mínimo 12. Muy corta.');
  });

  it('passes input attributes through', () => {
    render(
      <FormField
        label="Contraseña"
        name="password"
        type="password"
        autoComplete="current-password"
      />,
    );

    expect(screen.getByLabelText('Contraseña')).toHaveAttribute('type', 'password');
    expect(screen.getByLabelText('Contraseña')).toHaveAttribute('autocomplete', 'current-password');
  });
});
