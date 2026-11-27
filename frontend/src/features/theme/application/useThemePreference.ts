import { useContext } from 'react';

import { ThemePreferenceContext, type ThemePreferenceState } from './themePreferenceContext';

/** The user's theme choice and a way to change it. */
export function useThemePreference(): ThemePreferenceState {
  const state = useContext(ThemePreferenceContext);
  if (state === null) {
    throw new Error('useThemePreference() needs a ThemePreferenceProvider.');
  }
  return state;
}
