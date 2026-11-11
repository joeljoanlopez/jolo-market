import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Text } from '@/shared/ui/atoms/Text/Text';

import type { ListSummary } from '../../domain/shoppingList';

interface MyListsListProps {
  lists: ListSummary[];
}

/** "My lists": owned and shared with me, each with my role and how many items it has. */
export function MyListsList({ lists }: MyListsListProps) {
  const { t } = useTranslation();

  if (lists.length === 0) {
    return <Text tone="muted">{t('lists.mine.empty')}</Text>;
  }

  return (
    <ul aria-label={t('lists.mine.title')} className="flex flex-col gap-2">
      {lists.map((list) => (
        <li
          key={list.id}
          className="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border border-border bg-background p-3"
        >
          <Link to={`/lists/${list.id}`} className="font-medium text-primary underline">
            {list.name}
          </Link>
          <Text as="span" size="sm" tone="muted">
            {list.role === 'owner'
              ? t('lists.mine.owned')
              : t('lists.mine.sharedBy', { username: list.ownerUsername })}
            {' · '}
            {t('lists.mine.itemCount', { count: list.itemCount })}
          </Text>
        </li>
      ))}
    </ul>
  );
}
