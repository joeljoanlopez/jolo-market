import { describe, expect, it } from 'vitest';

import { parseThemePreference, resolveTheme } from './theme';

describe('parseThemePreference', () => {
  it.each(['system', 'light', 'dark'] as const)('reads a stored "%s"', (value) => {
    expect(parseThemePreference(value)).toBe(value);
  });

  it.each([null, '', 'Dark', 'blue'])('treats %j as system (PD-75)', (value) => {
    expect(parseThemePreference(value)).toBe('system');
  });
});

describe('resolveTheme', () => {
  it.each([true, false])(
    'uses light when the user chose light, whatever the system (%s)',
    (dark) => {
      expect(resolveTheme('light', dark)).toBe('light');
    },
  );

  it.each([true, false])('uses dark when the user chose dark, whatever the system (%s)', (dark) => {
    expect(resolveTheme('dark', dark)).toBe('dark');
  });

  it('follows a dark system when the user chose system (PD-74)', () => {
    expect(resolveTheme('system', true)).toBe('dark');
  });

  it('uses light when the system does not prefer dark (PD-74)', () => {
    expect(resolveTheme('system', false)).toBe('light');
  });
});
