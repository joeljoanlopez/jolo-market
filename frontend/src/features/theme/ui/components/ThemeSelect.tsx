import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { Label } from '@/shared/ui/atoms/Label/Label';
import { Select } from '@/shared/ui/atoms/Select/Select';

import { parseThemePreference, themePreferences, type ThemePreference } from '../../domain/theme';

interface ThemeSelectProps {
  value: ThemePreference;
  onChange: (preference: ThemePreference) => void;
}

/** The "Tema" dropdown of the header: Sistema, Claro or Oscuro (PD-76). */
export function ThemeSelect({ value, onChange }: ThemeSelectProps) {
  const { t } = useTranslation();
  const id = useId();

  return (
    <div className="flex items-center gap-2">
      <Label htmlFor={id}>{t('theme.label')}</Label>
      <Select
        id={id}
        className="h-8 w-auto px-2 text-sm"
        value={value}
        onChange={(event) => {
          onChange(parseThemePreference(event.target.value));
        }}
      >
        {themePreferences.map((preference) => (
          <option key={preference} value={preference}>
            {t(`theme.options.${preference}`)}
          </option>
        ))}
      </Select>
    </div>
  );
}
