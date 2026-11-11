import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Text } from '@/shared/ui/atoms/Text/Text';

/** A list that is gone or out of reach: the API never says which (PD-44). */
export function ListNotFound() {
  const { t } = useTranslation();

  return (
    <section className="flex flex-col gap-2">
      <Text as="h1" size="2xl" weight="bold">
        {t('lists.notFound.title')}
      </Text>
      <Text tone="muted">{t('lists.notFound.description')}</Text>
      <Link to="/" className="text-primary underline">
        {t('lists.notFound.backHome')}
      </Link>
    </section>
  );
}
