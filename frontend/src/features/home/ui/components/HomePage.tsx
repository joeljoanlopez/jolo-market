import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

export function HomePage() {
  const { t } = useTranslation();

  return (
    <section className="flex flex-col gap-2">
      <Text as="h1" size="2xl" weight="bold">
        {t('home.title')}
      </Text>
      <Text tone="muted">{t('home.description')}</Text>
    </section>
  );
}
