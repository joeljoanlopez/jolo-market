import type { ThemePreferenceStore } from '../application/ThemePreferenceStore';
import { parseThemePreference } from '../domain/theme';

/** Also read by the script in index.html, which paints the theme before the bundle loads. */
export const THEME_STORAGE_KEY = 'jolo-market.theme';

/**
 * Keeps the theme choice in this browser (PD-75). Storage can be unavailable (private
 * browsing, blocked site data): then the choice only lasts until the page is reloaded.
 */
export function createLocalStorageThemePreferenceStore(
  storage: () => Storage = () => window.localStorage,
): ThemePreferenceStore {
  return {
    read() {
      try {
        return parseThemePreference(storage().getItem(THEME_STORAGE_KEY));
      } catch {
        return 'system';
      }
    },
    write(preference) {
      try {
        if (preference === 'system') {
          storage().removeItem(THEME_STORAGE_KEY);
        } else {
          storage().setItem(THEME_STORAGE_KEY, preference);
        }
      } catch {
        // Not stored: the theme still applies until the next reload.
      }
    },
  };
}
