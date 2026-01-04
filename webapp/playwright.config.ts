import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:8001',
    trace: 'on',
    screenshot: 'only-on-failure'
  },
  timeout: 60000, // Increase test timeout to 60 seconds
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    }
  ],
  webServer: {
    command: 'yarn dev',
    url: 'http://localhost:8001',
    reuseExistingServer: !process.env.CI,
    timeout: 120000
  }
})
