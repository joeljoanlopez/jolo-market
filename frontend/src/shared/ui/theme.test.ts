import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { contrastRatio } from './contrast';

const css = readFileSync(resolve(import.meta.dirname, '../../index.css'), 'utf8');

// Supermarket ids from docs/architecture.md (Catalog domain model).
const supermarkets = ['mercadona', 'bonpreu', 'aldi'];

/** WCAG AA for normal text (PD-77). */
const AA = 4.5;

/** Every text/background pair the UI uses (PD-77). */
const pairs: [text: string, background: string][] = [
  ['foreground', 'background'],
  ['foreground', 'surface'],
  ['muted', 'background'],
  ['muted', 'surface'],
  ['primary', 'background'],
  ['primary', 'surface'],
  ['danger', 'background'],
  ['danger', 'surface'],
  ['primary-foreground', 'primary'],
  ['primary-foreground', 'primary-hover'],
  ['danger-foreground', 'danger'],
  ['danger-foreground', 'danger-hover'],
  ...supermarkets.map((s): [string, string] => [`${s}-foreground`, s]),
];

/** Tokens that keep their light value in the dark theme: the QR code stays dark on light (PD-78). */
const FIXED_TOKENS = ['qr', 'qr-background'];

/** The selector that redefines the color tokens for the dark theme. */
const DARK_SELECTOR = ":root[data-theme='dark']";

/** The body of the first CSS block that starts with `selector`. */
function block(selector: string): string {
  const start = css.indexOf(`${selector} {`);
  if (start === -1) {
    throw new Error(`No "${selector}" block in index.css.`);
  }
  return css.slice(start, css.indexOf('}', start));
}

/** Reads the `--color-*` declarations of the first CSS block that starts with `selector`. */
function colorTokens(selector: string): Map<string, string> {
  const tokens = new Map<string, string>();
  for (const match of block(selector).matchAll(/--color-([a-z-]+):\s*(#[0-9a-f]{6});/g)) {
    tokens.set(match[1] ?? '', match[2] ?? '');
  }
  return tokens;
}

function color(tokens: Map<string, string>, name: string): string {
  const value = tokens.get(name);
  if (value === undefined) {
    throw new Error(`Missing token --color-${name}.`);
  }
  return value;
}

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

  describe('light theme', () => {
    const light = colorTokens('@theme');

    it.each(pairs)('%s on %s reaches WCAG AA contrast', (text, background) => {
      expect(contrastRatio(color(light, text), color(light, background))).toBeGreaterThanOrEqual(
        AA,
      );
    });
  });

  describe('dark theme (PD-77)', () => {
    const light = colorTokens('@theme');
    const dark = colorTokens(DARK_SELECTOR);

    it('gives every color token but the fixed ones a dark value', () => {
      const themed = [...light.keys()].filter((name) => !FIXED_TOKENS.includes(name));
      expect([...dark.keys()].sort()).toEqual(themed.sort());
    });

    it('keeps the QR code dark on light (PD-78)', () => {
      expect(
        contrastRatio(color(light, 'qr'), color(light, 'qr-background')),
      ).toBeGreaterThanOrEqual(AA);
      // The darker color is the one further from white.
      expect(contrastRatio(color(light, 'qr'), '#ffffff')).toBeGreaterThan(
        contrastRatio(color(light, 'qr-background'), '#ffffff'),
      );
    });

    it('tells the browser to draw native controls dark', () => {
      expect(block(DARK_SELECTOR)).toContain('color-scheme: dark;');
    });

    it.each(pairs)('%s on %s reaches WCAG AA contrast', (text, background) => {
      expect(contrastRatio(color(dark, text), color(dark, background))).toBeGreaterThanOrEqual(AA);
    });
  });
});
