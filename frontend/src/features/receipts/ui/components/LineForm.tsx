import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Select } from '@/shared/ui/atoms/Select/Select';

import type { NewLine } from '../../application/ReceiptsApi';
import {
  centsFromEuros,
  eurosFromCents,
  inputFromQuantity,
  quantityFromInput,
  type LineKind,
  type QuantityUnit,
  type ReceiptLine,
} from '../../domain/receipt';

interface LineFormProps {
  /** The form's accessible name, and the label of its submit button unless `submitLabel` is given. */
  name: string;
  submitLabel?: string;
  /** A line to edit; its kind stays. Without it the form adds a line and offers both kinds. */
  line?: ReceiptLine;
  pending: boolean;
  onSubmit: (line: NewLine, reset: () => void) => void;
  onCancel?: () => void;
}

/** A product line (text, units or kg, amount) or a discount line (text, amount) (PD-66). */
export function LineForm({ name, submitLabel, line, pending, onSubmit, onCancel }: LineFormProps) {
  const { t } = useTranslation();
  const id = useId();
  const [kind, setKind] = useState<LineKind>(line?.kind ?? 'product');
  const [text, setText] = useState(line?.text ?? '');
  const [unit, setUnit] = useState<QuantityUnit>(line?.quantity?.unit ?? 'units');
  const [quantity, setQuantity] = useState(
    line?.quantity === undefined || line.quantity === null ? '1' : inputFromQuantity(line.quantity),
  );
  const [amount, setAmount] = useState(line === undefined ? '' : eurosFromCents(line.amountCents));
  const [error, setError] = useState<'amount' | 'quantity' | null>(null);

  const reset = () => {
    setText('');
    setQuantity('1');
    setAmount('');
  };

  return (
    <form
      noValidate
      aria-label={name}
      className="flex flex-wrap items-end gap-3"
      onSubmit={(event) => {
        event.preventDefault();
        const cents = centsFromEuros(amount);
        const parsed = kind === 'product' ? quantityFromInput(unit, quantity) : null;
        if (kind === 'product' && parsed === null) {
          setError('quantity');
          return;
        }
        if (cents === null) {
          setError('amount');
          return;
        }
        setError(null);
        const next: NewLine = { kind, text, amountCents: cents };
        if (parsed !== null) {
          next.unit = parsed.unit;
          next.quantity = parsed.value;
        }
        onSubmit(next, reset);
      }}
    >
      {line === undefined ? (
        <div className="flex flex-col gap-1">
          <Label htmlFor={`${id}-kind`}>{t('receipts.line.kind')}</Label>
          <Select
            id={`${id}-kind`}
            size="sm"
            value={kind}
            onChange={(event) => {
              setKind(event.target.value === 'discount' ? 'discount' : 'product');
            }}
          >
            <option value="product">{t('receipts.line.product')}</option>
            <option value="discount">{t('receipts.line.discount')}</option>
          </Select>
        </div>
      ) : null}
      <div className="flex min-w-40 flex-1 flex-col gap-1">
        <Label htmlFor={`${id}-text`}>{t('receipts.line.text')}</Label>
        <Input
          id={`${id}-text`}
          required
          maxLength={120}
          value={text}
          onChange={(event) => {
            setText(event.target.value);
          }}
        />
      </div>
      {kind === 'product' ? (
        <>
          <div className="flex w-24 flex-col gap-1">
            <Label htmlFor={`${id}-quantity`}>{t('receipts.line.quantity')}</Label>
            <Input
              id={`${id}-quantity`}
              inputMode="decimal"
              invalid={error === 'quantity'}
              value={quantity}
              onChange={(event) => {
                setQuantity(event.target.value);
              }}
            />
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor={`${id}-unit`}>{t('receipts.line.unit')}</Label>
            <Select
              id={`${id}-unit`}
              size="sm"
              value={unit}
              onChange={(event) => {
                setUnit(event.target.value === 'kg' ? 'kg' : 'units');
              }}
            >
              <option value="units">{t('receipts.quantity.units')}</option>
              <option value="kg">{t('receipts.quantity.kg')}</option>
            </Select>
          </div>
        </>
      ) : null}
      <div className="flex w-28 flex-col gap-1">
        <Label htmlFor={`${id}-amount`}>{t('receipts.line.amount')}</Label>
        <Input
          id={`${id}-amount`}
          inputMode="decimal"
          invalid={error === 'amount'}
          value={amount}
          onChange={(event) => {
            setAmount(event.target.value);
          }}
        />
      </div>
      <div className="flex gap-2">
        <Button type="submit" size="sm" disabled={pending}>
          {submitLabel ?? name}
        </Button>
        {onCancel === undefined ? null : (
          <Button size="sm" variant="secondary" onClick={onCancel}>
            {t('receipts.line.cancel')}
          </Button>
        )}
      </div>
      {error === null ? null : <Alert>{t(`receipts.review.errors.${error}`)}</Alert>}
    </form>
  );
}
