import { describe, expect, it } from 'vitest';

import { productPath } from './productPath';

describe('productPath', () => {
  it('is the product page of the id', () => {
    expect(productPath('0190f3c2-0000-7000-8000-000000000001')).toBe(
      '/product/0190f3c2-0000-7000-8000-000000000001',
    );
  });

  it('encodes the id', () => {
    expect(productPath('a/b')).toBe('/product/a%2Fb');
  });
});
