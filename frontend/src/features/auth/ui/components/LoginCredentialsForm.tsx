import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

interface LoginCredentialsFormProps {
  username: string;
  password: string;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  pending: boolean;
  onUsernameChange: (username: string) => void;
  onPasswordChange: (password: string) => void;
  onSubmit: () => void;
}

export function LoginCredentialsForm({
  username,
  password,
  error,
  pending,
  onUsernameChange,
  onPasswordChange,
  onSubmit,
}: LoginCredentialsFormProps) {
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
        {t('auth.login.title')}
      </Text>
      {error === null ? null : <Alert>{error}</Alert>}
      <FormField
        label={t('auth.login.username')}
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
      <FormField
        label={t('auth.login.password')}
        name="password"
        type="password"
        autoComplete="current-password"
        required
        value={password}
        onChange={(event) => {
          onPasswordChange(event.target.value);
        }}
      />
      <Button type="submit" disabled={pending}>
        {t('auth.login.continue')}
      </Button>
    </form>
  );
}
