import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Select } from '@/shared/ui/atoms/Select/Select';
import { Text } from '@/shared/ui/atoms/Text/Text';

import type { ListSummary } from '../../domain/shoppingList';

interface AddToListPickerProps {
  productName: string;
  lists: ListSummary[] | 'loading' | 'failed';
  selectedListId: string;
  pending: boolean;
  /** Name of the list the product was just added to. */
  addedTo: string | null;
  failed: boolean;
  onSelect: (listId: string) => void;
  onSubmit: () => void;
  onCancel: () => void;
}

/** The open "Add to list" of a search result: pick one of "My lists" (PD-4). */
export function AddToListPicker({
  productName,
  lists,
  selectedListId,
  pending,
  addedTo,
  failed,
  onSelect,
  onSubmit,
  onCancel,
}: AddToListPickerProps) {
  const { t } = useTranslation();
  const selectId = useId();
  const label = t('lists.addToList.openLabel', { name: productName });

  return (
    <div role="group" aria-label={label} className="flex flex-col gap-2">
      {lists === 'loading' ? (
        <Text size="sm" tone="muted">
          {t('lists.addToList.loading')}
        </Text>
      ) : null}
      {lists === 'failed' ? <Text size="sm">{t('lists.mine.loadFailed')}</Text> : null}
      {Array.isArray(lists) && lists.length === 0 ? (
        <Text size="sm">
          {t('lists.addToList.noLists')}{' '}
          <Link to="/" className="text-primary underline">
            {t('lists.addToList.createList')}
          </Link>
        </Text>
      ) : null}
      {Array.isArray(lists) && lists.length > 0 ? (
        <form
          className="flex flex-col gap-2 sm:flex-row sm:items-end"
          onSubmit={(event) => {
            event.preventDefault();
            onSubmit();
          }}
        >
          <div className="flex flex-1 flex-col gap-1">
            <Label htmlFor={selectId}>{t('lists.addToList.list')}</Label>
            <Select
              id={selectId}
              value={selectedListId}
              onChange={(event) => {
                onSelect(event.target.value);
              }}
            >
              {lists.map((list) => (
                <option key={list.id} value={list.id}>
                  {list.name}
                </option>
              ))}
            </Select>
          </div>
          <Button type="submit" size="sm" disabled={pending}>
            {t('lists.addToList.submit')}
          </Button>
        </form>
      ) : null}
      <div>
        <Button variant="secondary" size="sm" onClick={onCancel}>
          {t('lists.addToList.cancel')}
        </Button>
      </div>
      {failed ? <Alert>{t('lists.addToList.failed')}</Alert> : null}
      <div role="status">
        {addedTo === null ? null : (
          <Text size="sm" weight="medium">
            {t('lists.addToList.added', { name: addedTo })}
          </Text>
        )}
      </div>
    </div>
  );
}

/** The closed "Add to list" of a search result. */
export function AddToListButton({
  productName,
  onOpen,
}: {
  productName: string;
  onOpen: () => void;
}) {
  const { t } = useTranslation();

  return (
    <Button
      variant="secondary"
      size="sm"
      aria-label={t('lists.addToList.openLabel', { name: productName })}
      onClick={onOpen}
    >
      {t('lists.addToList.open')}
    </Button>
  );
}
