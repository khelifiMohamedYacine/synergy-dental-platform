import AxeBuilder from '@axe-core/playwright'
import { expect } from '@playwright/test'

export const ADMIN = { email: 'admin@example.test', password: process.env.E2E_ADMIN_PASSWORD }
export const STAFF = { email: 'staff@example.test', password: process.env.E2E_STAFF_PASSWORD }

export async function signIn(page, user) {
  if (!user.password) throw new Error('Set E2E_ADMIN_PASSWORD / E2E_STAFF_PASSWORD')
  await page.goto('/dashboard/login')
  await page.getByLabel('Email').fill(user.email)
  await page.getByLabel('Password', { exact: true }).fill(user.password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  // The sidebar is collapsed on phones, so wait for the signed-in page content, not the nav.
  await expect(page.locator('main#main')).toBeVisible()
  await expect(page).not.toHaveURL(/\/login/)
}

/** Fails on serious/critical WCAG 2.x A/AA violations, with a readable summary. */
export async function expectAccessible(page, { exclude = [] } = {}) {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
  for (const selector of exclude) builder = builder.exclude(selector)
  const { violations } = await builder.analyze()
  const serious = violations.filter((v) => ['serious', 'critical'].includes(v.impact))
  const summary = serious.map((v) => `${v.id}: ${v.help} (${v.nodes.map((n) => n.target.join(' ')).join(', ')})`)
  expect(summary, 'accessibility violations').toEqual([])
}

export function inDays(days) {
  const d = new Date(Date.now() + days * 86400000)
  return d.toISOString().slice(0, 10)
}

export const shots = (name) => ({ path: `../docs/screenshots/${name}.png`, fullPage: false })
