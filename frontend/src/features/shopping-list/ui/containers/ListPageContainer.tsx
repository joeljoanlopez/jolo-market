import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { useSupermarkets } from '@/features/catalog/application/useSupermarkets';
import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { useListItems, type AddItemError } from '../../application/useListItems';
import { useListManagement, type ListAction } from '../../application/useListManagement';
import { useShoppingList } from '../../application/useShoppingList';
import {
  isOwner,
  itemsForDisplay,
  joinPath,
  unpricedItems,
  type ShoppingList,
} from '../../domain/shoppingList';
import { AddItemForm } from '../components/AddItemForm';
import { ListActions } from '../components/ListActions';
import { ListItemRow } from '../components/ListItemRow';
import { ListNotFound } from '../components/ListNotFound';
import { MembersPanel } from '../components/MembersPanel';
import { ShareLinkButton } from '../components/ShareLinkButton';
import { SupermarketTotals } from '../components/SupermarketTotals';

/** `/lists/:listId`: an open list, kept up to date while it is on screen (PD-7). */
export function ListPageContainer() {
  const { t } = useTranslation();
  const { listId = '' } = useParams();
  const state = useShoppingList(listId);

  switch (state.status) {
    case 'loading':
      return (
        <div role="status">
          <Text tone="muted">{t('lists.page.loading')}</Text>
        </div>
      );
    case 'notFound':
      return <ListNotFound />;
    case 'error':
      return (
        <div className="flex flex-col items-start gap-3">
          <Text>{t('lists.page.loadFailed')}</Text>
          <Button variant="secondary" onClick={state.retry}>
            {t('lists.page.retry')}
          </Button>
        </div>
      );
    case 'success':
      return <OpenList list={state.list} />;
  }
}

function OpenList({ list }: { list: ShoppingList }) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const items = useListItems(list.id);
  const management = useListManagement(list.id);
  const supermarketNames = useSupermarketNames(list);
  const [text, setText] = useState('');
  const [quantity, setQuantity] = useState('1');
  const goHome = () => {
    void navigate('/');
  };

  const addErrors: Record<AddItemError, string> = {
    invalid: t('lists.add.invalid'),
    failed: t('lists.add.failed'),
  };
  const actionErrors: Record<ListAction, string> = {
    regenerateLink: t('lists.actions.errors.regenerateLink'),
    removeMember: t('lists.actions.errors.removeMember'),
    delete: t('lists.actions.errors.delete'),
    leave: t('lists.actions.errors.leave'),
  };
  const shown = itemsForDisplay(list.items);
  const owner = isOwner(list);

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <Text as="h1" size="2xl" weight="bold">
          {list.name}
        </Text>
        <Text tone="muted">{t('lists.page.ownedBy', { username: list.owner.username })}</Text>
      </div>
      {owner && list.shareToken !== null ? (
        <ShareLinkContainer key={list.shareToken} shareToken={list.shareToken} />
      ) : null}
      <AddItemForm
        text={text}
        quantity={quantity}
        pending={items.adding}
        error={items.addError === null ? null : addErrors[items.addError]}
        onTextChange={setText}
        onQuantityChange={setQuantity}
        onSubmit={() => {
          items.add({ type: 'text', text, quantity: Number(quantity) }, () => {
            setText('');
            setQuantity('1');
          });
        }}
      />
      {items.failed ? <Alert>{t('lists.page.itemFailed')}</Alert> : null}
      {shown.length === 0 ? (
        <Text tone="muted">{t('lists.page.empty')}</Text>
      ) : (
        <ul aria-label={t('lists.page.items')} className="flex flex-col gap-2">
          {shown.map((item) => (
            <ListItemRow
              key={item.id}
              item={item}
              supermarketName={
                item.type === 'product' && item.product.supermarketId !== null
                  ? (supermarketNames.get(item.product.supermarketId) ?? null)
                  : null
              }
              onCheckedChange={(checked) => {
                items.setChecked(item.id, checked);
              }}
              onRemove={() => {
                items.remove(item.id);
              }}
            />
          ))}
        </ul>
      )}
      <SupermarketTotals
        totals={list.totals}
        excluded={unpricedItems(list.items).map(
          (item) => item.product.name ?? t('lists.item.unknownProduct'),
        )}
      />
      {management.failedAction === null ? null : (
        <Alert>{actionErrors[management.failedAction]}</Alert>
      )}
      {owner ? (
        <MembersPanel
          owner={list.owner}
          members={list.members}
          pending={management.pending}
          onRemoveMember={management.removeMember}
          onRegenerateLink={management.regenerateLink}
        />
      ) : null}
      <ListActions
        listName={list.name}
        role={list.role}
        pending={management.pending}
        onDelete={() => {
          management.deleteList(goHome);
        }}
        onLeave={() => {
          management.leaveList(goHome);
        }}
      />
    </section>
  );
}

/** Builds the full link and copies it; the copy state belongs to this one link. */
function ShareLinkContainer({ shareToken }: { shareToken: string }) {
  const [copied, setCopied] = useState(false);
  const link = new URL(joinPath(shareToken), window.location.origin).toString();

  return (
    <ShareLinkButton
      link={link}
      copied={copied}
      onCopy={() => {
        void navigator.clipboard.writeText(link).then(() => {
          setCopied(true);
        });
      }}
    />
  );
}

/** Supermarket names by id: the enabled ones, plus any the totals mention. */
function useSupermarketNames(list: ShoppingList): Map<string, string> {
  const supermarkets = useSupermarkets();
  return new Map([
    ...supermarkets.map((supermarket) => [supermarket.id, supermarket.name] as const),
    ...list.totals.map((total) => [total.supermarketId, total.supermarketName] as const),
  ]);
}
