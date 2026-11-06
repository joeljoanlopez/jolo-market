import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router';

export function AppShell() {
  const { t } = useTranslation();

  return (
    <>
      <header>{t('app.name')}</header>
      <main>
        <Outlet />
      </main>
    </>
  );
}
