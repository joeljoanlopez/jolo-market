import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Text } from '@/shared/ui/atoms/Text/Text';

export function NotFoundPage() {
  const { t } = useTranslation();

  return (
    <section className="flex flex-col gap-2">
      <Text as="h1" size="2xl" weight="bold">
        {t('notFound.title')}
      </Text>
      <Text tone="muted">{t('notFound.description')}</Text>
      <Link to="/" className="text-primary underline">
        {t('notFound.backHome')}
      </Link>
    </section>
  );
}
