import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Select } from '@/shared/ui/atoms/Select/Select';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { photoProblem } from '../../domain/receipt';

interface UploadReceiptFormProps {
  /** The enabled supermarkets (PD-64). */
  supermarkets: { id: string; name: string }[];
  pending: boolean;
  /** Why the last upload failed, already in Spanish. */
  error: string | null;
  onUpload: (supermarketId: string, photo: File) => void;
}

type LocalError = 'missing' | 'type' | 'size';

/** One photo of a receipt and the supermarket it is from (PD-64, PD-68), checked before uploading. */
export function UploadReceiptForm({
  supermarkets,
  pending,
  error,
  onUpload,
}: UploadReceiptFormProps) {
  const { t } = useTranslation();
  const id = useId();
  const [supermarketId, setSupermarketId] = useState('');
  const [photo, setPhoto] = useState<File | null>(null);
  const [localError, setLocalError] = useState<LocalError | null>(null);

  const submit = () => {
    if (supermarketId === '' || photo === null) {
      setLocalError('missing');
      return;
    }
    const problem = photoProblem(photo);
    if (problem !== null) {
      setLocalError(problem);
      return;
    }
    setLocalError(null);
    onUpload(supermarketId, photo);
  };

  return (
    <form
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        submit();
      }}
      className="flex flex-col gap-3"
      aria-labelledby={`${id}-title`}
    >
      <span id={`${id}-title`}>
        <Text as="h3" weight="bold">
          {t('receipts.upload.title')}
        </Text>
      </span>
      <div className="flex flex-col gap-1">
        <Label htmlFor={`${id}-supermarket`}>{t('receipts.upload.supermarket')}</Label>
        <Select
          id={`${id}-supermarket`}
          value={supermarketId}
          onChange={(event) => {
            setSupermarketId(event.target.value);
          }}
        >
          <option value="">{t('receipts.upload.chooseSupermarket')}</option>
          {supermarkets.map((supermarket) => (
            <option key={supermarket.id} value={supermarket.id}>
              {supermarket.name}
            </option>
          ))}
        </Select>
      </div>
      <div className="flex flex-col gap-1">
        <Label htmlFor={`${id}-photo`}>{t('receipts.upload.photo')}</Label>
        <Input
          id={`${id}-photo`}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          aria-describedby={`${id}-photo-hint`}
          invalid={localError === 'type' || localError === 'size'}
          className="h-auto py-2"
          onChange={(event) => {
            setPhoto(event.target.files?.[0] ?? null);
            setLocalError(null);
          }}
        />
        <span id={`${id}-photo-hint`}>
          <Text as="span" size="sm" tone="muted">
            {t('receipts.upload.photoHint')}
          </Text>
        </span>
      </div>
      {localError === null ? null : <Alert>{t(`receipts.upload.errors.${localError}`)}</Alert>}
      {error === null ? null : <Alert>{error}</Alert>}
      <div>
        <Button type="submit" disabled={pending}>
          {pending ? t('receipts.upload.uploading') : t('receipts.upload.submit')}
        </Button>
      </div>
    </form>
  );
}
