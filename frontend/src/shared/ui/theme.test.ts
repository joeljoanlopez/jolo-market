import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

const css = readFileSync(resolve(import.meta.dirname, '../../index.css'), 'utf8');

// Supermarket ids from docs/architecture.md (Catalog domain model).
const supermarkets = ['mercadona', 'bonpreu', 'aldi'];

describe('theme tokens', () => {
  it.each(supermarkets)('defines a color and a readable foreground for %s', (supermarket) => {
    expect(css).toContain(`--color-${supermarket}:`);
    expect(css).toContain(`--color-${supermarket}-foreground:`);
  });

  it('replaces the default Tailwind palette, so only theme colors exist', () => {
    expect(css).toContain('--color-*: initial;');
  });

  it.each(['--text-*: initial;', '--radius-*: initial;', '--font-*: initial;'])(
    'replaces the default scale %s',
    (reset) => {
      expect(css).toContain(reset);
    },
  );
});
