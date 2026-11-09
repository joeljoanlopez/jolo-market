import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';

import { SearchBox } from './SearchBox';

function ControlledSearchBox({ onSubmit }: { onSubmit?: (value: string) => void }) {
  const [value, setValue] = useState('');
  return (
    <SearchBox
      label="Buscar productos"
      value={value}
      onChange={setValue}
      {...(onSubmit === undefined ? {} : { onSubmit, submitLabel: 'Buscar' })}
    />
  );
}

describe('SearchBox', () => {
  it('is a search landmark with a labelled text box', () => {
    render(<SearchBox label="Buscar productos" value="leche" onChange={vi.fn()} />);

    expect(screen.getByRole('search')).toBeInTheDocument();
    expect(screen.getByRole('searchbox', { name: 'Buscar productos' })).toHaveValue('leche');
  });

  it('emits every change of the text', async () => {
    const onChange = vi.fn();
    render(<SearchBox label="Buscar productos" value="" onChange={onChange} />);

    await userEvent.type(screen.getByRole('searchbox'), 'l');

    expect(onChange).toHaveBeenCalledWith('l');
  });

  it('emits the text when the submit button is pressed', async () => {
    const onSubmit = vi.fn();
    render(<ControlledSearchBox onSubmit={onSubmit} />);

    await userEvent.type(screen.getByRole('searchbox'), 'leche');
    await userEvent.click(screen.getByRole('button', { name: 'Buscar' }));

    expect(onSubmit).toHaveBeenCalledWith('leche');
  });

  it('has no submit button when nothing listens to submits, and Enter does not reload', async () => {
    render(<ControlledSearchBox />);

    await userEvent.type(screen.getByRole('searchbox'), 'leche{Enter}');

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.getByRole('searchbox')).toHaveValue('leche');
  });
});
