// End-to-end tests against a running stack (local `docker compose up` or a deployed URL).
//   BASE_URL=http://localhost:8080 E2E_ADMIN_PASSWORD=... E2E_STAFF_PASSWORD=... npx playwright test
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests',
  // Journeys build on each other (an enquiry created by the visitor is handled by staff).
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  timeout: 60_000,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  outputDir: 'test-results',
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    locale: 'en-GB',
    timezoneId: 'Europe/London',
    // For a local production test with Caddy's self-signed localhost certificate.
    ignoreHTTPSErrors: Boolean(process.env.E2E_INSECURE_TLS),
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] }, testIgnore: /mobile\.spec\.js/ },
    { name: 'mobile', use: { ...devices['Pixel 7'] }, testMatch: /mobile\.spec\.js/ },
  ],
})
