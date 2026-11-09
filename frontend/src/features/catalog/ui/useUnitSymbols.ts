import { useTranslation } from 'react-i18next';

import type { UnitSymbols } from '../domain/format';

/** The unit symbols of the current language, for formatUnitPrice and formatPackaging. */
export function useUnitSymbols(): UnitSymbols {
  const { t } = useTranslation();

  return { kg: t('catalog.units.kg'), l: t('catalog.units.l'), unit: t('catalog.units.unit') };
}
