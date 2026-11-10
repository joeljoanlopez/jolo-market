import { describe, expect, it } from 'vitest';

import { safeRedirectTarget } from './redirectTarget';

describe('safeRedirectTarget', () => {
  it.each([
    ['/search?q=leche', '/search?q=leche'],
    ['/join/s3cr3t-token', '/join/s3cr3t-token'],
    ['/', '/'],
  ])('keeps the in-app path %s', (next, expected) => {
    expect(safeRedirectTarget(next)).toBe(expected);
  });

  it.each([
    ['missing', null],
    ['empty', ''],
    ['another site', 'https://evil.example/'],
    ['a protocol-relative URL', '//evil.example/'],
    ['a backslash trick', '/\\evil.example/'],
    ['a relative path', 'search'],
    ['a script URL', 'javascript:alert(1)'],
    ['the login page itself', '/login?next=%2Fsearch'],
  ])('falls back to the home page for %s', (_case, next) => {
    expect(safeRedirectTarget(next)).toBe('/');
  });
});
