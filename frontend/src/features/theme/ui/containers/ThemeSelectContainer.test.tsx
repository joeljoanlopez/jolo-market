import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { InMemoryThemePreferenceStore } from '@/test/InMemoryThemePreferenceStore';
import { renderApp } from '@/test/renderApp';

const copy = es.theme;

describe('ThemeSelectContainer', () => {
  afterEach(() => {
    delete document.documentElement.dataset.theme;
  });

  it('is in the app header with the stored choice (PD-76)', async () => {
    renderApp('/', {
      auth: FakeAuthGateway.loggedInAs(maria),
      themeStore: new InMemoryThemePreferenceStore('light'),
    });

    const header = await screen.findByRole('banner');
    expect(within(header).getByRole('combobox', { name: copy.label })).toHaveValue('light');
  });

  it('paints and stores the chosen theme at once', async () => {
    const themeStore = new InMemoryThemePreferenceStore('system');
    renderApp('/', { auth: FakeAuthGateway.loggedInAs(maria), themeStore });

    await userEvent.selectOptions(
      await screen.findByRole('combobox', { name: copy.label }),
      copy.options.dark,
    );

    expect(document.documentElement.dataset.theme).toBe('dark');
    expect(themeStore.stored).toBe('dark');
  });
});
