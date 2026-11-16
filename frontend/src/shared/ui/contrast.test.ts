import { describe, expect, it } from 'vitest';

import { contrastRatio } from './contrast';

describe('contrastRatio', () => {
  it('is 21 for black on white', () => {
    expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21, 5);
  });

  it('is 1 for a color on itself', () => {
    expect(contrastRatio('#1d6b5a', '#1d6b5a')).toBeCloseTo(1, 5);
  });

  it('does not depend on the order of the colors', () => {
    expect(contrastRatio('#ffffff', '#767676')).toBeCloseTo(contrastRatio('#767676', '#ffffff'));
  });

  it('matches the WCAG reference value for #767676 on white', () => {
    expect(contrastRatio('#767676', '#ffffff')).toBeCloseTo(4.54, 2);
  });
});
