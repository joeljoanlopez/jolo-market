import type { ThemePreferenceStore } from '@/features/theme/application/ThemePreferenceStore';
import type { ThemePreference } from '@/features/theme/domain/theme';

export class InMemoryThemePreferenceStore implements ThemePreferenceStore {
  constructor(public stored: ThemePreference = 'system') {}

  read(): ThemePreference {
    return this.stored;
  }

  write(preference: ThemePreference): void {
    this.stored = preference;
  }
}
