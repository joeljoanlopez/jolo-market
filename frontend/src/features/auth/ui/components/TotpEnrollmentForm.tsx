import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { QrCode } from '@/shared/ui/atoms/QrCode/QrCode';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

import type { TotpEnrollment } from '../../domain/activation';

interface TotpEnrollmentFormProps {
  enrollment: TotpEnrollment;
  code: string;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  pending: boolean;
  submitLabel: string;
  onCodeChange: (code: string) => void;
  onSubmit: () => void;
  /** Shown as a secondary button when given (re-enrollment can be abandoned). */
  cancel?: { label: string; onCancel: () => void };
}

/** Shows a new TOTP device (QR code and manual secret) and asks for a code from it. */
export function TotpEnrollmentForm({
  enrollment,
  code,
  error,
  pending,
  submitLabel,
  onCodeChange,
  onSubmit,
  cancel,
}: TotpEnrollmentFormProps) {
  const { t } = useTranslation();

  return (
    <form
      className="flex flex-col gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <Text>{t('auth.activation.totpIntro')}</Text>
      <QrCode value={enrollment.provisioningUri} label={t('auth.activation.qrLabel')} />
      <div className="flex flex-col gap-1">
        <Text size="sm" tone="muted">
          {t('auth.activation.manualSecret')}
        </Text>
        <code className="font-sans text-base break-all text-foreground">{enrollment.secret}</code>
      </div>
      {error === null ? null : <Alert>{error}</Alert>}
      <FormField
        label={t('auth.activation.code')}
        hint={t('auth.activation.codeHint')}
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
      <div className="flex gap-2">
        <Button type="submit" disabled={pending}>
          {submitLabel}
        </Button>
        {cancel === undefined ? null : (
          <Button variant="secondary" onClick={cancel.onCancel}>
            {cancel.label}
          </Button>
        )}
      </div>
    </form>
  );
}
