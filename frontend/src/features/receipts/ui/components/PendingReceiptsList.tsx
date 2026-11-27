import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { SupermarketBadge } from '@/features/catalog/ui/components/SupermarketBadge';
import { Text } from '@/shared/ui/atoms/Text/Text';

import type { ReceiptSummary } from '../../domain/receipt';

interface PendingReceiptsListProps {
  receipts: ReceiptSummary[];
  reviewHref: (receiptId: string) => string;
}

/** Receipts still being read, and drafts waiting for review; they do not count as spending (PD-70). */
export function PendingReceiptsList({ receipts, reviewHref }: PendingReceiptsListProps) {
  const { t } = useTranslation();
  const titleId = useId();

  if (receipts.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-col gap-2">
      <span id={titleId}>
        <Text as="h3" weight="bold">
          {t('receipts.pending.title')}
        </Text>
      </span>
      <ul aria-labelledby={titleId} className="flex flex-col gap-2">
        {receipts.map((receipt) => (
          <li
            key={receipt.id}
            className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-background p-3"
          >
            <div className="flex flex-wrap items-center gap-2">
              <SupermarketBadge
                supermarket={{ id: receipt.supermarketId, name: receipt.supermarketName }}
              />
              <Text as="span" size="sm" tone="muted">
                {receipt.status === 'reading'
                  ? t('receipts.pending.reading')
                  : t('receipts.pending.draft')}
              </Text>
            </div>
            <Link
              to={reviewHref(receipt.id)}
              className="font-sans text-sm font-medium text-primary underline"
              aria-label={t('receipts.pending.review', {
                supermarket: receipt.supermarketName,
                username: receipt.uploader.username,
              })}
            >
              {receipt.uploader.username}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
