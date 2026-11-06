import { createI18n } from '@/shared/i18n/i18n';

const missingKeys: string[] = [];

export const testI18n = createI18n({
  onMissingKey: (key) => {
    missingKeys.push(key);
  },
});

/** Returns the keys rendered without a translation since the last call, and forgets them. */
export function takeMissingKeys(): string[] {
  return missingKeys.splice(0);
}
