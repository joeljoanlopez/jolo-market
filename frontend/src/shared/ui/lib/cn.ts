import { extendTailwindMerge, type ClassNameValue } from 'tailwind-merge';

// tailwind-merge must know the custom tokens of src/index.css to resolve conflicts,
// for example that "text-muted" is a color and "text-sm" a font size.
const twMerge = extendTailwindMerge({
  extend: {
    theme: {
      color: [
        'background',
        'surface',
        'border',
        'foreground',
        'muted',
        'primary',
        'primary-hover',
        'primary-foreground',
        'danger',
        'danger-hover',
        'danger-foreground',
        'focus',
        'mercadona',
        'mercadona-foreground',
        'bonpreu',
        'bonpreu-foreground',
        'aldi',
        'aldi-foreground',
      ],
      text: ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl'],
      radius: ['sm', 'md', 'lg', 'full'],
      font: ['sans'],
    },
  },
});

/** Joins class names, skipping falsy values, and lets the last conflicting utility win. */
export function cn(...inputs: ClassNameValue[]): string {
  return twMerge(inputs);
}
