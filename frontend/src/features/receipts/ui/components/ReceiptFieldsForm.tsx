import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';

import type { ReceiptChanges } from '../../application/ReceiptsApi';
import { centsFromEuros, eurosFromCents } from '../../domain/receipt';

interface ReceiptFieldsFormProps {
  date: string | null;
  totalCents: number | null;
  /** The last day it may be, today in Madrid: the date is never in the future (PD-66). */
  maxDate: string;
  pending: boolean;
  onSave: (changes: ReceiptChanges) => void;
}

/** The date and the total paid of a receipt (PD-66). Fields left empty are not sent. */
export function ReceiptFieldsForm({
  date,
  totalCents,
  maxDate,
  pending,
  onSave,
}: ReceiptFieldsFormProps) {
  const { t } = useTranslation();
  const id = useId();
  const [dateText, setDateText] = useState(date ?? '');
  const [totalText, setTotalText] = useState(totalCents === null ? '' : eurosFromCents(totalCents));
  const [invalidTotal, setInvalidTotal] = useState(false);

  return (
    <form
      noValidate
      className="flex flex-wrap items-end gap-3"
      onSubmit={(event) => {
        event.preventDefault();
        const changes: ReceiptChanges = {};
        if (dateText !== '') {
          changes.date = dateText;
        }
        if (totalText.trim() !== '') {
          const cents = centsFromEuros(totalText);
          if (cents === null) {
            setInvalidTotal(true);
            return;
          }
          changes.totalCents = cents;
        }
        setInvalidTotal(false);
        onSave(changes);
      }}
    >
      <div className="flex flex-col gap-1">
        <Label htmlFor={`${id}-date`}>{t('receipts.review.date')}</Label>
        <Input
          id={`${id}-date`}
          type="date"
          max={maxDate}
          value={dateText}
          onChange={(event) => {
            setDateText(event.target.value);
          }}
        />
      </div>
      <div className="flex flex-col gap-1">
        <Label htmlFor={`${id}-total`}>{t('receipts.review.total')}</Label>
        <Input
          id={`${id}-total`}
          inputMode="decimal"
          invalid={invalidTotal}
          value={totalText}
          onChange={(event) => {
            setTotalText(event.target.value);
          }}
        />
      </div>
      <Button type="submit" variant="secondary" disabled={pending}>
        {t('receipts.review.save')}
      </Button>
      {invalidTotal ? <Alert>{t('receipts.review.errors.amount')}</Alert> : null}
    </form>
  );
}
