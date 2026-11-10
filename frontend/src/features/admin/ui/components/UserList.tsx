import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

import type { AdminUser } from '../../domain/adminUser';
import { formatDateTime } from '../../domain/formatDateTime';

interface UserListProps {
  users: AdminUser[];
  /** Actions for each user (E3.28b); the list only places them. */
  renderActions?: (user: AdminUser) => ReactNode;
}

export function UserList({ users, renderActions }: UserListProps) {
  const { t } = useTranslation();

  return (
    <ul aria-label={t('admin.users.list')} className="flex flex-col gap-3">
      {users.map((user) => (
        <li
          key={user.id}
          className="flex flex-col gap-2 rounded-md border border-border bg-surface p-4"
        >
          <Text as="h3" size="lg" weight="bold">
            {user.username}
          </Text>
          <Text size="sm" tone="muted">
            {t(`admin.users.roles.${user.role}`)} · {t(`admin.users.statuses.${user.status}`)}
          </Text>
          {user.activationExpiresAt === null ? null : (
            <Text size="sm" tone="muted">
              {t('admin.users.linkValidUntil', { date: formatDateTime(user.activationExpiresAt) })}
            </Text>
          )}
          {renderActions?.(user)}
        </li>
      ))}
    </ul>
  );
}
