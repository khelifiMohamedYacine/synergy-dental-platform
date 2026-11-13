import { expect, test } from '@playwright/test'
import { ADMIN, STAFF, expectAccessible, inDays, shots, signIn } from './helpers.js'

const unique = Date.now().toString(36)
const visitor = {
  name: 'Avery Testwell', // letters only: digits are rejected by the name rule
  email: `avery.${unique}@example.test`,
  phone: '07700 900321',
}
let reference

test.describe.configure({ mode: 'serial' })

test('1. visitor requests an appointment on the website', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Confident smiles')
  await page.screenshot(shots('website-home'))
  await expectAccessible(page)

  await page.getByRole('link', { name: 'Treatments', exact: true }).first().click()
  await page.getByRole('link', { name: 'Teeth whitening' }).click()
  await expect(page.getByText('Price from')).toBeVisible()
  await page.getByRole('link', { name: 'Enquire about this' }).click()

  // Treatment is pre-selected from the link (US-01).
  await expect(page.getByLabel('Treatment')).toHaveValue('teeth-whitening')
  await expectAccessible(page)

  // Submitting empty shows a linked error summary, and the visitor's input is kept.
  await page.getByLabel(/^Full name/).fill(visitor.name)
  await page.getByRole('button', { name: 'Send request' }).click()
  const summary = page.getByRole('alert')
  await expect(summary).toContainText('Please correct the highlighted fields')
  await expect(page.getByLabel(/^Full name/)).toHaveValue(visitor.name)

  await page.getByLabel(/^Email/).fill(visitor.email)
  await page.getByLabel(/^Phone/).fill(visitor.phone)
  await page.getByLabel(/^Preferred clinic/).selectOption('riverside')
  await page.getByLabel('Treatment').selectOption('teeth-whitening')
  await page.getByLabel(/^Preferred date/).fill(inDays(14))
  await page.getByLabel('Preferred time').selectOption('AFTERNOON')
  await page.getByLabel('Message').fill('E2E test enquiry.')
  await page.getByRole('checkbox', { name: /I agree to be contacted/ }).check()
  await page.screenshot(shots('website-booking-form'))
  await page.getByRole('button', { name: 'Send request' }).click()

  await expect(page.getByRole('heading', { name: 'Thank you, Avery' })).toBeVisible()
  reference = (await page.locator('#sd-ref').textContent()).trim()
  expect(reference).toMatch(/^SD-[2-9A-HJKMNP-Z]{6}$/)
  expect(page.url()).not.toContain(visitor.email) // no personal data in the URL
  await page.screenshot(shots('website-confirmation'))
})

test('2. staff find the enquiry and move it through the workflow', async ({ page }) => {
  test.skip(!reference, 'depends on journey 1')
  await signIn(page, STAFF)
  await page.getByRole('link', { name: 'Enquiries' }).click()
  await page.getByLabel('Search').fill(reference)
  const link = page.getByRole('link', { name: reference })
  await expect(link).toBeVisible()
  await expect(page).toHaveURL(new RegExp(`q=${reference}`)) // filters live in the URL
  await page.screenshot(shots('dashboard-enquiries'))
  await link.click()

  await expect(page.getByRole('heading', { name: reference })).toBeVisible()
  await expect(page.getByText(visitor.email)).toBeVisible()
  // Only valid next steps are offered (status machine lives on the server).
  await expect(page.getByRole('button', { name: 'Mark as booked' })).toHaveCount(0)

  await page.getByRole('button', { name: 'Mark as contacted' }).click()
  await expect(page.getByText('Status changed to Contacted.')).toBeVisible()
  await page.getByRole('button', { name: 'Mark as qualified' }).click()
  await page.getByRole('button', { name: 'Mark as booked' }).click()
  await expect(page.getByText('Status changed to Booked.')).toBeVisible()

  await page.getByLabel('Add a note').fill('Booked for a whitening consultation (E2E).')
  await page.getByRole('button', { name: 'Add note' }).click()
  await expect(page.getByText('Booked for a whitening consultation (E2E).')).toBeVisible()

  // Closing needs a reason.
  await page.getByRole('button', { name: 'Close…' }).click()
  await page.getByRole('button', { name: 'Close enquiry' }).click()
  await expect(page.getByText('Choose a reason.')).toBeVisible()
  await page.getByLabel('Reason (required)').selectOption('TREATED')
  await page.getByRole('button', { name: 'Close enquiry' }).click()
  await expect(page.getByText(/This enquiry is closed/)).toBeVisible()
  await expect(page.getByRole('list', { name: 'Status history' }).getByRole('listitem')).toHaveCount(5)
  await page.screenshot(shots('dashboard-enquiry-detail'))
  await expectAccessible(page)

  // Staff cannot delete (admin only) and do not see the Users page.
  await expect(page.getByRole('button', { name: 'Delete enquiry' })).toHaveCount(0)
  await expect(page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Users' })).toHaveCount(0)
})

test('3. the overview shows analytics computed from the data', async ({ page }) => {
  await signIn(page, STAFF)
  const total = page.locator('.kpi').filter({ hasText: 'Total enquiries' }).locator('.kpi-value')
  await expect(total).toHaveText(/^\d[\d,]*$/)
  await expect(page.locator('.kpi').filter({ hasText: 'Conversion' }).locator('.kpi-value')).toHaveText(/%$/)
  await expect(page.getByRole('img', { name: /Line chart/ })).toBeVisible()
  await page.screenshot(shots('dashboard-overview'))

  // Every chart has an accessible table alternative.
  await page.getByRole('region', { name: 'By treatment' }).getByRole('button', { name: 'View as table' }).click()
  await expect(page.getByRole('region', { name: 'By treatment' }).getByRole('columnheader', { name: 'Conversion' })).toBeVisible()
  // Recharts SVG internals are covered by the table alternative; check everything else.
  await expectAccessible(page, { exclude: ['.recharts-wrapper'] })
})

test('4. a shopper buys with the demo payment method', async ({ page }) => {
  await page.goto('/shop/')
  await expect(page.getByText('Demo store: no real payments or deliveries.')).toBeVisible()
  await page.getByRole('link', { name: /Aligner Cleaning Crystals/ }).first().click()
  await expect(page.getByText('Recommended after:')).toBeVisible()
  await page.locator('form.cart').getByRole('button', { name: 'Add to cart' }).click()
  await page.getByRole('link', { name: 'View cart' }).first().click()
  await page.getByRole('link', { name: /Proceed to checkout/ }).click()

  await page.getByLabel('First name').fill('Demo')
  await page.getByLabel('Last name').fill('Shopper')
  await page.locator('#billing_address_1').fill('1 Example Street')
  await page.locator('#billing_city').fill('Leeds')
  await page.locator('#billing_postcode').fill('LS1 4AB')
  await page.locator('#billing_phone').fill('07700 900654')
  await page.locator('#billing_email').fill(`shopper.${unique}@example.test`)
  await expect(page.getByText('Demo payment (no money is taken)')).toBeVisible()
  await page.getByRole('button', { name: 'Place order' }).click()
  await expect(page.getByText(/Thank you\. Your order has been received/)).toBeVisible({ timeout: 20_000 })
  await page.screenshot(shots('shop-order-received'))
})

test('5. admins see the shop panel and manage users; roles are enforced', async ({ page }) => {
  test.setTimeout(150_000) // the API caches shop data for 60 s
  await signIn(page, ADMIN)
  const shop = page.getByRole('region', { name: 'Shop – last 30 days' })
  // The API caches WooCommerce data for 60 s, so the new order can take a moment to appear.
  await expect(async () => {
    await page.reload()
    await expect(shop.getByText('Paid orders')).toBeVisible()
    await expect(shop.getByRole('cell', { name: 'processing' }).first()).toBeVisible()
  }).toPass({ timeout: 75_000, intervals: [5_000, 10_000, 15_000] })

  await page.getByRole('link', { name: 'Users' }).click()
  await expect(page.getByRole('heading', { name: 'Users' })).toBeVisible()
  await expect(page.getByRole('cell', { name: /^Sam Receptionist/ })).toBeVisible()
  await page.screenshot(shots('dashboard-users'))

  await page.getByRole('link', { name: 'Clinics' }).click()
  await page.getByRole('button', { name: 'Edit Riverside Dental Studio' }).click()
  await expect(page.getByLabel('Slug (shared with the website)')).toHaveValue('riverside')
  await expectAccessible(page)

  await page.getByRole('button', { name: 'Log out' }).click()
  await expect(page.getByRole('heading', { name: 'Staff sign in' })).toBeVisible()
  // After logging out, the refresh cookie no longer restores the session.
  await page.goto('/dashboard/enquiries')
  await expect(page.getByRole('heading', { name: 'Staff sign in' })).toBeVisible()
})
