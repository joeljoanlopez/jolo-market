import { cva } from 'class-variance-authority';

import type { Supermarket } from '../../domain/product';

const badgeVariants = cva('inline-flex rounded-full px-2 font-sans text-xs font-medium', {
  variants: {
    supermarket: {
      mercadona: 'bg-mercadona text-mercadona-foreground',
      bonpreu: 'bg-bonpreu text-bonpreu-foreground',
      aldi: 'bg-aldi text-aldi-foreground',
      other: 'border border-border bg-surface text-foreground',
    },
  },
});

type BadgeColor = 'mercadona' | 'bonpreu' | 'aldi' | 'other';

// Supermarkets with their own theme colors (PD-20); any other one gets neutral colors.
function colorOf(id: string): BadgeColor {
  return id === 'mercadona' || id === 'bonpreu' || id === 'aldi' ? id : 'other';
}

export function SupermarketBadge({ supermarket }: { supermarket: Supermarket }) {
  return (
    <span className={badgeVariants({ supermarket: colorOf(supermarket.id) })}>
      {supermarket.name}
    </span>
  );
}
