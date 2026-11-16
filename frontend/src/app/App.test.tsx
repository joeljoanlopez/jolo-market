import { act, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeSystemColorScheme } from '@/test/FakeSystemColorScheme';
import { InMemoryThemePreferenceStore } from '@/test/InMemoryThemePreferenceStore';
import { renderApp } from '@/test/renderApp';

function renderLoggedInAt(path: string) {
  return renderApp(path, { auth: FakeAuthGateway.loggedInAs(maria) });
}

describe('App', () => {
  it('shows the app shell with the home page at /', async () => {
    renderLoggedInAt('/');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.home.title }),
    ).toBeInTheDocument();
    expect(screen.getByRole('banner')).toHaveTextContent(es.app.name);
  });

  it('shows the search page at /search, with the catalog wired in', async () => {
    renderLoggedInAt('/search?q=leche');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.catalog.search.title }),
    ).toBeInTheDocument();
    expect(await screen.findByText('No hay resultados para «leche».')).toBeInTheDocument();
  });

  it('shows the not-found page for an unknown route', async () => {
    renderLoggedInAt('/this-page-does-not-exist');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.notFound.title }),
    ).toBeInTheDocument();
  });

  describe('theme', () => {
    afterEach(() => {
      delete document.documentElement.dataset.theme;
    });

    it('paints the stored theme on pages without the header too (PD-75)', async () => {
      renderApp('/login', { themeStore: new InMemoryThemePreferenceStore('dark') });

      expect(
        await screen.findByRole('heading', { level: 1, name: es.auth.login.title }),
      ).toBeInTheDocument();
      expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('follows the system theme live on the login page (PD-74)', async () => {
      const system = new FakeSystemColorScheme(false);
      renderApp('/login', { systemColorScheme: system });
      await screen.findByRole('heading', { level: 1, name: es.auth.login.title });

      act(() => {
        system.switchTo(true);
      });

      expect(document.documentElement.dataset.theme).toBe('dark');
    });
  });
});
