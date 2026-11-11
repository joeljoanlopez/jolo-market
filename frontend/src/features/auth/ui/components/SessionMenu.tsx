import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

interface SessionMenuProps {
  username: string;
  /** Only ADMIN users see the admin entry points (PD-15). */
  showAdminLinks: boolean;
  pending: boolean;
  failed: boolean;
  onLogOut: () => void;
}

export function SessionMenu({
  username,
  showAdminLinks,
  pending,
  failed,
  onLogOut,
}: SessionMenuProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-wrap items-center justify-end gap-3">
      {failed ? <Alert>{t('auth.session.logOutFailed')}</Alert> : null}
      <Link to="/profile" className="font-sans text-sm text-primary underline">
        {t('auth.profile.nav')}
      </Link>
      {showAdminLinks ? (
        <nav aria-label={t('auth.session.adminNav')}>
          <Link to="/admin/users" className="font-sans text-sm text-primary underline">
            {t('admin.users.nav')}
          </Link>
        </nav>
      ) : null}
      <Text as="span" size="sm" tone="muted">
        {username}
      </Text>
      <Button variant="secondary" size="sm" disabled={pending} onClick={onLogOut}>
        {t('auth.session.logOut')}
      </Button>
    </div>
  );
}
