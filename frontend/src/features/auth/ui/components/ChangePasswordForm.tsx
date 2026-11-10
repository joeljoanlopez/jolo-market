import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

export interface PasswordChangeFields {
  currentPassword: string;
  code: string;
  newPassword: string;
}

interface ChangePasswordFormProps {
  fields: PasswordChangeFields;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  done: boolean;
  pending: boolean;
  onChange: (fields: PasswordChangeFields) => void;
  onSubmit: () => void;
}

export function ChangePasswordForm({
  fields,
  error,
  done,
  pending,
  onChange,
  onSubmit,
}: ChangePasswordFormProps) {
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
      <div role="status">{done ? <Text>{t('auth.profile.password.done')}</Text> : null}</div>
      <FormField
        label={t('auth.profile.password.current')}
        name="current-password"
        type="password"
        autoComplete="current-password"
        required
        value={fields.currentPassword}
        onChange={(event) => {
          onChange({ ...fields, currentPassword: event.target.value });
        }}
      />
      <FormField
        label={t('auth.profile.password.code')}
        name="code"
        inputMode="numeric"
        autoComplete="one-time-code"
        maxLength={6}
        required
        value={fields.code}
        onChange={(event) => {
          onChange({ ...fields, code: event.target.value });
        }}
      />
      <FormField
        label={t('auth.profile.password.new')}
        hint={t('auth.profile.password.newHint')}
        name="new-password"
        type="password"
        autoComplete="new-password"
        required
        value={fields.newPassword}
        onChange={(event) => {
          onChange({ ...fields, newPassword: event.target.value });
        }}
      />
      <Button type="submit" disabled={pending}>
        {t('auth.profile.password.submit')}
      </Button>
    </form>
  );
}
