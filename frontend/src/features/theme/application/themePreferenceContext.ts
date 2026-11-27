import { createContext } from 'react';

import type { ThemePreference } from '../domain/theme';

export interface ThemePreferenceState {
  preference: ThemePreference;
  setPreference: (preference: ThemePreference) => void;
}

/** Holds the theme choice for the whole app; provided by ThemePreferenceProvider. */
export const ThemePreferenceContext = createContext<ThemePreferenceState | null>(null);
