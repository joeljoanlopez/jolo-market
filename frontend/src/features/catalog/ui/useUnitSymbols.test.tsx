import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';

import { createI18n } from '../../../shared/i18n/i18n';
import { useUnitSymbols } from './useUnitSymbols';

describe('useUnitSymbols', () => {
  it('reads the unit symbols from the translation files', () => {
    const onMissingKey = vi.fn();
    const i18n = createI18n({ onMissingKey });
    const wrapper = ({ children }: { children: ReactNode }) => (
      <I18nextProvider i18n={i18n}>{children}</I18nextProvider>
    );

    const { result } = renderHook(() => useUnitSymbols(), { wrapper });

    expect(result.current).toEqual({ kg: 'kg', l: 'L', unit: 'ud' });
    expect(onMissingKey).not.toHaveBeenCalled();
  });
});
