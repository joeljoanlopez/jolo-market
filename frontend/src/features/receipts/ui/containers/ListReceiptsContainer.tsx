import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { useSupermarkets } from '@/features/catalog/application/useSupermarkets';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { useListReceipts, useListSpending } from '../../application/useListReceipts';
import { useUploadReceipt } from '../../application/useUploadReceipt';
import { isReadingAny } from '../../domain/receipt';
import { ConfirmedReceiptsList } from '../components/ConfirmedReceiptsList';
import { MonthlySpending } from '../components/MonthlySpending';
import { PendingReceiptsList } from '../components/PendingReceiptsList';
import { UploadReceiptForm } from '../components/UploadReceiptForm';

/** The receipts section of a list page: upload, pending, confirmed and monthly spending (PD-63 to PD-70). */
export function ListReceiptsContainer({ listId }: { listId: string }) {
  const { t } = useTranslation();
  const titleId = useId();
  const receipts = useListReceipts(listId);
  const reading = receipts.status === 'success' && isReadingAny(receipts.receipts.pending);
  const spending = useListSpending(listId, reading);
  const upload = useUploadReceipt(listId);
  const supermarkets = useSupermarkets();
  const reviewHref = (receiptId: string) =>
    `/lists/${encodeURIComponent(listId)}/receipts/${encodeURIComponent(receiptId)}`;

  return (
    <section aria-labelledby={titleId} className="flex flex-col gap-4">
      <span id={titleId}>
        <Text as="h2" size="lg" weight="bold">
          {t('receipts.section.title')}
        </Text>
      </span>
      <UploadReceiptForm
        supermarkets={supermarkets}
        pending={upload.pending}
        error={upload.error === null ? null : t(`receipts.upload.errors.${upload.error}`)}
        onUpload={(supermarketId, photo) => {
          upload.upload(supermarketId, photo);
        }}
      />
      {receipts.status === 'loading' ? (
        <div role="status">
          <Text tone="muted">{t('receipts.section.loading')}</Text>
        </div>
      ) : null}
      {receipts.status === 'error' || receipts.status === 'notFound' ? (
        <div className="flex flex-col items-start gap-2">
          <Text>{t('receipts.section.loadFailed')}</Text>
          {receipts.status === 'error' ? (
            <Button variant="secondary" onClick={receipts.retry}>
              {t('receipts.section.retry')}
            </Button>
          ) : null}
        </div>
      ) : null}
      {receipts.status === 'success' ? (
        <>
          <PendingReceiptsList receipts={receipts.receipts.pending} reviewHref={reviewHref} />
          <ConfirmedReceiptsList receipts={receipts.receipts.confirmed} reviewHref={reviewHref} />
        </>
      ) : null}
      {spending.status === 'success' ? <MonthlySpending months={spending.months} /> : null}
    </section>
  );
}
