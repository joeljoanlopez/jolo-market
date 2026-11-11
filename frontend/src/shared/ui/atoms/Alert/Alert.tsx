import type { ReactNode } from 'react';

interface AlertProps {
  children: ReactNode;
}

/** An error message that screen readers announce as soon as it appears. */
export function Alert({ children }: AlertProps) {
  return (
    <div
      role="alert"
      className="rounded-md border border-danger bg-surface px-3 py-2 font-sans text-sm text-danger"
    >
      {children}
    </div>
  );
}
