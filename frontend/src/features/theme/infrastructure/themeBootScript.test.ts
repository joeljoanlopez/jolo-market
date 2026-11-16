import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { afterEach, describe, expect, it, vi } from 'vitest';

import { resolveTheme, themePreferences } from '../domain/theme';

import { THEME_STORAGE_KEY } from './localStorageThemePreferenceStore';

const html = readFileSync(resolve(import.meta.dirname, '../../../../index.html'), 'utf8');

/** The inline script of index.html that paints the theme before the bundle loads (PD-75). */
function bootScript(): string {
  const match = /<script id="theme-boot">([\s\S]*?)<\/script>/.exec(html);
  if (match?.[1] === undefined) {
    throw new Error('index.html has no <script id="theme-boot">.');
  }
  return match[1];
}

function stubSystemPrefersDark(dark: boolean) {
  vi.stubGlobal(
    'matchMedia',
    (query: string) =>
      ({ matches: dark && query === '(prefers-color-scheme: dark)' }) as MediaQueryList,
  );
}

function runBootScript() {
  // The script is plain browser JavaScript kept in index.html; running it here is the only
  // way to check it agrees with resolveTheme().
  // eslint-disable-next-line @typescript-eslint/no-implied-eval
  const run = new Function(bootScript()) as () => void;
  run();
}

describe('theme boot script in index.html', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    localStorage.clear();
    delete document.documentElement.dataset.theme;
  });

  it('runs before the app bundle, so the first paint has the right theme', () => {
    expect(html.indexOf('id="theme-boot"')).toBeLessThan(html.indexOf('src="/src/main.tsx"'));
    expect(html.indexOf('id="theme-boot"')).toBeLessThan(html.indexOf('<body>'));
  });

  describe.each(themePreferences)('with "%s" stored', (preference) => {
    it.each([true, false])(
      'agrees with resolveTheme when the system prefers dark is %s',
      (dark) => {
        localStorage.setItem(THEME_STORAGE_KEY, preference);
        stubSystemPrefersDark(dark);

        runBootScript();

        expect(document.documentElement.dataset.theme).toBe(resolveTheme(preference, dark));
      },
    );
  });

  it('follows the system when nothing is stored', () => {
    stubSystemPrefersDark(true);

    runBootScript();

    expect(document.documentElement.dataset.theme).toBe('dark');
  });

  it('follows the system when the stored value is unknown', () => {
    localStorage.setItem(THEME_STORAGE_KEY, 'sepia');
    stubSystemPrefersDark(true);

    runBootScript();

    expect(document.documentElement.dataset.theme).toBe('dark');
  });

  it('paints light when neither localStorage nor matchMedia are available', () => {
    vi.stubGlobal('localStorage', undefined);
    vi.stubGlobal('matchMedia', undefined);

    runBootScript();

    expect(document.documentElement.dataset.theme).toBe('light');
  });
});
