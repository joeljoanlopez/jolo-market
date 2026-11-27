import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { formatPrice } from '@/features/catalog/domain/format';
import { SupermarketBadge } from '@/features/catalog/ui/components/SupermarketBadge';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatReceiptDate, type ReceiptSummary } from '../../domain/receipt';

interface ConfirmedReceiptsListProps {
  /** Newest first. */
  receipts: ReceiptSummary[];
  reviewHref: (receiptId: string) => string;
}

/** PD-70: the confirmed receipts of the list, with date, supermarket, total and uploader. */
export function ConfirmedReceiptsList({ receipts, reviewHref }: ConfirmedReceiptsListProps) {
  const { t } = useTranslation();
  const titleId = useId();

  return (
    <div className="flex flex-col gap-2">
      <span id={titleId}>
        <Text as="h3" weight="bold">
          {t('receipts.confirmed.title')}
        </Text>
      </span>
      {receipts.length === 0 ? (
        <Text size="sm" tone="muted">
          {t('receipts.confirmed.empty')}
        </Text>
      ) : (
        <ul aria-labelledby={titleId} className="flex flex-col gap-2">
          {receipts.map((receipt) => {
            const date = receipt.date === null ? '' : formatReceiptDate(receipt.date);
            return (
              <li
                key={receipt.id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-background p-3"
              >
                <div className="flex flex-col items-start gap-1">
                  <Link
                    to={reviewHref(receipt.id)}
                    className="font-sans font-medium text-primary underline"
                    aria-label={t('receipts.confirmed.open', {
                      supermarket: receipt.supermarketName,
                      date,
                    })}
                  >
                    {date}
                  </Link>
                  <SupermarketBadge
                    supermarket={{ id: receipt.supermarketId, name: receipt.supermarketName }}
                  />
                  <Text as="span" size="sm" tone="muted">
                    {t('receipts.confirmed.uploadedBy', { username: receipt.uploader.username })}
                  </Text>
                </div>
                <Text as="span" size="lg" weight="bold">
                  {formatPrice(receipt.totalCents ?? 0)}
                </Text>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
