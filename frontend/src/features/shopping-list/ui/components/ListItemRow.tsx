import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { formatPrice } from '@/features/catalog/domain/format';
import { productPath } from '@/features/catalog/domain/productPath';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Checkbox } from '@/shared/ui/atoms/Checkbox/Checkbox';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { cn } from '@/shared/ui/lib/cn';

import type { ListItem } from '../../domain/shoppingList';

interface ListItemRowProps {
  item: ListItem;
  /** Name of the product's supermarket, when it is a known one. */
  supermarketName: string | null;
  onCheckedChange: (checked: boolean) => void;
  onRemove: () => void;
}

/** One item: check it, see its quantity and, for a product, its price or that it is unavailable. */
export function ListItemRow({
  item,
  supermarketName,
  onCheckedChange,
  onRemove,
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

/** The text of the item, or the product name; `null` for a product the catalog no longer knows. */
function itemName(item: ListItem): string | null {
  return item.type === 'text' ? item.text : item.product.name;
}

function productDetails(
  product: Extract<ListItem, { type: 'product' }>['product'],
  supermarketName: string | null,
): string {
  const parts = [
    product.brand,
    supermarketName,
    product.available && product.priceCents !== null ? formatPrice(product.priceCents) : null,
  ].filter((part) => part !== null);
  return parts.map((part) => ` · ${part}`).join('');
}
