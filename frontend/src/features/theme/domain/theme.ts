/** What the user chose in the "Tema" dropdown (PD-74). */
export type ThemePreference = 'system' | 'light' | 'dark';

/** The theme actually painted. */
export type Theme = 'light' | 'dark';

export const themePreferences: readonly ThemePreference[] = ['system', 'light', 'dark'];

/** A stored value that is missing or unknown means "follow the system" (PD-75). */
export function parseThemePreference(value: string | null): ThemePreference {
  return themePreferences.find((preference) => preference === value) ?? 'system';
}

/** The theme to paint. A system without a dark preference counts as light (PD-74). */
export function resolveTheme(preference: ThemePreference, systemPrefersDark: boolean): Theme {
  if (preference === 'system') {
    return systemPrefersDark ? 'dark' : 'light';
  }
  return preference;
}
