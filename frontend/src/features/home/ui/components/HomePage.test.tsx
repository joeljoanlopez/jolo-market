import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { HomePage } from './HomePage';

describe('HomePage', () => {
  it('shows the placeholder title and description in Spanish', () => {
    renderWithProviders(<HomePage />);

    expect(screen.getByRole('heading', { level: 1, name: es.home.title })).toBeInTheDocument();
    expect(screen.getByText(es.home.description)).toBeInTheDocument();
  });
});
