import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router';

import { SessionMenuContainer } from '@/features/auth/ui/containers/SessionMenuContainer';
import { Text } from '@/shared/ui/atoms/Text/Text';

export function AppShell() {
  const { t } = useTranslation();

  return (
    <div className="min-h-screen bg-background">
      <header className="flex items-center justify-between gap-4 border-b border-border bg-surface px-4 py-3">
        <Text as="span" size="lg" weight="bold">
          {t('app.name')}
        </Text>
        <SessionMenuContainer />
      </header>
      <main className="mx-auto max-w-3xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  );
}
