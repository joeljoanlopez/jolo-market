import { render, screen } from '@testing-library/react';
import { I18nextProvider, useTranslation } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';

import { createI18n } from './i18n';
import es from './locales/es.json';

function Label({ translationKey }: { translationKey: string }) {
  const { t } = useTranslation();
  return <span>{t(translationKey)}</span>;
}

describe('i18n', () => {
  it('renders Spanish copy from es.json', () => {
    render(
      <I18nextProvider i18n={createI18n()}>
        <Label translationKey="app.name" />
      </I18nextProvider>,
    );

    expect(screen.getByText(es.app.name)).toBeInTheDocument();
  });

  it('reports a key that is missing from es.json', () => {
    const onMissingKey = vi.fn();

    render(
      <I18nextProvider i18n={createI18n({ onMissingKey })}>
        <Label translationKey="this.key.does.not.exist" />
      </I18nextProvider>,
    );

    expect(onMissingKey).toHaveBeenCalledWith('this.key.does.not.exist');
  });

  it('does not report keys that exist', () => {
    const onMissingKey = vi.fn();

    render(
      <I18nextProvider i18n={createI18n({ onMissingKey })}>
        <Label translationKey="app.name" />
      </I18nextProvider>,
    );

    expect(onMissingKey).not.toHaveBeenCalled();
  });
});
