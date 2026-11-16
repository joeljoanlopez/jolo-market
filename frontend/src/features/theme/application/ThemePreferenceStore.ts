import type { ThemePreference } from '../domain/theme';

/** Where the user's theme choice is kept between visits (PD-75). */
export interface ThemePreferenceStore {
  read(): ThemePreference;
  write(preference: ThemePreference): void;
}
