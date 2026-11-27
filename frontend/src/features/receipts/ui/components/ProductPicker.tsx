import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import type { Product } from '@/features/catalog/domain/product';
import { PriceTag } from '@/features/catalog/ui/components/PriceTag';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Text } from '@/shared/ui/atoms/Text/Text';

export type PickerResults =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'error' }
  | { status: 'success'; products: Product[] };

interface ProductPickerProps {
  supermarketName: string;
  text: string;
  results: PickerResults;
  onTextChange: (text: string) => void;
  onPick: (product: Product) => void;
  onClose: () => void;
}

/** "Cambiar": the catalog search, limited to the receipt's supermarket (PD-33, PD-67). */
export function ProductPicker({
  supermarketName,
  text,
  results,
  onTextChange,
  onPick,
  onClose,
}: ProductPickerProps) {
  const { t } = useTranslation();
  const id = useId();

  return (
    <div className="flex flex-col gap-2 rounded-md border border-border bg-surface p-3">
      <Label htmlFor={id}>{t('receipts.line.search', { supermarket: supermarketName })}</Label>
      <div className="flex gap-2">
        <Input
          id={id}
          type="search"
          value={text}
          onChange={(event) => {
            onTextChange(event.target.value);
          }}
        />
        <Button size="sm" variant="secondary" onClick={onClose}>
          {t('receipts.line.closeSearch')}
        </Button>
      </div>
      {results.status === 'loading' ? (
        <Text size="sm" tone="muted">
          {t('receipts.line.searching')}
        </Text>
      ) : null}
      {results.status === 'error' ? (
        <Text size="sm" tone="danger">
          {t('receipts.line.searchFailed')}
        </Text>
      ) : null}
      {results.status === 'success' && results.products.length === 0 ? (
        <Text size="sm" tone="muted">
          {t('receipts.line.noResults', { supermarket: supermarketName })}
        </Text>
      ) : null}
      {results.status === 'success' && results.products.length > 0 ? (
        <ul className="flex flex-col gap-1">
          {results.products.map((product) => (
            <li key={product.id} className="flex flex-wrap items-center justify-between gap-2">
              <Text as="span" size="sm">
                {product.brand === null ? product.name : `${product.name} · ${product.brand}`}
              </Text>
              <div className="flex items-center gap-2">
                <PriceTag priceCents={product.priceCents} unitPrice={product.unitPrice} />
                <Button
                  size="sm"
                  aria-label={t('receipts.line.pick', { name: product.name })}
                  onClick={() => {
                    onPick(product);
                  }}
                >
                  {t('receipts.line.accept')}
                </Button>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
