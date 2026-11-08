import { cva, type VariantProps } from 'class-variance-authority';
import type { ReactNode } from 'react';

import { cn } from '@/shared/ui/lib/cn';

const textVariants = cva('font-sans', {
  variants: {
    size: {
      xs: 'text-xs',
      sm: 'text-sm',
      base: 'text-base',
      lg: 'text-lg',
      xl: 'text-xl',
      '2xl': 'text-2xl',
      '3xl': 'text-3xl',
    },
    tone: {
      default: 'text-foreground',
      muted: 'text-muted',
    },
    weight: {
      normal: 'font-normal',
      medium: 'font-medium',
      bold: 'font-bold',
    },
  },
  defaultVariants: { size: 'base', tone: 'default', weight: 'normal' },
});

type TextElement = 'p' | 'span' | 'h1' | 'h2' | 'h3';

type TextProps = VariantProps<typeof textVariants> & {
  as?: TextElement;
  className?: string;
  children: ReactNode;
};

export function Text({ as: Element = 'p', size, tone, weight, className, children }: TextProps) {
  return (
    <Element className={cn(textVariants({ size, tone, weight }), className)}>{children}</Element>
  );
}
