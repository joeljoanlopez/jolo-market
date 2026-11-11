import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

import { MAX_LIST_NAME_LENGTH } from '../../domain/shoppingList';

interface CreateListFormProps {
  name: string;
  /** Spanish copy of the last error, if any. */
  error: string | null;
  pending: boolean;
  onNameChange: (name: string) => void;
  onSubmit: () => void;
}

export function CreateListForm({
  name,
  error,
  pending,
  onNameChange,
  onSubmit,
}: CreateListFormProps) {
  const { t } = useTranslation();

  return (
    <form
      className="flex flex-col gap-3 sm:flex-row sm:items-end"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <div className="flex-1">
        <FormField
          label={t('lists.create.name')}
          name="name"
          value={name}
          maxLength={MAX_LIST_NAME_LENGTH}
          autoComplete="off"
          {...(error === null ? {} : { error })}
          onChange={(event) => {
            onNameChange(event.target.value);
          }}
        />
      </div>
      <Button type="submit" disabled={pending}>
        {t('lists.create.submit')}
      </Button>
    </form>
  );
}
