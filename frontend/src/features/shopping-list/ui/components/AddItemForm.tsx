import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

import { MAX_ITEM_TEXT_LENGTH, MAX_QUANTITY, MIN_QUANTITY } from '../../domain/shoppingList';

interface AddItemFormProps {
  text: string;
  /** As typed, so the field can be empty while the user edits it. */
  quantity: string;
  /** Spanish copy of the last error, if any. */
  error: string | null;
  pending: boolean;
  onTextChange: (text: string) => void;
  onQuantityChange: (quantity: string) => void;
  onSubmit: () => void;
}

/** Adds a free-text item ("leche") with a quantity (PD-4, PD-45). */
export function AddItemForm({
  text,
  quantity,
  error,
  pending,
  onTextChange,
  onQuantityChange,
  onSubmit,
}: AddItemFormProps) {
  const { t } = useTranslation();

  return (
    <form
      className="flex flex-col gap-3"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="flex-1">
          <FormField
            label={t('lists.add.text')}
            name="text"
            value={text}
            maxLength={MAX_ITEM_TEXT_LENGTH}
            autoComplete="off"
            onChange={(event) => {
              onTextChange(event.target.value);
            }}
          />
        </div>
        <div className="sm:w-24">
          <FormField
            label={t('lists.add.quantity')}
            name="quantity"
            type="number"
            inputMode="numeric"
            min={MIN_QUANTITY}
            max={MAX_QUANTITY}
            step={1}
            value={quantity}
            onChange={(event) => {
              onQuantityChange(event.target.value);
            }}
          />
        </div>
        <Button type="submit" disabled={pending}>
          {t('lists.add.submit')}
        </Button>
      </div>
      {error === null ? null : <Alert>{error}</Alert>}
    </form>
  );
}
