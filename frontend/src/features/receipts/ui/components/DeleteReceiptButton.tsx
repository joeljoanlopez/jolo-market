import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

/** "Borrar" asks first, in place (PD-42, PD-65). */
export function DeleteReceiptButton({
  pending,
  onDelete,
}: {
  pending: boolean;
  onDelete: () => void;
}) {
  const { t } = useTranslation();
  const [confirming, setConfirming] = useState(false);

  if (!confirming) {
    return (
      <div>
        <Button
          variant="danger"
          disabled={pending}
          onClick={() => {
            setConfirming(true);
          }}
        >
          {t('receipts.review.delete')}
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      <Text>{t('receipts.review.confirmDelete')}</Text>
      <div className="flex flex-wrap gap-2">
        <Button
          variant="danger"
          disabled={pending}
          onClick={() => {
            onDelete();
            setConfirming(false);
          }}
        >
          {t('receipts.review.confirmDeleteAction')}
        </Button>
        <Button
          variant="secondary"
          onClick={() => {
            setConfirming(false);
          }}
        >
          {t('receipts.review.cancel')}
        </Button>
      </div>
    </div>
  );
}
