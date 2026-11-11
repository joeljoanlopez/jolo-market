import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

interface TotpProofFormProps {
  currentPassword: string;
  code: string;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  done: boolean;
  pending: boolean;
  onCurrentPasswordChange: (password: string) => void;
  onCodeChange: (code: string) => void;
  onSubmit: () => void;
}

/** First step of a TOTP re-enrollment: the password and a code from the current device. */
export function TotpProofForm({
  currentPassword,
  code,
  error,
  done,
  pending,
  onCurrentPasswordChange,
  onCodeChange,
  onSubmit,
}: TotpProofFormProps) {
  const { t } = useTranslation();

  return (
    <form
      className="flex flex-col gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <Text>{t('auth.profile.totp.intro')}</Text>
      {error === null ? null : <Alert>{error}</Alert>}
      <div role="status">{done ? <Text>{t('auth.profile.totp.done')}</Text> : null}</div>
      <FormField
        label={t('auth.profile.totp.current')}
        name="current-password"
        type="password"
        autoComplete="current-password"
        required
        value={currentPassword}
        onChange={(event) => {
          onCurrentPasswordChange(event.target.value);
        }}
      />
      <FormField
        label={t('auth.profile.totp.code')}
        name="code"
        inputMode="numeric"
        autoComplete="one-time-code"
        maxLength={6}
        required
        value={code}
        onChange={(event) => {
          onCodeChange(event.target.value);
        }}
      />
      <Button type="submit" disabled={pending}>
        {t('auth.profile.totp.continue')}
      </Button>
    </form>
  );
}
