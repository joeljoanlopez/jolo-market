import { describe, expect, it } from 'vitest';

import { formatDateTime } from './formatDateTime';

describe('formatDateTime', () => {
  it('writes a date and time the Spanish way, in the given time zone', () => {
    expect(formatDateTime(new Date('2026-11-13T09:05:00Z'), 'Europe/Madrid')).toBe(
      '13/11/2026, 10:05',
    );
  });
});
