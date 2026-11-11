import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useParams } from 'react-router';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { useActivation, type ActivationError } from '../../application/useActivation';
import { NewPasswordForm } from '../components/NewPasswordForm';
import { TotpEnrollmentForm } from '../components/TotpEnrollmentForm';

/** The /activate/:token page: set a password, enroll a TOTP device, then go to the app. */
export function ActivationPageContainer() {
  const { t } = useTranslation();
  const { token = '' } = useParams();
  const activation = useActivation(token);
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');

  const errorMessages: Record<ActivationError, string> = {
    passwordInvalid: t('auth.activation.passwordInvalid'),
    codeInvalid: t('auth.activation.codeInvalid'),
    tooManyAttempts: t('auth.activation.tooManyAttempts'),
    failed: t('auth.activation.failed'),
  };

  switch (activation.status) {
    case 'checking':
      return (
        <div role="status">
          <Text tone="muted">{t('auth.activation.checking')}</Text>
        </div>
      );
    case 'checkFailed':
      return (
        <section className="mx-auto flex max-w-sm flex-col items-start gap-3">
          <Text>{t('auth.activation.checkFailed')}</Text>
          <Button variant="secondary" onClick={activation.retry}>
            {t('auth.activation.retry')}
          </Button>
        </section>
      );
    case 'activated':
      return <Navigate to="/" replace />;
    case 'invalid':
      return (
        <section className="mx-auto flex max-w-sm flex-col gap-2">
          <Text as="h1" size="2xl" weight="bold">
            {t('auth.activation.invalidTitle')}
          </Text>
          <Text tone="muted">{t('auth.activation.invalid')}</Text>
        </section>
      );
    case 'password':
      return (
        <section className="mx-auto flex max-w-sm flex-col gap-4">
          <Text as="h1" size="2xl" weight="bold">
            {t('auth.activation.title')}
          </Text>
          <Text>{t('auth.activation.welcome', { username: activation.username })}</Text>
          <NewPasswordForm
            password={password}
            error={activation.error === null ? null : errorMessages[activation.error]}
            pending={activation.pending}
            submitLabel={t('auth.activation.continue')}
            onPasswordChange={setPassword}
            onSubmit={() => {
              activation.submitPassword(password);
            }}
          />
        </section>
      );
    case 'totp':
      return (
        <section className="mx-auto flex max-w-sm flex-col gap-4">
          <Text as="h1" size="2xl" weight="bold">
            {t('auth.activation.totpTitle')}
          </Text>
          <TotpEnrollmentForm
            enrollment={activation.enrollment}
            code={code}
            error={activation.error === null ? null : errorMessages[activation.error]}
            pending={activation.pending}
            submitLabel={t('auth.activation.activate')}
            onCodeChange={setCode}
            onSubmit={() => {
              activation.submitCode(code);
            }}
          />
        </section>
      );
  }
}
