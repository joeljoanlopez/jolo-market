import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate, useParams } from 'react-router';

import { useProductSearch } from '@/features/catalog/application/useProductSearch';
import { formatPrice } from '@/features/catalog/domain/format';
import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { useReceiptsApi } from '../../application/receiptsApiContext';
import { useReceipt } from '../../application/useReceipt';
import { useReceiptEditing, type ReceiptEditing } from '../../application/useReceiptEditing';
import { hasDifference, type Receipt, type ReceiptLine } from '../../domain/receipt';
import { DeleteReceiptButton } from '../components/DeleteReceiptButton';
import { LineForm } from '../components/LineForm';
import { ProductPicker, type PickerResults } from '../components/ProductPicker';
import { ReceiptFieldsForm } from '../components/ReceiptFieldsForm';
import { ReceiptLineRow } from '../components/ReceiptLineRow';

interface ReceiptPageContainerProps {
  /** Shown to someone who may not see the receipt (PD-63); the routes give the app's not-found page. */
  notFound: ReactNode;
}

/** `/lists/:listId/receipts/:receiptId`: the review of a receipt (PD-65 to PD-67). */
export function ReceiptPageContainer({ notFound }: ReceiptPageContainerProps) {
  const { t } = useTranslation();
  const { listId = '', receiptId = '' } = useParams();
  const state = useReceipt(listId, receiptId);

  switch (state.status) {
    case 'loading':
      return (
        <div role="status">
          <Text tone="muted">{t('receipts.review.loading')}</Text>
        </div>
      );
    case 'notFound':
      return notFound;
    case 'error':
      return (
        <div className="flex flex-col items-start gap-3">
          <Text>{t('receipts.review.loadFailed')}</Text>
          <Button variant="secondary" onClick={state.retry}>
            {t('receipts.review.retry')}
          </Button>
        </div>
      );
    case 'success':
      return <ReceiptReview listId={listId} receipt={state.receipt} />;
  }
}

function ReceiptReview({ listId, receipt }: { listId: string; receipt: Receipt }) {
  const { t } = useTranslation();
  const api = useReceiptsApi();
  const navigate = useNavigate();
  const editing = useReceiptEditing(listId, receipt.id);
  const listPath = `/lists/${encodeURIComponent(listId)}`;

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <Link to={listPath} className="font-sans text-sm text-primary underline">
          {t('receipts.review.back')}
        </Link>
        <Text as="h1" size="2xl" weight="bold">
          {t('receipts.review.title', { supermarket: receipt.supermarketName })}
        </Text>
        <div className="flex flex-wrap gap-2">
          {receipt.status === 'reading' ? null : (
            <Text as="span" weight="medium">
              {receipt.status === 'confirmed'
                ? t('receipts.review.confirmedStatus')
                : t('receipts.review.draftStatus')}
            </Text>
          )}
          <Text as="span" tone="muted">
            {t('receipts.review.uploadedBy', { username: receipt.uploader.username })}
          </Text>
        </div>
      </div>
      <img
        src={api.photoUrl(listId, receipt.id)}
        alt={t('receipts.review.photo')}
        className="max-h-screen w-full max-w-md rounded-lg border border-border object-contain"
      />
      {receipt.status === 'reading' ? (
        <div role="status">
          <Text tone="muted">{t('receipts.review.reading')}</Text>
        </div>
      ) : (
        <ReceiptEditor receipt={receipt} editing={editing} />
      )}
      <DeleteReceiptButton
        pending={editing.pending}
        onDelete={() => {
          editing.deleteReceipt(() => {
            void navigate(listPath);
          });
        }}
      />
    </section>
  );
}

function ReceiptEditor({ receipt, editing }: { receipt: Receipt; editing: ReceiptEditing }) {
  const { t } = useTranslation();
  const [editingLine, setEditingLine] = useState<string | null>(null);
  const [pickingLine, setPickingLine] = useState<string | null>(null);

  const row = (line: ReceiptLine) => (
    <ReceiptLineRow
      key={line.id}
      line={line}
      pending={editing.pending}
      editing={editingLine === line.id}
      onAccept={(productId) => {
        editing.link(line.id, productId);
      }}
      onChange={() => {
        setPickingLine(line.id);
      }}
      onLeaveUnlinked={() => {
        editing.unlink(line.id);
      }}
      onEdit={() => {
        setEditingLine(line.id);
      }}
      onRemove={() => {
        editing.removeLine(line.id);
      }}
    >
      {editingLine === line.id ? (
        <LineForm
          name={t('receipts.line.edit')}
          submitLabel={t('receipts.line.save')}
          line={line}
          pending={editing.pending}
          onSubmit={(changes) => {
            editing.updateLine(line.id, {
              text: changes.text,
              amountCents: changes.amountCents,
              ...(changes.unit === undefined ? {} : { unit: changes.unit }),
              ...(changes.quantity === undefined ? {} : { quantity: changes.quantity }),
            });
            setEditingLine(null);
          }}
          onCancel={() => {
            setEditingLine(null);
          }}
        />
      ) : null}
      {pickingLine === line.id && editingLine !== line.id ? (
        <PickerContainer
          supermarketId={receipt.supermarketId}
          supermarketName={receipt.supermarketName}
          onPick={(productId) => {
            editing.link(line.id, productId);
            setPickingLine(null);
          }}
          onClose={() => {
            setPickingLine(null);
          }}
        />
      ) : null}
    </ReceiptLineRow>
  );

  return (
    <div className="flex flex-col gap-6">
      <ReceiptFieldsForm
        key={`${receipt.date ?? ''}|${String(receipt.totalCents)}`}
        date={receipt.date}
        totalCents={receipt.totalCents}
        maxDate={todayInMadrid()}
        pending={editing.pending}
        onSave={editing.update}
      />
      <div className="flex flex-col gap-3">
        <Text as="h2" size="lg" weight="bold">
          {t('receipts.review.lines')}
        </Text>
        {receipt.lines.length === 0 ? (
          <Text tone="muted">{t('receipts.review.noLines')}</Text>
        ) : null}
        <ul aria-label={t('receipts.review.lines')} className="flex flex-col gap-2">
          {receipt.lines.map(row)}
        </ul>
        <LineForm
          name={t('receipts.line.add')}
          pending={editing.pending}
          onSubmit={(line, reset) => {
            editing.addLine(line, reset);
          }}
        />
      </div>
      {hasDifference(receipt) ? (
        <Text tone="danger">
          {t('receipts.review.difference', {
            amount: formatPrice(Math.abs(receipt.differenceCents ?? 0)),
          })}
        </Text>
      ) : null}
      {editing.error === null ? null : (
        <Alert>{t(`receipts.review.errors.${editing.error}`)}</Alert>
      )}
      <div>
        <Button
          disabled={editing.pending}
          onClick={() => {
            editing.confirm();
          }}
        >
          {receipt.status === 'confirmed'
            ? t('receipts.review.confirmAgain')
            : t('receipts.review.confirm')}
        </Button>
      </div>
    </div>
  );
}

interface PickerContainerProps {
  supermarketId: string;
  supermarketName: string;
  onPick: (productId: string) => void;
  onClose: () => void;
}

function PickerContainer({
  supermarketId,
  supermarketName,
  onPick,
  onClose,
}: PickerContainerProps) {
  const [text, setText] = useState('');
  const search = useProductSearch({ text, supermarket: supermarketId });
  const results: PickerResults =
    search.status === 'success'
      ? { status: 'success', products: search.data.items }
      : { status: search.status };

  return (
    <ProductPicker
      supermarketName={supermarketName}
      text={text}
      results={results}
      onTextChange={setText}
      onPick={(product) => {
        onPick(product.id);
      }}
      onClose={onClose}
    />
  );
}

/** Today in Madrid, as the API counts receipt days (PD-66). */
function todayInMadrid(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Madrid' }).format(new Date());
}
