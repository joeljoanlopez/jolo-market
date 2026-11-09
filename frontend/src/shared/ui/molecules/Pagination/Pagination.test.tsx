import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Pagination } from './Pagination';

const labels = {
  label: 'Paginación',
  previous: 'Anterior',
  next: 'Siguiente',
  status: 'Página 2 de 3',
};

describe('Pagination', () => {
  it('is a labelled navigation with the page status', () => {
    render(<Pagination page={2} pageCount={3} onPageChange={vi.fn()} labels={labels} />);

    expect(screen.getByRole('navigation', { name: 'Paginación' })).toHaveTextContent(
      'Página 2 de 3',
    );
  });

  it('emits the previous and the next page', async () => {
    const onPageChange = vi.fn();
    render(<Pagination page={2} pageCount={3} onPageChange={onPageChange} labels={labels} />);

    await userEvent.click(screen.getByRole('button', { name: 'Anterior' }));
    await userEvent.click(screen.getByRole('button', { name: 'Siguiente' }));

    expect(onPageChange.mock.calls).toEqual([[1], [3]]);
  });

  it('disables previous on the first page and next on the last one', () => {
    const { rerender } = render(
      <Pagination page={1} pageCount={3} onPageChange={vi.fn()} labels={labels} />,
    );
    expect(screen.getByRole('button', { name: 'Anterior' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeEnabled();

    rerender(<Pagination page={3} pageCount={3} onPageChange={vi.fn()} labels={labels} />);
    expect(screen.getByRole('button', { name: 'Anterior' })).toBeEnabled();
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeDisabled();
  });
});
