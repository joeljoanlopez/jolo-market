import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

interface SetupFormProps {
  code: string;
  username: string;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  pending: boolean;
  onCodeChange: (code: string) => void;
  onUsernameChange: (username: string) => void;
  onSubmit: () => void;
}

export function SetupForm({
  code,
  username,
  error,
  pending,
  onCodeChange,
  onUsernameChange,
  onSubmit,
}: SetupFormProps) {
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
        {t('auth.setup.title')}
      </Text>
      <Text>{t('auth.setup.intro')}</Text>
      {error === null ? null : <Alert>{error}</Alert>}
      <FormField
        label={t('auth.setup.code')}
        hint={t('auth.setup.codeHint')}
        name="setup-code"
        autoComplete="off"
        autoCapitalize="none"
        spellCheck={false}
        required
        value={code}
        onChange={(event) => {
          onCodeChange(event.target.value);
        }}
      />
      <FormField
        label={t('auth.setup.username')}
        hint={t('auth.setup.usernameHint')}
        name="username"
        autoComplete="username"
        autoCapitalize="none"
        spellCheck={false}
        required
        value={username}
        onChange={(event) => {
          onUsernameChange(event.target.value);
        }}
      />
      <Button type="submit" disabled={pending}>
        {t('auth.setup.submit')}
      </Button>
    </form>
  );
}
