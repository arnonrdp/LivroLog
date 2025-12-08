import { defineConfig, devices } from '@playwright/test'

/**
 * Production Playwright Configuration
 *
 * Run with: npx playwright test --config=playwright.production.config.ts
 * Requires: PLAYWRIGHT_USER_PASSWORD environment variable
 */
export default defineConfig({
  testDir: './e2e',
  testMatch: 'production.spec.ts',
  fullyParallel: false,
  forbidOnly: true,
  retries: 1,
  workers: 1,
  reporter: [['html', { outputFolder: 'playwright-report-production' }]],
  use: {
    baseURL: 'https://livrolog.com',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'on-first-retry'
  },
  timeout: 90000,
  expect: {
    timeout: 15000
  },
  projects: [
    {
      name: 'production-chromium',
      use: { ...devices['Desktop Chrome'] }
    }
  ]
})
