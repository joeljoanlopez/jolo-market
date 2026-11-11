import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import type { ListMember } from '../../domain/shoppingList';

interface MembersPanelProps {
  owner: ListMember;
  members: ListMember[];
  pending: boolean;
  onRemoveMember: (userId: string) => void;
  onRegenerateLink: () => void;
}

/** Owner only: who is in the list, removing them, and a new link (asked first, PD-2). */
export function MembersPanel({
  owner,
  members,
  pending,
  onRemoveMember,
  onRegenerateLink,
}: MembersPanelProps) {
  const { t } = useTranslation();
  const titleId = useId();
  const [confirming, setConfirming] = useState(false);

  return (
    <section aria-labelledby={titleId} className="flex flex-col gap-3">
      <span id={titleId}>
        <Text as="h2" size="lg" weight="bold">
          {t('lists.members.title')}
        </Text>
      </span>
      <Text size="sm" tone="muted">
        {t('lists.members.owner', { username: owner.username })}
      </Text>
      {members.length === 0 ? (
        <Text size="sm">{t('lists.members.none')}</Text>
      ) : (
        <ul className="flex flex-col gap-2">
          {members.map((member) => (
            <li key={member.id} className="flex items-center justify-between gap-3">
              <Text as="span">{member.username}</Text>
              <Button
                variant="secondary"
                size="sm"
                disabled={pending}
                onClick={() => {
                  onRemoveMember(member.id);
                }}
              >
                {t('lists.members.remove', { username: member.username })}
              </Button>
            </li>
          ))}
        </ul>
      )}
      {confirming ? (
        <div className="flex flex-col gap-2">
          <Text size="sm">{t('lists.members.confirmRegenerate')}</Text>
          <div className="flex flex-wrap gap-2">
            <Button
              variant="danger"
              size="sm"
              disabled={pending}
              onClick={() => {
                onRegenerateLink();
                setConfirming(false);
              }}
            >
              {t('lists.members.confirm')}
            </Button>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => {
                setConfirming(false);
              }}
            >
              {t('lists.members.cancel')}
            </Button>
          </div>
        </div>
      ) : (
        <div>
          <Button
            variant="secondary"
            size="sm"
            disabled={pending}
            onClick={() => {
              setConfirming(true);
            }}
          >
            {t('lists.members.regenerate')}
          </Button>
        </div>
      )}
    </section>
  );
}
