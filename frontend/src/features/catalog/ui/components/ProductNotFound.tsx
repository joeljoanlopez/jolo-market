import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Text } from '@/shared/ui/atoms/Text/Text';

/** An unknown or malformed product id: the API answers 404 for both. */
export function ProductNotFound() {
  const { t } = useTranslation();

  return (
    <section className="flex flex-col gap-2">
      <Text as="h1" size="2xl" weight="bold">
        {t('catalog.detail.notFound.title')}
      </Text>
      <Text tone="muted">{t('catalog.detail.notFound.description')}</Text>
      <Link to="/" className="text-primary underline">
        {t('catalog.detail.notFound.backHome')}
      </Link>
    </section>
  );
}
