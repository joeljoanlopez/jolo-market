import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import type { ListRole } from '../../domain/shoppingList';

interface ListActionsProps {
  listName: string;
  role: ListRole;
  pending: boolean;
  onDelete: () => void;
  onLeave: () => void;
}

/** The owner deletes the list (asked first); a member leaves it (PD-44). */
export function ListActions({ listName, role, pending, onDelete, onLeave }: ListActionsProps) {
  const { t } = useTranslation();
  const [confirming, setConfirming] = useState(false);

  if (role === 'member') {
    return (
      <div>
        <Button variant="secondary" disabled={pending} onClick={onLeave}>
          {t('lists.actions.leave')}
        </Button>
      </div>
    );
  }

  if (!confirming) {
    return (
      <div>
        <Button
          variant="danger"
          disabled={pending}
          onClick={() => {
            setConfirming(true);
          }}
        >
          {t('lists.actions.delete')}
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      <Text>{t('lists.actions.confirmDelete', { name: listName })}</Text>
      <div className="flex flex-wrap gap-2">
        <Button
          variant="danger"
          disabled={pending}
          onClick={() => {
            onDelete();
            setConfirming(false);
          }}
        >
          {t('lists.actions.confirm')}
        </Button>
        <Button
          variant="secondary"
          onClick={() => {
            setConfirming(false);
          }}
        >
          {t('lists.actions.cancel')}
        </Button>
      </div>
    </div>
  );
}
