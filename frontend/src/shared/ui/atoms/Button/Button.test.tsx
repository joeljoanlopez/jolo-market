import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Button } from './Button';

describe('Button', () => {
  it('renders its label as an accessible button', () => {
    render(<Button>Guardar</Button>);

    expect(screen.getByRole('button', { name: 'Guardar' })).toBeInTheDocument();
  });

  it('does not submit forms unless asked to', () => {
    render(<Button>Guardar</Button>);

    expect(screen.getByRole('button')).toHaveAttribute('type', 'button');
  });

  it('can be a submit button', () => {
    render(<Button type="submit">Guardar</Button>);

    expect(screen.getByRole('button')).toHaveAttribute('type', 'submit');
  });

  it('emits click events', async () => {
    const onClick = vi.fn();
    render(<Button onClick={onClick}>Guardar</Button>);

    await userEvent.click(screen.getByRole('button'));

    expect(onClick).toHaveBeenCalledOnce();
  });

  it('does not emit clicks while disabled', async () => {
    const onClick = vi.fn();
    render(
      <Button onClick={onClick} disabled>
        Guardar
      </Button>,
    );

    await userEvent.click(screen.getByRole('button'));

    expect(onClick).not.toHaveBeenCalled();
  });

  it('uses the primary variant by default', () => {
    render(<Button>Guardar</Button>);

    expect(screen.getByRole('button')).toHaveClass('bg-primary', 'text-primary-foreground');
  });

  it.each([
    ['secondary', 'bg-surface'],
    ['danger', 'bg-danger'],
  ] as const)('supports the %s variant', (variant, expectedClass) => {
    render(<Button variant={variant}>Guardar</Button>);

    expect(screen.getByRole('button')).toHaveClass(expectedClass);
    expect(screen.getByRole('button')).not.toHaveClass('bg-primary');
  });

  it('supports a small size', () => {
    render(<Button size="sm">Guardar</Button>);

    expect(screen.getByRole('button')).toHaveClass('text-sm');
  });

  it('lets callers override classes', () => {
    render(<Button className="w-full">Guardar</Button>);

    expect(screen.getByRole('button')).toHaveClass('w-full');
  });
});
