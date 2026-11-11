import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useSearchParams } from 'react-router';

import { useCurrentUser } from '../../application/useCurrentUser';
import { useLogin, type LoginError } from '../../application/useLogin';
import { safeRedirectTarget } from '../../domain/redirectTarget';
import { LoginCodeForm } from '../components/LoginCodeForm';
import { LoginCredentialsForm } from '../components/LoginCredentialsForm';

/** The /login?next= page. Once the session is authenticated it goes to `next`. */
export function LoginPageContainer() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const session = useCurrentUser();
  const login = useLogin();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');

  if (session.status === 'authenticated') {
    return <Navigate to={safeRedirectTarget(searchParams.get('next'))} replace />;
  }

  const errorMessages: Record<LoginError, string> = {
    invalidCredentials: t('auth.login.invalidCredentials'),
    invalidCode: t('auth.login.invalidCode'),
    tooManyAttempts: t('auth.login.tooManyAttempts'),
    failed: t('auth.login.failed'),
  };
  const error = login.error === null ? null : errorMessages[login.error];

  return (
    <section className="mx-auto flex max-w-sm flex-col">
      {login.step === 'credentials' ? (
        <LoginCredentialsForm
          username={username}
          password={password}
          error={error}
          pending={login.pending}
          onUsernameChange={setUsername}
          onPasswordChange={setPassword}
          onSubmit={() => {
            login.submitCredentials({ username, password });
          }}
        />
      ) : (
        <LoginCodeForm
          code={code}
          error={error}
          pending={login.pending}
          onCodeChange={setCode}
          onSubmit={() => {
            login.submitCode(code);
          }}
          onBack={() => {
            setPassword('');
            setCode('');
            login.back();
          }}
        />
      )}
    </section>
  );
}
