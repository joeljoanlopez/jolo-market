import { type SubmitEvent, useId } from 'react';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';

interface SearchBoxProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  /** Without it there is no submit button; Enter still never reloads the page. */
  onSubmit?: (value: string) => void;
  submitLabel?: string;
}

/** Controlled search form: a labelled text box and an optional submit button. */
export function SearchBox({ label, value, onChange, onSubmit, submitLabel }: SearchBoxProps) {
  const id = useId();

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit?.(value);
  }

  return (
    <form role="search" onSubmit={handleSubmit} className="flex flex-col gap-1">
      <Label htmlFor={id}>{label}</Label>
      <div className="flex gap-2">
        <Input
          id={id}
          type="search"
          value={value}
          onChange={(event) => {
            onChange(event.target.value);
          }}
        />
        {onSubmit !== undefined && <Button type="submit">{submitLabel}</Button>}
      </div>
    </form>
  );
}
