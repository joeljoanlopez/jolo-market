import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it } from 'vitest';

import { FakeSystemColorScheme } from '@/test/FakeSystemColorScheme';
import { InMemoryThemePreferenceStore } from '@/test/InMemoryThemePreferenceStore';

import { ThemePreferenceProvider } from './ThemePreferenceProvider';
import { useThemePreference } from './useThemePreference';

function renderThemeHook(store: InMemoryThemePreferenceStore, system: FakeSystemColorScheme) {
  return renderHook(() => useThemePreference(), {
    wrapper: ({ children }: { children: ReactNode }) => (
      <ThemePreferenceProvider store={store} systemColorScheme={system}>
        {children}
      </ThemePreferenceProvider>
    ),
  });
}

function paintedTheme(): string | undefined {
  return document.documentElement.dataset.theme;
}

describe('useThemePreference', () => {
  afterEach(() => {
    delete document.documentElement.dataset.theme;
  });

  it('starts with the stored preference and paints it', () => {
    const { result } = renderThemeHook(
      new InMemoryThemePreferenceStore('dark'),
      new FakeSystemColorScheme(false),
    );

    expect(result.current.preference).toBe('dark');
    expect(paintedTheme()).toBe('dark');
  });

  it('stores a new preference and paints it at once', () => {
    const store = new InMemoryThemePreferenceStore('system');
    const { result } = renderThemeHook(store, new FakeSystemColorScheme(false));

    act(() => {
      result.current.setPreference('dark');
    });

    expect(result.current.preference).toBe('dark');
    expect(store.stored).toBe('dark');
    expect(paintedTheme()).toBe('dark');
  });

  it('follows the system theme live while the preference is system (PD-74)', () => {
    const system = new FakeSystemColorScheme(false);
    renderThemeHook(new InMemoryThemePreferenceStore('system'), system);
    expect(paintedTheme()).toBe('light');

    act(() => {
      system.switchTo(true);
    });

    expect(paintedTheme()).toBe('dark');
  });

  it('ignores the system theme and stops listening while light or dark is chosen', () => {
    const system = new FakeSystemColorScheme(false);
    const { result } = renderThemeHook(new InMemoryThemePreferenceStore('system'), system);

    act(() => {
      result.current.setPreference('light');
    });
    act(() => {
      system.switchTo(true);
    });

    expect(paintedTheme()).toBe('light');
    expect(system.listenerCount).toBe(0);
  });

  it('follows the system again when the user goes back to system', () => {
    const system = new FakeSystemColorScheme(true);
    const { result } = renderThemeHook(new InMemoryThemePreferenceStore('light'), system);

    act(() => {
      result.current.setPreference('system');
    });

    expect(paintedTheme()).toBe('dark');
    act(() => {
      system.switchTo(false);
    });
    expect(paintedTheme()).toBe('light');
  });

  it('stops listening to the system when unmounted', () => {
    const system = new FakeSystemColorScheme(false);
    const { unmount } = renderThemeHook(new InMemoryThemePreferenceStore('system'), system);

    unmount();

    expect(system.listenerCount).toBe(0);
  });
});
