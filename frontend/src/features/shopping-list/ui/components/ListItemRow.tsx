import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { formatPrice } from '@/features/catalog/domain/format';
import { productPath } from '@/features/catalog/domain/productPath';
import { ProductImage } from '@/features/catalog/ui/components/ProductImage';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Checkbox } from '@/shared/ui/atoms/Checkbox/Checkbox';
import { Select } from '@/shared/ui/atoms/Select/Select';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { cn } from '@/shared/ui/lib/cn';

import { SECTIONS, isSectionId, type SectionId } from '../../domain/sections';
import type { ListItem, ProductItem } from '../../domain/shoppingList';

interface ListItemRowProps {
  item: ListItem;
  /** Name of the product's supermarket, when it is a known one. */
  supermarketName: string | null;
  onCheckedChange: (checked: boolean) => void;
  onRemove: () => void;
  /** A section id chosen by hand, or `null` to follow the catalog ("Otros" for free text, PD-92). */
  onSectionChange: (section: SectionId | null) => void;
}

/** One item: check it, see its quantity and, for a product, its price or that it is unavailable. */
export function ListItemRow({
  item,
  supermarketName,
  onCheckedChange,
  onRemove,
  onSectionChange,
}: ListItemRowProps) {
  const { t } = useTranslation();
  const name = itemName(item) ?? t('lists.item.unknownProduct');

  return (
    <li className="flex items-center gap-3 rounded-lg border border-border bg-background p-3">
      <Checkbox
        aria-label={name}
        checked={item.checked}
        onChange={(event) => {
          onCheckedChange(event.target.checked);
        }}
      />
      {item.type === 'product' ? (
        <ItemImage item={item} name={name} />
      ) : (
        // Same width as the image, so free text lines up with the product names (PD-97).
        <div aria-hidden="true" className="w-12 shrink-0" />
      )}
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <Text as="span" weight="medium" className={cn(item.checked && 'text-muted line-through')}>
          {item.type === 'product' && item.product.name !== null ? (
            <Link to={productPath(item.product.id)} className="underline-offset-2 hover:underline">
              {name}
            </Link>
          ) : (
            name
          )}
        </Text>
        <Text as="span" size="sm" tone="muted">
          {t('lists.item.quantity', { count: item.quantity })}
          {item.type === 'product' ? productDetails(item.product, supermarketName) : null}
        </Text>
        {item.type === 'product' && !item.product.available ? (
          <Text as="span" size="sm" tone="danger" weight="medium">
            {t('lists.item.unavailable')}
          </Text>
        ) : null}
        {item.checked ? null : (
          <SectionSelect item={item} name={name} onSectionChange={onSectionChange} />
        )}
      </div>
      <Button
        variant="secondary"
        size="sm"
        aria-label={t('lists.item.remove', { name })}
        onClick={onRemove}
      >
        {t('lists.item.removeShort')}
      </Button>
    </li>
  );
}

/** The value of the "Automática" option of a product item. */
const AUTOMATIC = '';

/**
 * The section of a pending item (PD-92, PD-116). A product can follow its catalog section
 * ("Automática"); a free-text item without a choice is in "Otros", so choosing "Otros" removes it.
 */
function SectionSelect({
  item,
  name,
  onSectionChange,
}: {
  item: ListItem;
  name: string;
  onSectionChange: (section: SectionId | null) => void;
}) {
  const { t } = useTranslation();
  const defaultValue = item.type === 'product' ? AUTOMATIC : 'other';

  return (
    <Select
      size="sm"
      aria-label={t('lists.item.section', { name })}
      value={item.sectionOverride ?? defaultValue}
      onChange={(event) => {
        const value = event.target.value;
        onSectionChange(value === defaultValue || !isSectionId(value) ? null : value);
      }}
    >
      {item.type === 'product' ? (
        <option value={AUTOMATIC}>
          {t('lists.item.automaticSection', {
            section: t(`lists.sections.${item.product.section}`),
          })}
        </option>
      ) : null}
      {SECTIONS.map((section) => (
        <option key={section} value={section}>
          {t(`lists.sections.${section}`)}
        </option>
      ))}
    </Select>
  );
}

/**
 * The product image, faded once checked (PD-97). For a known product it links to its page like
 * the name (PD-48), but out of the tab order and hidden from assistive technology, which the
 * name link already serves.
 */
function ItemImage({ item, name }: { item: ProductItem; name: string }) {
  const image = (
    <ProductImage src={item.product.imageUrl} name={name} size="sm" faded={item.checked} />
  );
  if (item.product.name === null) {
    return image;
  }
  return (
    <Link to={productPath(item.product.id)} tabIndex={-1} aria-hidden="true" className="shrink-0">
      {image}
    </Link>
  );
}

/** The text of the item, or the product name; `null` for a product the catalog no longer knows. */
function itemName(item: ListItem): string | null {
  return item.type === 'text' ? item.text : item.product.name;
}

function productDetails(product: ProductItem['product'], supermarketName: string | null): string {
  const parts = [
    product.brand,
    supermarketName,
    product.available && product.priceCents !== null ? formatPrice(product.priceCents) : null,
  ].filter((part) => part !== null);
  return parts.map((part) => ` · ${part}`).join('');
}
