import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams, useSearchParams } from 'react-router';

import {
  useProductSearch,
  type ProductSearchState,
} from '@/features/catalog/application/useProductSearch';
import { useSupermarkets } from '@/features/catalog/application/useSupermarkets';
import type { Product } from '@/features/catalog/domain/product';
import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { useListItems, type AddItemError } from '../../application/useListItems';
import { useListManagement, type ListAction } from '../../application/useListManagement';
import { useShoppingList } from '../../application/useShoppingList';
import { groupBySection } from '../../domain/sections';
import {
  isOwner,
  joinPath,
  unpricedItems,
  type ListItem,
  type ShoppingList,
} from '../../domain/shoppingList';
import {
  NO_HIGHLIGHT,
  highlightAfter,
  searchResultsPath,
  suggestionOptions,
  type Highlight,
} from '../../domain/suggestions';
import {
  hiddenWithoutSupermarket,
  isSupermarketChip,
  itemsOfSupermarket,
  supermarketChips,
} from '../../domain/supermarketFilter';
import { AddItemForm, type OpenSuggestions } from '../components/AddItemForm';
import { ListActions } from '../components/ListActions';
import { ListItemRow } from '../components/ListItemRow';
import { ListNotFound } from '../components/ListNotFound';
import { SectionHeader } from '../components/SectionHeader';
import { MembersPanel } from '../components/MembersPanel';
import { ShareLinkButton } from '../components/ShareLinkButton';
import { SupermarketFilter } from '../components/SupermarketFilter';
import { SupermarketTotals } from '../components/SupermarketTotals';

interface ListPageContainerProps {
  /** Sections of other features shown under the list, such as its receipts (E10); the routes compose them. */
  renderListExtras?: (listId: string) => ReactNode;
}

/** `/lists/:listId`: an open list, kept up to date while it is on screen (PD-7). */
export function ListPageContainer({ renderListExtras }: ListPageContainerProps = {}) {
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
      return <OpenList list={state.list} extras={renderListExtras?.(state.list.id) ?? null} />;
  }
}

function OpenList({ list, extras }: { list: ShoppingList; extras: ReactNode }) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const items = useListItems(list.id);
  const management = useListManagement(list.id);
  const supermarketNames = useSupermarketNames(list);
  const addition = useAddItemForm(list.id);
  const filter = useSupermarketFilter(list);
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
  const shown =
    filter.selected === null ? list.items : itemsOfSupermarket(list.items, filter.selected);
  const grouped = groupBySection(shown);
  const row = (item: ListItem) => (
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
      onSectionChange={(section) => {
        items.changeSection(item.id, section);
      }}
    />
  );
  const hidden = filter.selected === null ? 0 : hiddenWithoutSupermarket(list.items);
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
        {...addition.form}
        pending={items.adding}
        error={items.addError === null ? null : addErrors[items.addError]}
        onSubmit={() => {
          items.add(
            { type: 'text', text: addition.form.text, quantity: addition.quantity },
            addition.reset,
          );
        }}
        onPickProduct={(product) => {
          items.add(
            { type: 'product', productId: product.id, quantity: addition.quantity },
            addition.reset,
          );
        }}
      />
      {items.failed ? <Alert>{t('lists.page.itemFailed')}</Alert> : null}
      <SupermarketFilter
        chips={supermarketChips(list)}
        selected={filter.selected}
        onSelect={filter.select}
      />
      {hidden === 0 ? null : (
        <Text size="sm" tone="muted">
          {t('lists.filter.hidden', { count: hidden })}
        </Text>
      )}
      {shown.length === 0 ? (
        <Text tone="muted">{t('lists.page.empty')}</Text>
      ) : (
        <ul aria-label={t('lists.page.items')} className="flex flex-col gap-4">
          {grouped.sections.map((group) => (
            <li key={group.section} className="flex flex-col gap-2">
              <SectionHeader section={group.section} />
              <ul aria-label={t(`lists.sections.${group.section}`)} className="flex flex-col gap-2">
                {group.items.map(row)}
              </ul>
            </li>
          ))}
          {grouped.checked.length === 0 ? null : (
            <li>
              <ul className="flex flex-col gap-2">{grouped.checked.map(row)}</ul>
            </li>
          )}
        </ul>
      )}
      <SupermarketTotals
        totals={list.totals}
        excluded={unpricedItems(list.items).map(
          (item) => item.product.name ?? t('lists.item.unknownProduct'),
        )}
      />
      {extras}
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

const SUPERMARKET_PARAM = 'supermarket';

/**
 * The supermarket chosen in the `supermarket` URL parameter (PD-86). A value that names no chip,
 * on load or after a polling update removes its last available product, is dropped from the URL,
 * so the view is "Todos".
 */
function useSupermarketFilter(list: ShoppingList) {
  const [searchParams, setSearchParams] = useSearchParams();
  const requested = searchParams.get(SUPERMARKET_PARAM);
  const selected = requested !== null && isSupermarketChip(list, requested) ? requested : null;
  const dropRequested = requested !== null && selected === null;

  useEffect(() => {
    if (dropRequested) {
      setSearchParams(
        (current) => {
          const next = new URLSearchParams(current);
          next.delete(SUPERMARKET_PARAM);
          return next;
        },
        { replace: true },
      );
    }
  }, [dropRequested, setSearchParams]);

  return {
    selected,
    select: (supermarketId: string | null) => {
      setSearchParams((current) => {
        const next = new URLSearchParams(current);
        if (supermarketId === null) {
          next.delete(SUPERMARKET_PARAM);
        } else {
          next.set(SUPERMARKET_PARAM, supermarketId);
        }
        return next;
      });
    },
  };
}

/**
 * State of the add-item form: the typed text searches the catalog (PD-80), and after an item
 * is added the form is ready for the next one, with the focus still in the field (PD-81).
 */
function useAddItemForm(listId: string) {
  const textRef = useRef<HTMLInputElement>(null);
  const [text, setText] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [highlighted, setHighlighted] = useState<Highlight>(NO_HIGHLIGHT);
  const [closed, setClosed] = useState(false);
  const search = useProductSearch({ text });
  const suggestions = closed ? null : openSuggestions(search, highlighted, text, listId);

  return {
    quantity: Number(quantity),
    reset: () => {
      setText('');
      setQuantity('1');
      setHighlighted(NO_HIGHLIGHT);
      setClosed(false);
      textRef.current?.focus();
    },
    form: {
      text,
      quantity,
      suggestions,
      textRef,
      onTextChange: (next: string) => {
        setText(next);
        setHighlighted(highlightAfter(highlighted, { type: 'textChanged' }));
        setClosed(false);
      },
      onQuantityChange: setQuantity,
      onMoveHighlight: (direction: 'next' | 'previous') => {
        setHighlighted(
          highlightAfter(highlighted, {
            type: direction,
            optionCount: suggestions?.search.options.length ?? 0,
          }),
        );
      },
      onCloseSuggestions: () => {
        setClosed(true);
        setHighlighted(NO_HIGHLIGHT);
      },
      onRetrySuggestions: () => {
        if (search.status === 'error') {
          search.retry();
        }
      },
    },
  };
}

function openSuggestions(
  search: ProductSearchState,
  highlighted: Highlight,
  text: string,
  listId: string,
): OpenSuggestions | null {
  if (search.status === 'idle') {
    return null;
  }
  const products: Product[] = search.status === 'success' ? search.data.items : [];
  return {
    search: { status: search.status, options: suggestionOptions(products) },
    highlighted,
    seeAllHref: searchResultsPath(text.trim(), listId),
  };
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
