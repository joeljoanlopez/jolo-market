import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

interface NewPasswordFormProps {
  password: string;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  pending: boolean;
  submitLabel: string;
  onPasswordChange: (password: string) => void;
  onSubmit: () => void;
}

/** Asks for a new password and explains the policy (PD-37). */
export function NewPasswordForm({
  password,
  error,
  pending,
  submitLabel,
  onPasswordChange,
  onSubmit,
}: NewPasswordFormProps) {
  const { t } = useTranslation();

  return (
    <form
      className="flex flex-col gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      {error === null ? null : <Alert>{error}</Alert>}
      <FormField
        label={t('auth.activation.password')}
        hint={t('auth.activation.passwordHint')}
        name="new-password"
        type="password"
        autoComplete="new-password"
        required
        value={password}
        onChange={(event) => {
          onPasswordChange(event.target.value);
        }}
      />
      <Button type="submit" disabled={pending}>
        {submitLabel}
      </Button>
    </form>
  );
}
