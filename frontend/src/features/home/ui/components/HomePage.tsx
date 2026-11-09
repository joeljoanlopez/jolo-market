import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Text } from '@/shared/ui/atoms/Text/Text';
import { SearchBox } from '@/shared/ui/molecules/SearchBox/SearchBox';

export function HomePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [text, setText] = useState('');

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-col gap-2">
        <Text as="h1" size="2xl" weight="bold">
          {t('home.title')}
        </Text>
        <Text tone="muted">{t('home.description')}</Text>
      </div>
      <SearchBox
        label={t('home.searchLabel')}
        submitLabel={t('home.searchSubmit')}
        value={text}
        onChange={setText}
        onSubmit={(value) => {
          const q = value.trim();
          void navigate({
            pathname: '/search',
            search: q === '' ? '' : `?${new URLSearchParams({ q }).toString()}`,
          });
        }}
      />
    </section>
  );
}
