import { useId, type Ref } from 'react';
import { useTranslation } from 'react-i18next';

import type { Product } from '@/features/catalog/domain/product';
import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { FormField } from '@/shared/ui/molecules/FormField/FormField';

import { MAX_ITEM_TEXT_LENGTH, MAX_QUANTITY, MIN_QUANTITY } from '../../domain/shoppingList';
import { suggestionOptionId, type Highlight } from '../../domain/suggestions';
import { ProductSuggestions, type SuggestionSearch } from './ProductSuggestions';

export interface OpenSuggestions {
  search: SuggestionSearch;
  highlighted: Highlight;
  seeAllHref: string;
}

interface AddItemFormProps {
  text: string;
  /** As typed, so the field can be empty while the user edits it. */
  quantity: string;
  /** Spanish copy of the last error, if any. */
  error: string | null;
  pending: boolean;
  /** The suggestion panel, or `null` while it is closed (PD-80). */
  suggestions: OpenSuggestions | null;
  /** The text field, so the container can keep the focus in it after adding (PD-81). */
  textRef?: Ref<HTMLInputElement>;
  onTextChange: (text: string) => void;
  onQuantityChange: (quantity: string) => void;
  /** Adds the typed text as a free-text item. */
  onSubmit: () => void;
  onPickProduct: (product: Product) => void;
  onMoveHighlight: (direction: 'next' | 'previous') => void;
  onCloseSuggestions: () => void;
  onRetrySuggestions: () => void;
}

/**
 * Adds an item with a quantity: the typed text (PD-4) or a product suggested by the catalog
 * search (PD-80, PD-81). The text field is a combobox that controls the suggestion list.
 */
export function AddItemForm({
  text,
  quantity,
  error,
  pending,
  suggestions,
  textRef,
  onTextChange,
  onQuantityChange,
  onSubmit,
  onPickProduct,
  onMoveHighlight,
  onCloseSuggestions,
  onRetrySuggestions,
}: AddItemFormProps) {
  const { t } = useTranslation();
  const listboxId = useId();
  const highlighted = suggestions?.highlighted ?? null;
  const highlightedOption =
    suggestions === null || highlighted === null
      ? undefined
      : suggestions.search.options[highlighted];

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
            ref={textRef}
            label={t('lists.add.text')}
            name="text"
            value={text}
            maxLength={MAX_ITEM_TEXT_LENGTH}
            autoComplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded={suggestions !== null}
            aria-controls={suggestions === null ? undefined : listboxId}
            aria-activedescendant={
              highlighted === null ? undefined : suggestionOptionId(listboxId, highlighted)
            }
            onChange={(event) => {
              onTextChange(event.target.value);
            }}
            onKeyDown={(event) => {
              if (suggestions === null) {
                return;
              }
              switch (event.key) {
                case 'ArrowDown':
                case 'ArrowUp':
                  event.preventDefault();
                  onMoveHighlight(event.key === 'ArrowDown' ? 'next' : 'previous');
                  return;
                case 'Escape':
                  event.preventDefault();
                  onCloseSuggestions();
                  return;
                case 'Enter':
                  // With nothing or the text option highlighted, Enter submits the free text.
                  if (highlightedOption?.kind === 'product') {
                    event.preventDefault();
                    onPickProduct(highlightedOption.product);
                  }
                  return;
              }
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
      {suggestions === null ? null : (
        <ProductSuggestions
          id={listboxId}
          text={text.trim()}
          search={suggestions.search}
          highlighted={highlighted}
          seeAllHref={suggestions.seeAllHref}
          onPickText={onSubmit}
          onPickProduct={onPickProduct}
          onRetry={onRetrySuggestions}
        />
      )}
      {error === null ? null : <Alert>{error}</Alert>}
    </form>
  );
}
