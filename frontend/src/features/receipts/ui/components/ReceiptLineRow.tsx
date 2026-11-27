import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { formatPrice } from '@/features/catalog/domain/format';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatLineQuantity, type LineProduct, type ReceiptLine } from '../../domain/receipt';

interface ReceiptLineRowProps {
  line: ReceiptLine;
  pending: boolean;
  /** Shown instead of the line while it is being edited, or under it while a product is picked. */
  children?: ReactNode;
  editing: boolean;
  onAccept: (productId: string) => void;
  onChange: () => void;
  onLeaveUnlinked: () => void;
  onEdit: () => void;
  onRemove: () => void;
}

/** One line of the review: what was printed, and for a product line its linked or suggested product (PD-67). */
export function ReceiptLineRow({
  line,
  pending,
  children,
  editing,
  onAccept,
  onChange,
  onLeaveUnlinked,
  onEdit,
  onRemove,
}: ReceiptLineRowProps) {
  const { t } = useTranslation();
  const symbols = { units: t('receipts.quantity.units'), kg: t('receipts.quantity.kg') };
  const product = (shown: LineProduct) => (
    <>
      <Text as="span" size="sm" weight="medium">
        {shown.name ?? t('receipts.line.unknownProduct')}
      </Text>
      {shown.name !== null && !shown.available ? (
        <Text as="span" size="sm" tone="muted">
          {t('receipts.line.unavailable')}
        </Text>
      ) : null}
    </>
  );

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border bg-background p-3">
      {editing ? (
        children
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex flex-wrap items-baseline gap-2">
              {line.kind === 'discount' ? (
                <Text as="span" size="sm" tone="muted">
                  {t('receipts.line.discount')}
                </Text>
              ) : null}
              <Text as="span" weight="medium">
                {line.text}
              </Text>
              {line.quantity === null ? null : (
                <Text as="span" size="sm" tone="muted">
                  {formatLineQuantity(line.quantity, symbols)}
                </Text>
              )}
            </div>
            <Text as="span" weight="bold">
              {line.kind === 'discount'
                ? t('receipts.line.discountAmount', { amount: formatPrice(line.amountCents) })
                : formatPrice(line.amountCents)}
            </Text>
          </div>
          {line.kind === 'product' ? (
            <div className="flex flex-wrap items-center gap-2">
              {line.linkedProduct !== null ? (
                <>
                  <Text as="span" size="sm" tone="muted">
                    {t('receipts.line.linked')}
                  </Text>
                  {product(line.linkedProduct)}
                </>
              ) : line.suggestedProduct !== null ? (
                <>
                  <Text as="span" size="sm" tone="muted">
                    {t('receipts.line.suggested')}
                  </Text>
                  {product(line.suggestedProduct)}
                </>
              ) : (
                <Text as="span" size="sm" tone="muted">
                  {t('receipts.line.unlinked')}
                </Text>
              )}
              {line.linkedProduct === null && line.suggestedProduct !== null ? (
                <Button
                  size="sm"
                  disabled={pending}
                  onClick={() => {
                    onAccept(line.suggestedProduct?.id ?? '');
                  }}
                >
                  {t('receipts.line.accept')}
                </Button>
              ) : null}
              <Button size="sm" variant="secondary" disabled={pending} onClick={onChange}>
                {t('receipts.line.change')}
              </Button>
              {line.linkedProduct !== null || line.suggestedProduct !== null ? (
                <Button size="sm" variant="secondary" disabled={pending} onClick={onLeaveUnlinked}>
                  {t('receipts.line.leaveUnlinked')}
                </Button>
              ) : null}
            </div>
          ) : null}
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="secondary" disabled={pending} onClick={onEdit}>
              {t('receipts.line.edit')}
            </Button>
            <Button size="sm" variant="secondary" disabled={pending} onClick={onRemove}>
              {t('receipts.line.remove')}
            </Button>
          </div>
          {children}
        </>
      )}
    </li>
  );
}
