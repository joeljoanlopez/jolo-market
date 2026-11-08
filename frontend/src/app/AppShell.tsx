import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router';

import { Text } from '@/shared/ui/atoms/Text/Text';

export function AppShell() {
  const { t } = useTranslation();

  return (
    <div className="min-h-screen bg-background">
      <header className="border-b border-border bg-surface px-4 py-3">
        <Text as="span" size="lg" weight="bold">
          {t('app.name')}
        </Text>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  );
}
