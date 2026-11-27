import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/shared/ui/lib/cn';

const selectVariants = cva(
  'rounded-md border border-border bg-background font-sans text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-50',
  {
    variants: {
      size: {
        md: 'h-10 w-full px-3 text-base',
        /** Inside a row, next to other content (PD-116). */
        sm: 'h-8 w-auto px-2 text-sm',
      },
    },
    defaultVariants: { size: 'md' },
  },
);

type SelectProps = Omit<ComponentProps<'select'>, 'size'> & VariantProps<typeof selectVariants>;

/** Native select with the same look as Input. */
export function Select({ className, size, ...props }: SelectProps) {
  return <select className={cn(selectVariants({ size }), className)} {...props} />;
}
