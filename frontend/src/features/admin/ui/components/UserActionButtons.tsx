import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import type { UserAction } from '../../application/useUserActions';
import type { AdminUser } from '../../domain/adminUser';

interface UserActionButtonsProps {
  user: AdminUser;
  /** An admin can neither reset nor disable their own account (PD-35, PD-36). */
  isOwnAccount: boolean;
  pending: boolean;
  /** Spanish copy of the error of this user's last action, if any. */
  error: string | null;
  onAction: (action: UserAction) => void;
}

/** Actions that end sessions or remove credentials ask first (PD-42). */
type ConfirmedAction = Extract<UserAction, 'reset' | 'disable'>;

export function UserActionButtons({
  user,
  isOwnAccount,
  pending,
  error,
  onAction,
}: UserActionButtonsProps) {
  const { t } = useTranslation();
  const [confirming, setConfirming] = useState<ConfirmedAction | null>(null);

  const label = t('admin.users.actions.label', { username: user.username });

  if (confirming !== null) {
    return (
      <div role="group" aria-label={label} className="flex flex-col gap-2">
        <Text size="sm">
          {t(
            confirming === 'reset'
              ? 'admin.users.actions.confirmReset'
              : 'admin.users.actions.confirmDisable',
            { username: user.username },
          )}
        </Text>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="danger"
            size="sm"
            disabled={pending}
            onClick={() => {
              onAction(confirming);
              setConfirming(null);
            }}
          >
            {t('admin.users.actions.confirm')}
          </Button>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => {
              setConfirming(null);
            }}
          >
            {t('admin.users.actions.cancel')}
          </Button>
        </div>
      </div>
    );
  }

  const canResetOrDisable = user.status === 'ACTIVE' && !isOwnAccount;

  return (
    <div role="group" aria-label={label} className="flex flex-col gap-2">
      {error === null ? null : <Alert>{error}</Alert>}
      <div className="flex flex-wrap gap-2">
        {user.status === 'PENDING_ACTIVATION' ? (
          <Button
            variant="secondary"
            size="sm"
            disabled={pending}
            onClick={() => {
              onAction('regenerateLink');
            }}
          >
            {t('admin.users.actions.regenerateLink')}
          </Button>
        ) : null}
        {canResetOrDisable ? (
          <>
            <Button
              variant="secondary"
              size="sm"
              disabled={pending}
              onClick={() => {
                setConfirming('reset');
              }}
            >
              {t('admin.users.actions.reset')}
            </Button>
            <Button
              variant="secondary"
              size="sm"
              disabled={pending}
              onClick={() => {
                setConfirming('disable');
              }}
            >
              {t('admin.users.actions.disable')}
            </Button>
          </>
        ) : null}
        {user.status === 'DISABLED' ? (
          <Button
            variant="secondary"
            size="sm"
            disabled={pending}
            onClick={() => {
              onAction('enable');
            }}
          >
            {t('admin.users.actions.enable')}
          </Button>
        ) : null}
        <Button
          variant="secondary"
          size="sm"
          disabled={pending}
          onClick={() => {
            onAction('changeRole');
          }}
        >
          {t(
            user.role === 'ADMIN'
              ? 'admin.users.actions.makeUser'
              : 'admin.users.actions.makeAdmin',
          )}
        </Button>
      </div>
    </div>
  );
}
