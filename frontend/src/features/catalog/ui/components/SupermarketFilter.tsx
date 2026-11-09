import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { Label } from '@/shared/ui/atoms/Label/Label';
import { Select } from '@/shared/ui/atoms/Select/Select';

import type { Supermarket } from '../../domain/product';

interface SupermarketFilterProps {
  supermarkets: Supermarket[];
  /** Slug of the chosen supermarket; undefined means every supermarket. */
  value: string | undefined;
  onChange: (supermarket: string | undefined) => void;
}

export function SupermarketFilter({ supermarkets, value, onChange }: SupermarketFilterProps) {
  const { t } = useTranslation();
  const id = useId();

  return (
    <div className="flex flex-col gap-1">
      <Label htmlFor={id}>{t('catalog.search.supermarket')}</Label>
      <Select
        id={id}
        value={value ?? ''}
        onChange={(event) => {
          onChange(event.target.value === '' ? undefined : event.target.value);
        }}
      >
        <option value="">{t('catalog.search.allSupermarkets')}</option>
        {supermarkets.map((supermarket) => (
          <option key={supermarket.id} value={supermarket.id}>
            {supermarket.name}
          </option>
        ))}
      </Select>
    </div>
  );
}
