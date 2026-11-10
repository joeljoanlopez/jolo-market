import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import type { Role } from '@/features/auth/domain/currentUser';
import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Select } from '@/shared/ui/atoms/Select/Select';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

interface CreateUserFormProps {
  username: string;
  role: Role;
  /** Spanish copy of the error to show, if any. */
  error: string | null;
  pending: boolean;
  onUsernameChange: (username: string) => void;
  onRoleChange: (role: Role) => void;
  onSubmit: () => void;
}

const roles: Role[] = ['USER', 'ADMIN'];

export function CreateUserForm({
  username,
  role,
  error,
  pending,
  onUsernameChange,
  onRoleChange,
  onSubmit,
}: CreateUserFormProps) {
  const { t } = useTranslation();
  const roleId = useId();

  return (
    <form
      className="flex flex-col gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <Text as="h2" size="xl" weight="bold">
        {t('admin.users.create.title')}
      </Text>
      {error === null ? null : <Alert>{error}</Alert>}
      <FormField
        label={t('admin.users.create.username')}
        hint={t('admin.users.create.usernameHint')}
        name="username"
        autoComplete="off"
        autoCapitalize="none"
        spellCheck={false}
        required
        value={username}
        onChange={(event) => {
          onUsernameChange(event.target.value);
        }}
      />
      <div className="flex flex-col gap-1">
        <Label htmlFor={roleId}>{t('admin.users.create.role')}</Label>
        <Select
          id={roleId}
          name="role"
          value={role}
          onChange={(event) => {
            onRoleChange(event.target.value === 'ADMIN' ? 'ADMIN' : 'USER');
          }}
        >
          {roles.map((option) => (
            <option key={option} value={option}>
              {t(`admin.users.roles.${option}`)}
            </option>
          ))}
        </Select>
      </div>
      <Button type="submit" disabled={pending}>
        {t('admin.users.create.submit')}
      </Button>
    </form>
  );
}
