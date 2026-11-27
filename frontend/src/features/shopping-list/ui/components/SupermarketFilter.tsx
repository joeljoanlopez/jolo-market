import { useTranslation } from 'react-i18next';

import { formatPrice } from '@/features/catalog/domain/format';
import { SupermarketBadge } from '@/features/catalog/ui/components/SupermarketBadge';
import { Button } from '@/shared/ui/atoms/Button/Button';

import type { SupermarketChip } from '../../domain/supermarketFilter';

interface SupermarketFilterProps {
  chips: SupermarketChip[];
  /** The chosen supermarket; `null` is "Todos". */
  selected: string | null;
  onSelect: (supermarketId: string | null) => void;
}

/** "Todos", then one chip per supermarket with its estimated total; the chosen one is pressed (PD-84). */
export function SupermarketFilter({ chips, selected, onSelect }: SupermarketFilterProps) {
  const { t } = useTranslation();

  return (
    <div role="group" aria-label={t('lists.filter.label')} className="flex flex-wrap gap-2">
      <Button
        size="sm"
        variant={selected === null ? 'primary' : 'secondary'}
        aria-pressed={selected === null}
        onClick={() => {
          onSelect(null);
        }}
      >
        {t('lists.filter.all')}
      </Button>
      {chips.map((chip) => (
        <Button
          key={chip.supermarketId}
          size="sm"
          variant={chip.supermarketId === selected ? 'primary' : 'secondary'}
          aria-pressed={chip.supermarketId === selected}
          className="gap-2"
          onClick={() => {
            onSelect(chip.supermarketId);
          }}
        >
          <SupermarketBadge supermarket={{ id: chip.supermarketId, name: chip.supermarketName }} />
          <span>{formatPrice(chip.totalCents)}</span>
        </Button>
      ))}
    </div>
  );
}
