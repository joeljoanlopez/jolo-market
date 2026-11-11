import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate } from 'react-router';

import { Text } from '@/shared/ui/atoms/Text/Text';

import { useCompleteSetup, useSetupStatus, type SetupError } from '../../application/useSetup';
import { SetupForm } from '../components/SetupForm';

/** The /setup page of a fresh instance: creates the first admin, then opens their activation. */
export function SetupPageContainer() {
  const { t } = useTranslation();
  const status = useSetupStatus();
  const setup = useCompleteSetup();
  const [code, setCode] = useState('');
  const [username, setUsername] = useState('');

  if (setup.activationToken !== null) {
    return <Navigate to={`/activate/${encodeURIComponent(setup.activationToken)}`} replace />;
  }
  if (status === 'checking') {
    return (
      <div role="status">
        <Text tone="muted">{t('app.loading')}</Text>
      </div>
    );
  }
  if (status === 'done') {
    return <Navigate to="/" replace />;
  }

  const errorMessages: Record<SetupError, string> = {
    invalid: t('auth.setup.invalid'),
    tooManyAttempts: t('auth.setup.tooManyAttempts'),
    failed: t('auth.setup.failed'),
  };

  return (
    <section className="mx-auto flex max-w-sm flex-col">
      <SetupForm
        code={code}
        username={username}
        error={setup.error === null ? null : errorMessages[setup.error]}
        pending={setup.pending}
        onCodeChange={setCode}
        onUsernameChange={setUsername}
        onSubmit={() => {
          setup.submit(code, username);
        }}
      />
    </section>
  );
}
