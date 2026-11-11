import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

type JoinStatusProps =
  { status: 'joining' } | { status: 'invalid' } | { status: 'error'; onRetry: () => void };

/** What `/join/:token` shows until it opens the list. */
export function JoinStatus(props: JoinStatusProps) {
  const { t } = useTranslation();

  switch (props.status) {
    case 'joining':
      return (
        <div role="status">
          <Text tone="muted">{t('lists.join.joining')}</Text>
        </div>
      );
    case 'invalid':
      return (
        <section className="flex flex-col gap-2">
          <Text as="h1" size="2xl" weight="bold">
            {t('lists.join.invalidTitle')}
          </Text>
          <Text tone="muted">{t('lists.join.invalid')}</Text>
          <Link to="/" className="text-primary underline">
            {t('lists.join.backHome')}
          </Link>
        </section>
      );
    case 'error':
      return (
        <div className="flex flex-col items-start gap-3">
          <Text>{t('lists.join.failed')}</Text>
          <Button variant="secondary" onClick={props.onRetry}>
            {t('lists.join.retry')}
          </Button>
        </div>
      );
  }
}
