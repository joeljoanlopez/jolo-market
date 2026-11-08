import i18next, { type i18n } from 'i18next';
import { initReactI18next } from 'react-i18next';

import es from './locales/es.json';

export interface I18nOptions {
  /** Called for every key rendered without a translation. Tests use it to fail fast. */
  onMissingKey?: (key: string) => void;
}

export function createI18n({ onMissingKey }: I18nOptions = {}): i18n {
  const instance = i18next.createInstance();

  void instance.use(initReactI18next).init({
    lng: 'es',
    fallbackLng: 'es',
    resources: { es: { translation: es } },
    initAsync: false,
    // React already escapes rendered values.
    interpolation: { escapeValue: false },
    saveMissing: onMissingKey !== undefined,
    missingKeyHandler: (_languages, _namespace, key) => {
      onMissingKey?.(key);
    },
  });

  return instance;
}
