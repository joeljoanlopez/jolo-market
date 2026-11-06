import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterAll, afterEach, beforeAll } from 'vitest';

import { takeMissingKeys } from './i18n';
import { server } from './server';

beforeAll(() => {
  server.listen({ onUnhandledFrame: 'error' });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();

  // Any component that rendered a key missing from es.json fails its test.
  const missing = takeMissingKeys();
  if (missing.length > 0) {
    throw new Error(`Missing translation keys: ${missing.join(', ')}`);
  }
});

afterAll(() => {
  server.close();
});
