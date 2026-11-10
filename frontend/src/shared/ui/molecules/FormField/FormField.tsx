import { useId, type ComponentProps } from 'react';

import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Text } from '@/shared/ui/atoms/Text/Text';

type FormFieldProps = Omit<ComponentProps<'input'>, 'id' | 'aria-describedby' | 'aria-invalid'> & {
  label: string;
  name: string;
  /** Help shown under the field (format, where to find a code...). */
  hint?: string;
  /** Shown under the hint; it also marks the input invalid. */
  error?: string;
};

/** A labelled input with an optional hint and error, both read by screen readers. */
export function FormField({ label, hint, error, ...inputProps }: FormFieldProps) {
  const id = useId();
  const hintId = `${id}-hint`;
  const errorId = `${id}-error`;
  const describedBy = [hint === undefined ? null : hintId, error === undefined ? null : errorId]
    .filter((part) => part !== null)
    .join(' ');

  return (
    <div className="flex flex-col gap-1">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        invalid={error !== undefined}
        aria-describedby={describedBy === '' ? undefined : describedBy}
        {...inputProps}
      />
      {hint === undefined ? null : (
        <span id={hintId}>
          <Text as="span" size="sm" tone="muted">
            {hint}
          </Text>
        </span>
      )}
      {error === undefined ? null : (
        <span id={errorId}>
          <Text as="span" size="sm" tone="danger">
            {error}
          </Text>
        </span>
      )}
    </div>
  );
}
