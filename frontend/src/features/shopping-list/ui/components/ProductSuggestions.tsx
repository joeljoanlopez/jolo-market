import { cva } from 'class-variance-authority';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import type { Product } from '@/features/catalog/domain/product';
import { PriceTag } from '@/features/catalog/ui/components/PriceTag';
import { ProductImage } from '@/features/catalog/ui/components/ProductImage';
import { SupermarketBadge } from '@/features/catalog/ui/components/SupermarketBadge';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import {
  suggestionOptionId,
  type Highlight,
  type SuggestionOption,
} from '../../domain/suggestions';

const optionVariants = cva('flex cursor-pointer items-center gap-3 rounded-md px-3 py-2', {
  variants: {
    highlighted: {
      true: 'bg-surface',
      false: 'hover:bg-surface',
    },
  },
});

export interface SuggestionSearch {
  status: 'loading' | 'error' | 'success';
  /** Always starts with the text option, so free text can be added whatever the search does. */
  options: SuggestionOption<Product>[];
}

interface ProductSuggestionsProps {
  /** Id of the listbox, for the combobox's `aria-controls`. */
  id: string;
  /** The typed text, trimmed. */
  text: string;
  search: SuggestionSearch;
  highlighted: Highlight;
  seeAllHref: string;
  onPickText: () => void;
  onPickProduct: (product: Product) => void;
  onRetry: () => void;
}

/** The suggestion panel of the add-item field (PD-80). */
export function ProductSuggestions({
  id,
  text,
  search,
  highlighted,
  seeAllHref,
  onPickText,
  onPickProduct,
  onRetry,
}: ProductSuggestionsProps) {
  const { t } = useTranslation();
  const onlyText = search.options.length === 1;

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-border bg-background p-2">
      <ul
        id={id}
        role="listbox"
        aria-label={t('lists.suggestions.label')}
        className="flex flex-col"
      >
        {search.options.map((option, index) => (
          <Option
            key={option.kind === 'text' ? 'text' : option.product.id}
            id={suggestionOptionId(id, index)}
            highlighted={highlighted === index}
            onPick={() => {
              if (option.kind === 'text') {
                onPickText();
              } else {
                onPickProduct(option.product);
              }
            }}
          >
            {option.kind === 'text' ? (
              <Text as="span">{t('lists.suggestions.addText', { text })}</Text>
            ) : (
              <ProductOption product={option.product} />
            )}
          </Option>
        ))}
      </ul>
      {search.status === 'loading' && (
        <div role="status" className="px-3">
          <Text size="sm" tone="muted">
            {t('lists.suggestions.loading')}
          </Text>
        </div>
      )}
      {search.status === 'error' && (
        <div role="alert" className="flex flex-col items-start gap-2 px-3">
          <Text size="sm">{t('lists.suggestions.error')}</Text>
          <Button variant="secondary" onClick={onRetry}>
            {t('lists.suggestions.retry')}
          </Button>
        </div>
      )}
      {search.status === 'success' && onlyText && (
        <Text size="sm" tone="muted" className="px-3">
          {t('lists.suggestions.empty', { text })}
        </Text>
      )}
      <Link to={seeAllHref} className="px-3 text-sm underline underline-offset-2">
        {t('lists.suggestions.seeAll')}
      </Link>
    </div>
  );
}

function Option({
  id,
  highlighted,
  onPick,
  children,
}: {
  id: string;
  highlighted: boolean;
  onPick: () => void;
  children: ReactNode;
}) {
  return (
    // Keyboard use goes through the combobox (aria-activedescendant), so options take no focus.
    <li
      id={id}
      role="option"
      aria-selected={highlighted}
      className={optionVariants({ highlighted })}
      // Keeps the focus in the field, so the next item can be typed at once (PD-81).
      onMouseDown={(event) => {
        event.preventDefault();
      }}
      onClick={onPick}
    >
      {children}
    </li>
  );
}

function ProductOption({ product }: { product: Product }) {
  return (
    <>
      {/* Not a link: tapping the image picks the product, like the rest of the option (PD-97).
          Hidden from assistive technology, since the option already names the product. */}
      <div aria-hidden="true" className="shrink-0">
        <ProductImage src={product.imageUrl} name={product.name} size="sm" />
      </div>
      <div className="flex min-w-0 flex-1 flex-col items-start gap-1">
        <Text as="span" weight="medium">
          {product.name}
        </Text>
        {product.brand !== null && (
          <Text as="span" size="sm" tone="muted">
            {product.brand}
          </Text>
        )}
        <SupermarketBadge supermarket={product.supermarket} />
      </div>
      <PriceTag priceCents={product.priceCents} unitPrice={product.unitPrice} />
    </>
  );
}
