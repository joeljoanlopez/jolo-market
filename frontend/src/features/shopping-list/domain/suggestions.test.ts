import { describe, expect, it } from 'vitest';

import {
  MAX_PRODUCT_SUGGESTIONS,
  NO_HIGHLIGHT,
  highlightAfter,
  suggestionOptions,
} from './suggestions';

const products = (count: number) =>
  Array.from({ length: count }, (_, index) => `p${String(index)}`);

describe('suggestionOptions', () => {
  it('puts the text option first, then the products in the order given (PD-80)', () => {
    expect(suggestionOptions(['milk', 'eggs'])).toEqual([
      { kind: 'text' },
      { kind: 'product', product: 'milk' },
      { kind: 'product', product: 'eggs' },
    ]);
  });

  it('keeps only the text option when there are no products (PD-80)', () => {
    expect(suggestionOptions([])).toEqual([{ kind: 'text' }]);
  });

  it('suggests at most 10 products (PD-80)', () => {
    const options = suggestionOptions(products(25));

    expect(MAX_PRODUCT_SUGGESTIONS).toBe(10);
    expect(options).toHaveLength(1 + MAX_PRODUCT_SUGGESTIONS);
    expect(options.at(-1)).toEqual({ kind: 'product', product: 'p9' });
  });
});

describe('highlightAfter', () => {
  it('highlights the first option on ArrowDown when nothing is highlighted (PD-81)', () => {
    expect(highlightAfter(NO_HIGHLIGHT, { type: 'next', optionCount: 3 })).toBe(0);
  });

  it('highlights the last option on ArrowUp when nothing is highlighted', () => {
    expect(highlightAfter(NO_HIGHLIGHT, { type: 'previous', optionCount: 3 })).toBe(2);
  });

  it('moves down and up one option at a time', () => {
    expect(highlightAfter(0, { type: 'next', optionCount: 3 })).toBe(1);
    expect(highlightAfter(2, { type: 'previous', optionCount: 3 })).toBe(1);
  });

  it('wraps around at both ends', () => {
    expect(highlightAfter(2, { type: 'next', optionCount: 3 })).toBe(0);
    expect(highlightAfter(0, { type: 'previous', optionCount: 3 })).toBe(2);
  });

  it('highlights nothing when there are no options', () => {
    expect(highlightAfter(NO_HIGHLIGHT, { type: 'next', optionCount: 0 })).toBe(NO_HIGHLIGHT);
  });

  it('clears the highlight when the text changes (PD-81)', () => {
    expect(highlightAfter(1, { type: 'textChanged' })).toBe(NO_HIGHLIGHT);
  });

  it('clears the highlight when the options shrink below it', () => {
    expect(highlightAfter(5, { type: 'next', optionCount: 3 })).toBe(0);
  });
});
