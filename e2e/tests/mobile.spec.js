import { expect, test } from '@playwright/test'
import { STAFF, shots, signIn } from './helpers.js'

test('mobile: website menu is keyboard/touch friendly and the dashboard stacks tables', async ({ page }) => {
  await page.goto('/')
  const toggle = page.getByRole('button', { name: 'Menu' })
  await expect(toggle).toHaveAttribute('aria-expanded', 'false')
  await toggle.click()
  await expect(toggle).toHaveAttribute('aria-expanded', 'true')
  await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Clinics' })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(toggle).toHaveAttribute('aria-expanded', 'false')

  // No horizontal scrolling at phone width.
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
  expect(overflow).toBeLessThanOrEqual(0)
  await page.screenshot(shots('website-mobile'))

  await signIn(page, STAFF)
  await page.goto('/dashboard/enquiries')
  await expect(page.locator('table.responsive td[data-label="Reference"]').first()).toBeVisible()
  const dashOverflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
  expect(dashOverflow).toBeLessThanOrEqual(0)
  await page.screenshot(shots('dashboard-mobile'))
})
