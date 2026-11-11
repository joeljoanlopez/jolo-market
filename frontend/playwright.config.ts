import { defineConfig, devices } from '@playwright/test';

import { baseURL, storageStatePath } from './e2e/support/session';

// Runs against the Docker stack (`docker compose up`), served by nginx.
export default defineConfig({
  testDir: './e2e',
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL,
    trace: 'retain-on-failure',
  },
  projects: [
    // E3.29 runs first: on a fresh instance it is the one that goes through /setup.
    {
      name: 'onboarding',
      testMatch: /onboarding\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    // Creates (or reuses) a logged-in admin, so the other tests start authenticated.
    { name: 'session', testMatch: /session\.setup\.ts/, dependencies: ['onboarding'] },
    {
      name: 'chromium',
      testIgnore: /onboarding\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], storageState: storageStatePath },
      dependencies: ['session'],
    },
  ],
});
