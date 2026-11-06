import { cva } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/shared/ui/lib/cn';

const inputVariants = cva(
  'h-10 w-full rounded-md border bg-background px-3 font-sans text-base text-foreground placeholder:text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-50',
  {
    variants: {
      invalid: {
        true: 'border-danger',
        false: 'border-border',
      },
    },
    defaultVariants: { invalid: false },
  },
);

type InputProps = ComponentProps<'input'> & {
  /** Marks the field as invalid both visually and for assistive technology. */
  invalid?: boolean;
};

export function Input({ invalid = false, className, ...props }: InputProps) {
  return (
    <input
      aria-invalid={invalid ? true : undefined}
      className={cn(inputVariants({ invalid }), className)}
      {...props}
    />
  );
}
