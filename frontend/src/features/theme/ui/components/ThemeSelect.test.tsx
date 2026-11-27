import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { ThemeSelect } from './ThemeSelect';

const copy = es.theme;

describe('ThemeSelect', () => {
  it('offers Sistema, Claro and Oscuro, in that order (PD-76)', () => {
    renderWithProviders(<ThemeSelect value="system" onChange={vi.fn()} />);

    const select = screen.getByRole('combobox', { name: copy.label });
    expect(Array.from((select as HTMLSelectElement).options).map((option) => option.text)).toEqual([
      copy.options.system,
      copy.options.light,
      copy.options.dark,
    ]);
  });

  it('shows the current choice', () => {
    renderWithProviders(<ThemeSelect value="dark" onChange={vi.fn()} />);

    expect(screen.getByRole('combobox', { name: copy.label })).toHaveValue('dark');
  });

  it('emits the chosen preference', async () => {
    const onChange = vi.fn();
    renderWithProviders(<ThemeSelect value="system" onChange={onChange} />);

    await userEvent.selectOptions(
      screen.getByRole('combobox', { name: copy.label }),
      copy.options.light,
    );

    expect(onChange).toHaveBeenCalledWith('light');
  });
});
