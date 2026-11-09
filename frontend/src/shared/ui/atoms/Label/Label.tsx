import type { ComponentProps } from 'react';

import { cn } from '@/shared/ui/lib/cn';

export function Label({ className, ...props }: ComponentProps<'label'>) {
  return (
    <label className={cn('font-sans text-sm font-medium text-foreground', className)} {...props} />
  );
}
