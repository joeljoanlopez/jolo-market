import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { SectionHeader } from './SectionHeader';

describe('SectionHeader', () => {
  it('shows only the Spanish name of the section as a heading (PD-93)', () => {
    renderWithProviders(<SectionHeader section="dairy-and-eggs" />);

    expect(
      screen.getByRole('heading', { level: 2, name: es.lists.sections['dairy-and-eggs'] }),
    ).toBeInTheDocument();
  });
});
