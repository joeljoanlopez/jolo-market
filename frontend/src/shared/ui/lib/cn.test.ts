import { describe, expect, it } from 'vitest';

import { cn } from './cn';

describe('cn', () => {
  it('joins class names and skips falsy values', () => {
    expect(cn('p-4', false, undefined, null, '', 'rounded-md')).toBe('p-4 rounded-md');
  });

  it('lets the last conflicting utility win', () => {
    expect(cn('px-2 py-1', 'px-4')).toBe('py-1 px-4');
  });

  it('treats theme color tokens as colors', () => {
    expect(cn('bg-primary text-primary-foreground', 'bg-danger')).toBe(
      'text-primary-foreground bg-danger',
    );
  });

  it('keeps a theme font size and a theme text color together', () => {
    expect(cn('text-sm', 'text-muted')).toBe('text-sm text-muted');
  });

  it('treats theme font sizes as font sizes', () => {
    expect(cn('text-sm', 'text-lg')).toBe('text-lg');
  });
});
