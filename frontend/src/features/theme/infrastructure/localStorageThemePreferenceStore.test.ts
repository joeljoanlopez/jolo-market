import { afterEach, describe, expect, it } from 'vitest';

import {
  createLocalStorageThemePreferenceStore,
  THEME_STORAGE_KEY,
} from './localStorageThemePreferenceStore';

/** A storage that throws on every call, as in some private browsing modes. */
const brokenStorage = (): Storage => {
  throw new Error('localStorage is not available');
};

describe('LocalStorageThemePreferenceStore', () => {
  afterEach(() => {
    localStorage.clear();
  });

  it('reads system when nothing is stored (PD-75)', () => {
    expect(createLocalStorageThemePreferenceStore().read()).toBe('system');
  });

  it('reads back a stored preference', () => {
    const store = createLocalStorageThemePreferenceStore();

    store.write('dark');

    expect(createLocalStorageThemePreferenceStore().read()).toBe('dark');
    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark');
  });

  it('reads system when the stored value is unknown (PD-75)', () => {
    localStorage.setItem(THEME_STORAGE_KEY, 'sepia');

    expect(createLocalStorageThemePreferenceStore().read()).toBe('system');
  });

  it('removes the key when the user goes back to system', () => {
    const store = createLocalStorageThemePreferenceStore();
    store.write('light');

    store.write('system');

    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBeNull();
  });

  it('reads system when localStorage is not available', () => {
    expect(createLocalStorageThemePreferenceStore(brokenStorage).read()).toBe('system');
  });

  it('does not throw when it cannot store the preference', () => {
    expect(() => {
      createLocalStorageThemePreferenceStore(brokenStorage).write('dark');
    }).not.toThrow();
  });
});
