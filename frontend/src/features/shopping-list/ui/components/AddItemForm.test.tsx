import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it, vi } from 'vitest';

import { aProduct } from '@/features/catalog/ui/components/testProduct';
import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { suggestionOptions } from '../../domain/suggestions';
import { AddItemForm } from './AddItemForm';

const copy = es.lists.add;
const milk = aProduct({ id: 'milk', name: 'Leche entera' });

type Props = ComponentProps<typeof AddItemForm>;

function renderForm(overrides: Partial<Props> = {}) {
  const props: Props = {
    text: 'leche',
    quantity: '1',
    error: null,
    pending: false,
    suggestions: {
      search: { status: 'success', options: suggestionOptions([milk]) },
      highlighted: null,
      seeAllHref: '/search?q=leche&list=l1',
    },
    onTextChange: vi.fn(),
    onQuantityChange: vi.fn(),
    onSubmit: vi.fn(),
    onPickProduct: vi.fn(),
    onMoveHighlight: vi.fn(),
    onCloseSuggestions: vi.fn(),
    onRetrySuggestions: vi.fn(),
    ...overrides,
  };
  renderWithProviders(
    <MemoryRouter>
      <AddItemForm {...props} />
    </MemoryRouter>,
  );
  return props;
}

function field() {
  return screen.getByRole('combobox', { name: copy.text });
}

describe('AddItemForm', () => {
  it('is a combobox that controls the open suggestion list', () => {
    renderForm();

    expect(field()).toHaveAttribute('aria-expanded', 'true');
    expect(field()).toHaveAttribute('aria-controls', screen.getByRole('listbox').id);
    expect(field()).not.toHaveAttribute('aria-activedescendant');
  });

  it('points at the highlighted option', () => {
    renderForm({
      suggestions: {
        search: { status: 'success', options: suggestionOptions([milk]) },
        highlighted: 1,
        seeAllHref: '/search',
      },
    });

    expect(field()).toHaveAttribute(
      'aria-activedescendant',
      screen.getByRole('option', { selected: true }).id,
    );
  });

  it('shows no panel while the suggestions are closed', () => {
    renderForm({ suggestions: null });

    expect(field()).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('listbox')).toBeNull();
  });

  it('moves the highlight with the arrow keys (PD-81)', async () => {
    const props = renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('{ArrowDown}{ArrowUp}');

    expect(props.onMoveHighlight).toHaveBeenNthCalledWith(1, 'next');
    expect(props.onMoveHighlight).toHaveBeenNthCalledWith(2, 'previous');
  });

  it('closes the suggestions with Escape', async () => {
    const props = renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('{Escape}');

    expect(props.onCloseSuggestions).toHaveBeenCalledOnce();
  });

  it('adds the free text with Enter when nothing is highlighted (PD-81)', async () => {
    const props = renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('{Enter}');

    expect(props.onSubmit).toHaveBeenCalledOnce();
    expect(props.onPickProduct).not.toHaveBeenCalled();
  });

  it('adds the highlighted product with Enter (PD-81)', async () => {
    const props = renderForm({
      suggestions: {
        search: { status: 'success', options: suggestionOptions([milk]) },
        highlighted: 1,
        seeAllHref: '/search',
      },
    });
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('{Enter}');

    expect(props.onPickProduct).toHaveBeenCalledWith(milk);
    expect(props.onSubmit).not.toHaveBeenCalled();
  });

  it('adds the free text with the button even when a product is highlighted', async () => {
    const props = renderForm({
      suggestions: {
        search: { status: 'success', options: suggestionOptions([milk]) },
        highlighted: 1,
        seeAllHref: '/search',
      },
    });
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: copy.submit }));

    expect(props.onSubmit).toHaveBeenCalledOnce();
    expect(props.onPickProduct).not.toHaveBeenCalled();
  });
});
