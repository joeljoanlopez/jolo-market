import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

interface LoginCodeFormProps {
  code: string;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  pending: boolean;
  onCodeChange: (code: string) => void;
  onSubmit: () => void;
  onBack: () => void;
}

export function LoginCodeForm({
  code,
  error,
  pending,
  onCodeChange,
  onSubmit,
  onBack,
}: LoginCodeFormProps) {
  const { t } = useTranslation();

  return (
    <form
      className="flex flex-col gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <Text as="h1" size="2xl" weight="bold">
        {t('auth.login.codeTitle')}
      </Text>
      {error === null ? null : <Alert>{error}</Alert>}
      <FormField
        label={t('auth.login.code')}
        hint={t('auth.login.codeHint')}
        name="code"
        inputMode="numeric"
        autoComplete="one-time-code"
        maxLength={6}
        required
        autoFocus
        value={code}
        onChange={(event) => {
          onCodeChange(event.target.value);
        }}
      />
      <div className="flex gap-2">
        <Button type="submit" disabled={pending}>
          {t('auth.login.submit')}
        </Button>
        <Button variant="secondary" onClick={onBack}>
          {t('auth.login.back')}
        </Button>
      </div>
    </form>
  );
}
