import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

import type { SectionId } from '../../domain/sections';

/** The header of a group of pending items: only the section name (PD-93). */
export function SectionHeader({ section }: { section: SectionId }) {
  const { t } = useTranslation();
  return (
    <Text as="h2" size="sm" weight="bold" tone="muted">
      {t(`lists.sections.${section}`)}
    </Text>
  );
}
