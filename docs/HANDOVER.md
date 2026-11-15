# Handover guide

This guide is for the team taking over the platform. It lists what a fresh installation
contains and what to set up before going live.

## 1. What a fresh installation contains

| Part | Contents on first start |
|---|---|
| Staff dashboard | One administrator (from `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`). No other accounts, no enquiries. |
| Clinics and treatments | 4 starter clinics and 12 starter treatments. Replace their details with your own (see §2). |
| Website | Home, About, Contact, Book, Privacy notice and Image credits pages; treatment and clinic pages; menus. |
| Shop | Product categories, UK delivery options and payment methods. **No products.** |
| Payments | Bank transfer; pay at the clinic (only with clinic collection); Stripe installed, waiting for your keys. |

There are no sample enquiries, orders, products, coupons or demo accounts. The site is hidden
from search engines until you choose to launch (`SITE_PUBLIC=1`).

## 2. Before going live

1. **Administrator.** Sign in at `/dashboard/` with the account from `.env`. Use a real mailbox
   for `SEED_ADMIN_EMAIL`, or generate one with `py infra/make_env.py <domain> <email>`.
2. **Staff accounts.** Dashboard → Users → Add user. Each person gets their own account.
3. **Clinics.** Dashboard → Clinics: address, phone, email, opening hours and treatments
   offered. These show on the website automatically. The descriptive text for each clinic page is
   edited in WordPress (`/wp-admin/` → Clinics).
4. **Treatments.** Dashboard → Treatments: names, summaries, starting prices and durations.
   The longer page text and FAQs are edited in WordPress (Treatments).
5. **Products.** Dashboard → Shop → Add product: name, price, sale price, stock, category and
   the treatment it is recommended after. Add photos and long descriptions in WordPress (each
   product has an "Add photos… in WordPress" link).
6. **Card payments.** WordPress → WooCommerce → Settings → Payments → Stripe: connect your
   Stripe account (or paste its API keys). Card payments then appear at checkout.
7. **Bank transfer.** WooCommerce → Settings → Payments → Bank transfer: enter your account
   name, sort code and account number so they are shown to customers. Turn it off if unwanted.
8. **Delivery.** WooCommerce → Settings → Shipping → United Kingdom: prices for standard and
   express delivery, the free-delivery threshold, and clinic collection.
9. **Wording and photos.** About, Contact and Privacy notice pages in WordPress → Pages. Replace
   the starter photographs (Media) with your own and update the Image credits page. Have the
   privacy notice reviewed for your organisation.
10. **Site name.** `SITE_NAME` in `.env` (header, footer and emails), then redeploy.
11. **Launch.** Set `SITE_PUBLIC=1` in `.env` and redeploy so search engines can index the site.

## 3. Day-to-day use

| Task | Where |
|---|---|
| Reply to enquiries, record calls and bookings | Dashboard → Enquiries (filter "New" for those waiting) |
| See performance by clinic and treatment | Dashboard → Overview |
| Mark bank-transfer orders as paid, mark orders as completed | Dashboard → Shop → Orders |
| Add or change products and prices | Dashboard → Shop → Products |
| Refunds, coupons, emails to customers | WordPress → WooCommerce |

Order statuses: **Awaiting payment** (bank transfer not yet received) → **Paid – to dispatch**
→ **Completed** (sent or collected). Card and pay-at-clinic orders start as "Paid – to dispatch".

## 4. Development data

For development only, sample data can be added to a local database and removed again:

```sh
docker compose exec api python -m app.seed --demo        # demo staff + 5,000 sample enquiries
docker compose exec api python -m app.seed --purge-demo  # remove all of it
docker compose run --rm wpcli wp synergy purge-test-orders  # and any test shop orders
```

Sample data always uses the reserved `example.test` email domain, which is how `--purge-demo`
finds it. Never run `--demo` on the live system.

## 5. Automated tests against a deployment

The end-to-end suite creates its own staff account and product, places an order with "Pay at
the clinic", completes it, and removes the product again; the test account is deactivated at
the end. Afterwards remove the test enquiry and order:

```sh
cd e2e && BASE_URL=https://<host> E2E_ADMIN_PASSWORD=… npx playwright test
docker compose -f docker-compose.prod.yml exec api python -m app.seed --purge-demo
docker compose -f docker-compose.prod.yml run --rm wpcli wp synergy purge-test-orders
```

Both commands only touch records using the reserved `example.test` email domain.
