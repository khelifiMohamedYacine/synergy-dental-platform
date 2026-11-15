import { expect, test } from '@playwright/test'
import { ADMIN, STAFF, expectAccessible, inDays, shots, signIn } from './helpers.js'

const unique = Date.now().toString(36)
const visitor = {
  name: 'Avery Testwell', // letters only: digits are rejected by the name rule
  email: `avery.${unique}@example.test`,
  phone: '07700 900321',
}
let reference
const PRODUCT = `Aligner Cleaning Crystals ${unique.toUpperCase()}`

test.describe.configure({ mode: 'serial' })

test('1. visitor requests an appointment on the website', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Healthy, confident smiles')
  await page.screenshot(shots('website-home'))
  await expectAccessible(page)

  await page.getByRole('link', { name: 'Treatments', exact: true }).first().click()
  await page.getByRole('link', { name: 'Teeth whitening' }).click()
  await expect(page.locator('.booking-price')).toContainText('£295')
  await page.getByRole('link', { name: 'Request an appointment' }).click()

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

test('4a. an admin adds a product to the shop from the dashboard', async ({ page }) => {
  await signIn(page, ADMIN)
  await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Shop' }).click()
  await expect(page.getByRole('heading', { name: 'Shop', level: 1 })).toBeVisible()
  await page.getByRole('button', { name: 'Add product' }).first().click()
  // Validation runs before anything is sent.
  await page.getByRole('button', { name: 'Save product' }).click()
  await expect(page.getByText('Enter a product name.')).toBeVisible()
  await page.getByLabel('Name', { exact: true }).fill(PRODUCT)
  await page.getByLabel('Price (£)').fill('14.99')
  await page.getByLabel('Short description').fill('A month of cleaning crystals for clear aligners.')
  await page.getByLabel('Track stock quantity').check()
  await page.getByLabel('Quantity in stock').fill('25')
  await page.getByLabel('Recommended after treatment').selectOption('clear-aligners')
  await page.getByRole('button', { name: 'Save product' }).click()
  await expect(page.getByText(`Product “${PRODUCT}” saved.`)).toBeVisible()
  const row = page.getByRole('row').filter({ hasText: PRODUCT })
  await expect(row).toContainText('£14.99')
  await expect(row).toContainText('25 in stock')
  await page.screenshot(shots('dashboard-shop-products'))
  await expectAccessible(page)
})

test('4b. a shopper uses the cart, delivery options and pays on collection', async ({ page }) => {
  test.setTimeout(120_000)
  await page.goto('/shop/')
  await expect(page.getByText(/free UK delivery on orders over £30/)).toBeVisible()
  await page.screenshot(shots('shop'))
  await page.getByRole('link', { name: PRODUCT }).first().click()
  await expect(page.getByText('Recommended after:')).toBeVisible()
  await page.locator('form.cart').getByRole('button', { name: 'Add to cart' }).click()

  // The header cart shows the item and leads to the cart.
  const headerCart = page.locator('a.header-cart')
  await expect(headerCart).toContainText('1 item')
  await headerCart.click()
  await expect(page).toHaveURL(/\/cart\//)
  await page.getByRole('link', { name: /Proceed to checkout/ }).click()

  await page.locator('#billing_first_name').fill('Demo')
  await page.locator('#billing_last_name').fill('Shopper')
  await page.locator('#billing_address_1').fill('1 Example Street')
  await page.locator('#billing_city').fill('Leeds')
  await page.locator('#billing_postcode').fill('LS1 4AB')
  await page.locator('#billing_phone').fill('07700 900654')
  await page.locator('#billing_email').fill(`shopper.${unique}@example.test`)

  // Delivery options, including collection from a clinic (which then needs a clinic).
  const methods = page.locator('#shipping_method')
  await expect(methods.getByText('Standard delivery (2-3 working days)')).toBeVisible()
  await expect(methods.getByText('Express delivery (next working day)')).toBeVisible()
  await methods.getByLabel(/Collect from one of our clinics/).check()
  const clinicPicker = page.locator('#sd_collection_clinic')
  await expect(clinicPicker).toBeVisible()

  // Payment: bank transfer is always offered; "Pay at the clinic" only with clinic collection.
  const payment = page.locator('#payment')
  await expect(payment.getByLabel('Bank transfer')).toBeVisible()
  await payment.getByLabel('Pay at the clinic').check()
  await page.getByRole('button', { name: 'Place order' }).click()
  await expect(page.locator('.woocommerce-error')).toContainText('Choose the clinic you will collect your order from.')

  await clinicPicker.selectOption('parkside')
  await page.screenshot(shots('shop-checkout'))
  await page.getByRole('button', { name: 'Place order' }).click()
  await expect(page.getByText(/Thank you\. Your order has been received/)).toBeVisible({ timeout: 20_000 })
  await expect(page.getByText('Collect from:')).toBeVisible()
  await page.screenshot(shots('shop-order-received'))
})

test('website pages show photography and pass accessibility checks', async ({ page }) => {
  await page.goto('/about/')
  await expect(page.locator('.about-photo img')).toBeVisible()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Careful dentistry, explained clearly')
  await page.screenshot(shots('website-about'))
  await expectAccessible(page)

  await page.goto('/clinics/')
  await expect(page.locator('.card-media img')).toHaveCount(4)
  await page.screenshot(shots('website-clinics'))

  await page.goto('/treatments/')
  const images = page.locator('.card-media img')
  await expect(images.first()).toBeVisible()
  expect(await images.count()).toBe(12)
  await expectAccessible(page)
})

test('5. admins fulfil the order, see the shop panel and manage users; roles are enforced', async ({ page }) => {
  test.setTimeout(150_000) // the API caches shop totals for 60 s
  await signIn(page, ADMIN)
  const shop = page.getByRole('region', { name: 'Shop' })
  // The API caches WooCommerce totals for 60 s, so the new order can take a moment to appear.
  await expect(async () => {
    await page.reload()
    await expect(shop.getByText('Paid orders', { exact: true })).toBeVisible()
    await expect(shop.getByText('Paid – to dispatch').first()).toBeVisible()
  }).toPass({ timeout: 75_000, intervals: [5_000, 10_000, 15_000] })
  await page.screenshot(shots('dashboard-overview-shop'))

  // Orders: the shopper's order is listed with its collection clinic; mark it as completed.
  await shop.getByRole('link', { name: 'Manage orders' }).click()
  const order = page.getByRole('row').filter({ hasText: `shopper.${unique}@example.test` })
  await expect(order).toContainText('Parkside')
  await order.getByRole('button', { name: /^Mark as completed/ }).click()
  await expect(page.getByText(/is now “Completed”/)).toBeVisible()
  await expect(order).toContainText('Completed')
  await page.screenshot(shots('dashboard-shop-orders'))
  await expectAccessible(page)

  // Remove the test product again.
  await page.getByRole('tab', { name: 'Products' }).click()
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('row').filter({ hasText: PRODUCT }).getByRole('button', { name: /^Remove/ }).click()
  await expect(page.getByText(`“${PRODUCT}” was removed.`)).toBeVisible()

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
