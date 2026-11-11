import { useId, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

import {
  useChangePassword,
  useTotpReEnrollment,
  type ChangePasswordError,
  type TotpReEnrollmentError,
} from '../../application/useProfile';
import { ChangePasswordForm, type PasswordChangeFields } from '../components/ChangePasswordForm';
import { TotpEnrollmentForm } from '../components/TotpEnrollmentForm';
import { TotpProofForm } from '../components/TotpProofForm';

/** The /profile page (PD-38): change the password and re-enroll TOTP. */
export function ProfilePageContainer() {
  const { t } = useTranslation();

  return (
    <section className="mx-auto flex max-w-sm flex-col gap-8">
      <Text as="h1" size="2xl" weight="bold">
        {t('auth.profile.title')}
      </Text>
      <ProfileSection title={t('auth.profile.password.title')}>
        <ChangePasswordContainer />
      </ProfileSection>
      <ProfileSection title={t('auth.profile.totp.title')}>
        <TotpReEnrollmentContainer />
      </ProfileSection>
    </section>
  );
}

function ProfileSection({ title, children }: { title: string; children: ReactNode }) {
  const titleId = useId();
  return (
    <section aria-labelledby={titleId} className="flex flex-col gap-4">
      <span id={titleId}>
        <Text as="h2" size="xl" weight="bold">
          {title}
        </Text>
      </span>
      {children}
    </section>
  );
}

const emptyPasswordChange: PasswordChangeFields = {
  currentPassword: '',
  code: '',
  newPassword: '',
};

function ChangePasswordContainer() {
  const { t } = useTranslation();
  const [fields, setFields] = useState(emptyPasswordChange);
  const change = useChangePassword(() => {
    setFields(emptyPasswordChange);
  });

  const errors: Record<ChangePasswordError, string> = {
    weak: t('auth.profile.password.weak'),
    rejected: t('auth.profile.password.rejected'),
    tooManyAttempts: t('auth.profile.tooManyAttempts'),
    failed: t('auth.profile.failed'),
  };

  return (
    <ChangePasswordForm
      fields={fields}
      error={change.error === null ? null : errors[change.error]}
      done={change.done}
      pending={change.pending}
      onChange={setFields}
      onSubmit={() => {
        change.submit(fields);
      }}
    />
  );
}

function TotpReEnrollmentContainer() {
  const { t } = useTranslation();
  const reEnrollment = useTotpReEnrollment();
  const [currentPassword, setCurrentPassword] = useState('');
  const [code, setCode] = useState('');
  const [newDeviceCode, setNewDeviceCode] = useState('');

  const errors: Record<TotpReEnrollmentError, string> = {
    rejected: t('auth.profile.totp.rejected'),
    codeInvalid: t('auth.profile.totp.codeInvalid'),
    expired: t('auth.profile.totp.expired'),
    tooManyAttempts: t('auth.profile.tooManyAttempts'),
    failed: t('auth.profile.failed'),
  };
  const error = reEnrollment.error === null ? null : errors[reEnrollment.error];

  if (reEnrollment.step === 'confirm') {
    return (
      <TotpEnrollmentForm
        enrollment={reEnrollment.enrollment}
        code={newDeviceCode}
        error={error}
        pending={reEnrollment.pending}
        submitLabel={t('auth.profile.totp.submit')}
        onCodeChange={setNewDeviceCode}
        onSubmit={() => {
          reEnrollment.confirm(newDeviceCode);
        }}
        cancel={{
          label: t('auth.profile.totp.cancel'),
          onCancel: () => {
            setNewDeviceCode('');
            reEnrollment.cancel();
          },
        }}
      />
    );
  }

  return (
    <TotpProofForm
      currentPassword={currentPassword}
      code={code}
      error={error}
      done={reEnrollment.done}
      pending={reEnrollment.pending}
      onCurrentPasswordChange={setCurrentPassword}
      onCodeChange={setCode}
      onSubmit={() => {
        reEnrollment.start({ currentPassword, code });
        setCurrentPassword('');
        setCode('');
        setNewDeviceCode('');
      }}
    />
  );
}
