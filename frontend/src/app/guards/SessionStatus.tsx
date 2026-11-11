import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

interface SessionStatusProps {
  onRetry?: () => void;
}

/** What a guard shows while the session loads, or when it could not be checked. */
export function SessionStatus({ onRetry }: SessionStatusProps) {
  const { t } = useTranslation();

  if (onRetry === undefined) {
    return (
      <div role="status">
        <Text tone="muted">{t('app.loading')}</Text>
      </div>
    );
  }

  return (
    <div role="alert" className="flex flex-col items-start gap-3">
      <Text>{t('auth.session.error')}</Text>
      <Button variant="secondary" onClick={onRetry}>
        {t('auth.session.retry')}
      </Button>
    </div>
  );
}
