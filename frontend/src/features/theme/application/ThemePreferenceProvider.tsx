import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';

import { resolveTheme, type ThemePreference } from '../domain/theme';

import type { SystemColorScheme } from './SystemColorScheme';
import { ThemePreferenceContext } from './themePreferenceContext';
import type { ThemePreferenceStore } from './ThemePreferenceStore';

interface ThemePreferenceProviderProps {
  store: ThemePreferenceStore;
  systemColorScheme: SystemColorScheme;
  children: ReactNode;
}

/**
 * Keeps `data-theme` on <html> in line with the user's choice (PD-74, PD-75). It wraps the
 * whole app, so pages without the selector (login, setup, activation) also follow the system
 * live. index.html sets the first value before the bundle loads.
 */
export function ThemePreferenceProvider({
  store,
  systemColorScheme,
  children,
}: ThemePreferenceProviderProps) {
  const [preference, setPreferenceState] = useState<ThemePreference>(() => store.read());

  useEffect(() => {
    const paint = (systemPrefersDark: boolean) => {
      document.documentElement.dataset.theme = resolveTheme(preference, systemPrefersDark);
    };
    paint(systemColorScheme.prefersDark());
    if (preference !== 'system') {
      return undefined;
    }
    return systemColorScheme.subscribe(paint);
  }, [preference, systemColorScheme]);

  const setPreference = useCallback(
    (next: ThemePreference) => {
      store.write(next);
      setPreferenceState(next);
    },
    [store],
  );

  const value = useMemo(() => ({ preference, setPreference }), [preference, setPreference]);

  return <ThemePreferenceContext value={value}>{children}</ThemePreferenceContext>;
}
