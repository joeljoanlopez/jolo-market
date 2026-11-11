import { describe, expect, it } from 'vitest';

import { meetsPasswordPolicy } from './passwordPolicy';

describe('meetsPasswordPolicy (PD-37, same rules as the API)', () => {
  it.each([
    ['all four kinds and 12 characters', 'Abcdefghij1!'],
    ['accented letters, which count as letters', 'Contraseña-Ñ1'],
    ['a space as the special character', 'Correct horse 1'],
    ['128 characters', `Aa1!${'x'.repeat(124)}`],
  ])('accepts %s', (_case, password) => {
    expect(meetsPasswordPolicy(password)).toBe(true);
  });

  it.each([
    ['11 characters', 'Abcdefghi1!'],
    ['129 characters', `Aa1!${'x'.repeat(125)}`],
    ['no uppercase letter', 'abcdefghij1!'],
    ['no lowercase letter', 'ABCDEFGHIJ1!'],
    ['no digit', 'Abcdefghijk!'],
    ['no special character', 'Abcdefghijk1'],
  ])('rejects %s', (_case, password) => {
    expect(meetsPasswordPolicy(password)).toBe(false);
  });

  it('counts characters, not bytes', () => {
    // 12 characters, more than 12 bytes in UTF-8.
    expect(meetsPasswordPolicy('Ññññññññ1!aA')).toBe(true);
  });
});
