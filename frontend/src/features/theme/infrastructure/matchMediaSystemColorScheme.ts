import type { SystemColorScheme } from '../application/SystemColorScheme';

const DARK_QUERY = '(prefers-color-scheme: dark)';

type MatchMedia = (query: string) => MediaQueryList;

function browserMatchMedia(): MatchMedia | undefined {
  return typeof window.matchMedia === 'function' ? window.matchMedia.bind(window) : undefined;
}

/** Reads `prefers-color-scheme`. Without matchMedia the system counts as light (PD-74). */
export function createMatchMediaSystemColorScheme(
  matchMedia: MatchMedia | undefined = browserMatchMedia(),
): SystemColorScheme {
  return {
    prefersDark() {
      return matchMedia?.(DARK_QUERY).matches ?? false;
    },
    subscribe(onChange) {
      if (matchMedia === undefined) {
        return () => undefined;
      }
      const query = matchMedia(DARK_QUERY);
      const listener = (event: MediaQueryListEvent) => {
        onChange(event.matches);
      };
      query.addEventListener('change', listener);
      return () => {
        query.removeEventListener('change', listener);
      };
    },
  };
}
