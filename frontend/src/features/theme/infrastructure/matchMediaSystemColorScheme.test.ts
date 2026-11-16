import { describe, expect, it } from 'vitest';

import { createMatchMediaSystemColorScheme } from './matchMediaSystemColorScheme';

/** A minimal MediaQueryList whose `matches` the test can flip, firing `change`. */
class FakeMediaQueryList extends EventTarget {
  constructor(
    public matches: boolean,
    public readonly media: string,
  ) {
    super();
  }

  flip(matches: boolean) {
    this.matches = matches;
    this.dispatchEvent(Object.assign(new Event('change'), { matches }));
  }
}

function fakeMatchMedia(dark: boolean) {
  const queries: FakeMediaQueryList[] = [];
  const matchMedia = (query: string) => {
    const list = new FakeMediaQueryList(dark && query === '(prefers-color-scheme: dark)', query);
    queries.push(list);
    return list as unknown as MediaQueryList;
  };
  return { matchMedia, queries };
}

describe('MatchMediaSystemColorScheme', () => {
  it.each([true, false])('reports whether the system prefers dark (%s)', (dark) => {
    const { matchMedia } = fakeMatchMedia(dark);

    expect(createMatchMediaSystemColorScheme(matchMedia).prefersDark()).toBe(dark);
  });

  it('notifies changes until unsubscribed', () => {
    const { matchMedia, queries } = fakeMatchMedia(false);
    const seen: boolean[] = [];
    const unsubscribe = createMatchMediaSystemColorScheme(matchMedia).subscribe((dark) => {
      seen.push(dark);
    });

    queries.forEach((query) => {
      query.flip(true);
    });
    unsubscribe();
    queries.forEach((query) => {
      query.flip(false);
    });

    expect(seen).toEqual([true]);
  });

  it('reports light and never notifies when the browser has no matchMedia (PD-74)', () => {
    const scheme = createMatchMediaSystemColorScheme(undefined);

    expect(scheme.prefersDark()).toBe(false);
    expect(() => {
      scheme.subscribe(() => undefined)();
    }).not.toThrow();
  });
});
