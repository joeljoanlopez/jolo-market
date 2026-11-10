import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useCurrentUser } from '@/features/auth/application/useCurrentUser';
import type { Role } from '@/features/auth/domain/currentUser';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { useUserActions, type UserAction } from '../../application/useUserActions';
import { useCreateUser, useUsers, type CreateUserError } from '../../application/useUsers';
import { activationPath, type ActivationLinkGrant } from '../../domain/adminUser';
import { ActivationLinkPanel } from '../components/ActivationLinkPanel';
import { CreateUserForm } from '../components/CreateUserForm';
import { UserActionButtons } from '../components/UserActionButtons';
import { UserList } from '../components/UserList';

/** The /admin/users page (ADMIN only): the users, and creating one. */
export function UsersPageContainer() {
  const { t } = useTranslation();
  const users = useUsers();
  const createUser = useCreateUser();
  const actions = useUserActions();
  const session = useCurrentUser();
  const currentUserId = session.status === 'authenticated' ? session.user.id : null;
  const [username, setUsername] = useState('');
  const [role, setRole] = useState<Role>('USER');

  const actionErrors: Record<UserAction, string> = {
    regenerateLink: t('admin.users.actions.errors.regenerateLink'),
    reset: t('admin.users.actions.errors.reset'),
    disable: t('admin.users.actions.errors.disable'),
    enable: t('admin.users.actions.errors.enable'),
    changeRole: t('admin.users.actions.errors.changeRole'),
  };
  const shownGrant = actions.grant ?? createUser.grant;

  const createErrors: Record<CreateUserError, string> = {
    taken: t('admin.users.create.taken'),
    invalid: t('admin.users.create.invalid'),
    failed: t('admin.users.create.failed'),
  };

  return (
    <section className="flex flex-col gap-6">
      <Text as="h1" size="2xl" weight="bold">
        {t('admin.users.title')}
      </Text>
      {shownGrant === null ? (
        <CreateUserForm
          username={username}
          role={role}
          error={createUser.error === null ? null : createErrors[createUser.error]}
          pending={createUser.pending}
          onUsernameChange={setUsername}
          onRoleChange={setRole}
          onSubmit={() => {
            createUser.create({ username, role });
          }}
        />
      ) : (
        <ActivationLinkContainer
          key={shownGrant.activationToken}
          grant={shownGrant}
          onDone={() => {
            setUsername('');
            setRole('USER');
            createUser.dismiss();
            actions.dismissGrant();
          }}
        />
      )}
      {users.status === 'loading' ? (
        <div role="status">
          <Text tone="muted">{t('admin.users.loading')}</Text>
        </div>
      ) : null}
      {users.status === 'error' ? (
        <div className="flex flex-col items-start gap-3">
          <Text>{t('admin.users.loadFailed')}</Text>
          <Button variant="secondary" onClick={users.retry}>
            {t('admin.users.retry')}
          </Button>
        </div>
      ) : null}
      {users.status === 'success' ? (
        <UserList
          users={users.users}
          renderActions={(user) => {
            const failure = actions.failure?.userId === user.id ? actions.failure : null;
            return (
              <UserActionButtons
                user={user}
                isOwnAccount={user.id === currentUserId}
                pending={actions.pendingUserId === user.id}
                error={
                  failure === null
                    ? null
                    : failure.refused
                      ? actionErrors[failure.action]
                      : t('admin.users.actions.errors.failed')
                }
                onAction={(action) => {
                  actions.run(action, user);
                }}
              />
            );
          }}
        />
      ) : null}
    </section>
  );
}

/** Builds the full link and copies it; the copy state belongs to this one link. */
export function ActivationLinkContainer({
  grant,
  onDone,
}: {
  grant: ActivationLinkGrant;
  onDone: () => void;
}) {
  const [copied, setCopied] = useState(false);
  const link = new URL(activationPath(grant), window.location.origin).toString();

  return (
    <ActivationLinkPanel
      username={grant.username}
      link={link}
      expiresAt={grant.expiresAt}
      copied={copied}
      onCopy={() => {
        void navigator.clipboard.writeText(link).then(() => {
          setCopied(true);
        });
      }}
      onDone={onDone}
    />
  );
}
