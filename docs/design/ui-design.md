# UI Design – Public Website and Staff Dashboard

**Status:** Draft for review (Phase 2). This supports docs 02 (FR-PUB, FR-DASH) and 06
(implementation). · **Last updated:** 2026-11-13

## 1. Design direction

Patients deciding on private treatment (implants, aligners, whitening) want **calm, clinical
competence with warmth**. The aim is: trustworthy rather than trendy, premium rather than flashy.

- Deep teal as the primary colour (clean, medical, not the usual "dentist blue"), on a warm
  off-white background. A muted bronze accent is used only for decoration.
- A serif display face for headings (premium, editorial) and a neutral sans-serif for body and UI
  text (readable, efficient).
- Generous white space, large photography, few borders, and soft 8–12 px radii.
- The dashboard uses the **same tokens** but is denser: white surfaces, smaller type and tables
  first. That way it feels like the same organisation without wasting staff time.

No Synergy Dental logos, photos, copy or colours are used. The brand name in the demo is
**"Synergy Dental (demo)"**, and the logo is a simple text wordmark created for this project.

## 2. Design tokens

Tokens are defined once as CSS custom properties. They are shared by the WordPress theme
(`theme.json` palette + `tokens.css`) and the React dashboard (`src/styles/tokens.css`), so both
products stay consistent.

### 2.1 Colour (contrast measured with the WCAG 2.x relative-luminance formula)

| Token | Hex | Use | Contrast (measured 2026-11-13) |
|---|---|---|---|
| `--color-bg` | `#FAF8F5` | Page background (public) | — |
| `--color-surface` | `#FFFFFF` | Cards, dashboard surfaces | — |
| `--color-text` | `#1B2A2F` | Body text | 13.96:1 on bg ✔ AAA |
| `--color-text-muted` | `#56656A` | Secondary text | 5.72:1 on bg, 6.06:1 on white ✔ AA |
| `--color-primary` | `#0E5A63` | Buttons, links, focus ring | 7.44:1 on bg ✔ AAA; white text on it 7.89:1 ✔ |
| `--color-primary-hover` | `#0A464D` | Hover/active | white on it 10.52:1 |
| `--color-primary-50` | `#E6F2F2` | Tinted sections, selected rows | primary text on it 6.89:1 ✔ |
| `--color-accent` | `#B5762E` | **Decorative only** (rules, icons next to text) | 3.54:1, below 4.5:1, so never used for text |
| `--color-accent-text` | `#8A5418` | Accent when used as text (eyebrows) | 5.89:1 on bg ✔ |
| `--color-border` | `#C9D3D5` | Decorative dividers | 1.53:1, decorative only |
| `--color-input-border` | `#7C8B90` | Form field borders (WCAG 1.4.11 needs ≥ 3:1) | 3.53:1 on white ✔ |
| `--color-success` | `#1E7A46` | Success text/icons | 5.35:1 on white ✔ |
| `--color-error` | `#B42318` | Errors | 6.57:1 on white ✔ |
| `--color-warning-text` / `-bg` | `#8A5300` / `#FFF6E5` | Demo-mode notices | 5.90:1 ✔ |

Status colours in the dashboard (badges always show a **text label** as well, so colour is never the
only signal):
`NEW` primary-50/primary · `CONTACTED` blue-50/#1D4F91 · `QUALIFIED` violet-50/#5B3A99 ·
`BOOKED` green-50/success · `CLOSED` grey-100/text-muted. The badge pairs will be measured in
Phase 4 and recorded in doc 09.

### 2.2 Typography
| Token | Value |
|---|---|
| `--font-display` | "Fraunces", Georgia, serif (variable, weights 400–600) |
| `--font-body` | "Inter", system-ui, -apple-system, "Segoe UI", sans-serif (variable, 400–600) |
| Scale (public) | 14 / 16 (base) / 18 / 22 / 28 / 36 / 48 px, fluid with `clamp()` for h1–h2 |
| Scale (dashboard) | 13 / 14 (base) / 16 / 20 / 24 px |
| Line height | 1.6 body, 1.2 headings |

Both fonts are SIL Open Font Licence. They are **self-hosted** as subset `woff2` files with
`font-display: swap` and one `preload` for the body font. That means no third-party font request
(better privacy and fewer layout shifts). Effect on LCP/CLS to be measured in Phase 7.

### 2.3 Spacing, radius, elevation, motion
- Spacing: 4 px base, tokens `--space-1…10` = 4, 8, 12, 16, 24, 32, 48, 64, 96, 128.
- Radius: `--radius-sm` 6px (inputs), `--radius-md` 10px (cards, buttons), `--radius-pill`.
- Shadow: `--shadow-1` `0 1px 2px rgb(16 42 47 / .06), 0 2px 8px rgb(16 42 47 / .06)`.
- Motion: 150–200 ms ease-out. All non-essential motion is disabled under
  `prefers-reduced-motion: reduce`.
- Breakpoints: 360 (min supported), 640, 960, 1280 px. Layouts are built mobile-first.
- Focus: `outline: 3px solid var(--color-primary); outline-offset: 2px` on `:focus-visible`. It is
  never removed.

## 3. Public website wireframes (WordPress theme)

Sitemap: Home · Treatments (archive → single) · Clinics (archive → single) · About · Shop
(WooCommerce) · Contact · Book / Enquire → Confirmation · Privacy notice.

### 3.1 Home (desktop ≥ 960 px)
```
┌──────────────────────────────────────────────────────────────────────────┐
│ [Skip to content]                                                        │
│ Synergy Dental (demo)   Treatments  Clinics  About  Shop  Contact [Book] │
├──────────────────────────────────────────────────────────────────────────┤
│  EYEBROW: PRIVATE & FAMILY DENTISTRY                ┌──────────────────┐ │
│  H1  Confident smiles,                               │   hero image     │ │
│      planned around you                              │  (WebP/AVIF,     │ │
│  Lead paragraph (1–2 lines)                          │   fetchpriority  │ │
│  [Book a consultation]  [Explore treatments →]       │   = high)        │ │
│  ★ trust row: "12 clinics · GDC-registered · 0% finance (fictional)"    │ │
├──────────────────────────────────────────────────────────────────────────┤
│  H2 Popular treatments                                                   │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐   (cards from CPT query,    │
│  │ img    │ │ img    │ │ img    │ │ img    │    category eyebrow,        │
│  │ Implants│ │Aligners│ │Whitening│ │Hygiene│    "from £x", link)        │
│  └────────┘ └────────┘ └────────┘ └────────┘                             │
├──────────────────────────────────────────────────────────────────────────┤
│  H2 How it works   1 Enquire → 2 We call you → 3 Consultation → 4 Plan   │
├──────────────────────────────────────────────────────────────────────────┤
│  H2 Find a clinic near you   [city cards ×3]   [All clinics →]           │
├──────────────────────────────────────────────────────────────────────────┤
│  Shop strip: "Aftercare essentials" 3 products  [Visit shop]             │
├──────────────────────────────────────────────────────────────────────────┤
│  CTA band (primary bg): "Ready to talk?"  [Book a consultation]          │
├──────────────────────────────────────────────────────────────────────────┤
│  Footer: clinics · treatments · privacy · "Independent demo – not       │
│  affiliated with Synergy Dental Group"                                   │
└──────────────────────────────────────────────────────────────────────────┘
```
Mobile (360 px): header collapses to wordmark + `[Book]` + menu button (`aria-expanded`, focus
trapped while open, `Esc` closes). Hero image moves below the text. Cards stack in one column.

### 3.2 Single treatment
```
Breadcrumb: Home › Treatments › Cosmetic › Teeth whitening
┌─────────────────────────────────────┬──────────────────────────────┐
│ H1 Teeth whitening                  │ ┌──────────────────────────┐ │
│ Summary (custom field)              │ │ Price from  £295         │ │
│ [hero image]                        │ │ Duration    2 visits     │ │
│ H2 What to expect (editor content)  │ │ Available at: 5 clinics  │ │
│ H2 FAQs  ▸ accordion (<details>)    │ │ [Enquire about this]     │ │
│                                     │ └──────────── sticky ──────┘ │
├─────────────────────────────────────┴──────────────────────────────┤
│ Related products (Woo, `_sd_recommended_after` = this slug)        │
└────────────────────────────────────────────────────────────────────┘
```
"Enquire about this" links to `/book/?treatment=teeth-whitening`, which pre-selects the treatment (US-01).

### 3.3 Enquiry form (`/book/`) and confirmation
```
H1 Request an appointment
Error summary (role="alert", links to each field) — shown only after a failed submit
( ) General enquiry   (•) Appointment request          ← fieldset/legend
Full name*            [__________________]
Email*                [__________________]
Phone*                [__________________]   hint: "UK mobile or landline"
Preferred clinic*     [ Riverside ▾ ]
Treatment             [ Not sure yet ▾ ]     ← filtered to the clinic's treatments (JS); all if no JS
Preferred date*       [ dd/mm/yyyy ]  Time [Morning ▾]   (shown for Appointment)
Message               [                    ]  0/2000
[✓] I agree to be contacted about my enquiry. (link: privacy notice)*
(hidden honeypot field, hidden nonce, hidden idempotency key)
[ Send request ]   ← disabled + "Sending…" while submitting
```
Each error is linked to its field with `aria-describedby` and `aria-invalid="true"`, and the error
text is written next to the field. The form is a normal HTML POST, so it works without JavaScript.
JS only adds the dependent dropdown and the character counter.

Confirmation: H1 "Thank you, Jamie". The reference **SD-7K3M9Q** is shown in a large monospace
font with a copy button, followed by "What happens next" (3 steps), the clinic phone number and a
link to the shop.

### 3.4 Shop (WooCommerce)
The standard Woo templates are styled by the theme, not rewritten (overriding templates makes Woo
updates painful). Additions: a site-wide "Demo store – no real payments or deliveries" notice
(`woocommerce_demo_store`), a "Recommended after: <treatment>" line on product pages, and the
"Demo payment" gateway at checkout.

## 4. Dashboard wireframes (React)

Routes: `/login` · `/` overview · `/enquiries` · `/enquiries/:id` · `/appointments` · `/clinics` ·
`/treatments` · `/users` (ADMIN) · `*` not found. Routes the current role cannot use are hidden in
the navigation **and** guarded in the router, and the API enforces the same rules in any case.

### 4.1 Login
A centred card with the wordmark, email, password (with a show/hide toggle), `[Sign in]`, and an
error banner for `INVALID_CREDENTIALS`. Below it: "Demo credentials are in the README" (fictional
accounts).

### 4.2 Overview
```
┌─────────┬─────────────────────────────────────────────────────────────────┐
│ SD demo │ Overview                     Period [Last 90 days ▾] Clinic [All▾]│
│         ├─────────────────────────────────────────────────────────────────┤
│ Overview│ ┌──────────┐┌──────────┐┌──────────┐┌──────────┐                 │
│ Enquiries││ Total    ││ New      ││ Booked   ││ Conversion│                │
│ Appoint. ││ 1,284    ││ 63       ││ 311      ││ 24.2 %   │  (from API)    │
│ Clinics  │└──────────┘└──────────┘└──────────┘└──────────┘                 │
│ Treatments│ Enquiries over time (line: total vs booked, weekly)            │
│ Users*   │ ┌───────────────────────────────────────────────────────────┐   │
│          │ └───────────────────────────────────────────────────────────┘   │
│          │ ┌── By treatment (horizontal bar) ──┐┌── By clinic (table) ──┐ │
│──────────│ │                                   ││ name total booked conv │ │
│ A. Morgan│ └───────────────────────────────────┘└────────────────────────┘ │
│ [Log out]│ Shop (last 30 days): 42 orders · £1,236 · recent orders list    │
└──────────┴─────────────────────────────────────────────────────────────────┘
```
The numbers above are layout placeholders; the real ones come from the API. Each chart has a
visually-hidden data table, or a "View as table" toggle, so screen-reader users get the same
information. Chart library: **Recharts**. It is React-native, declarative and small enough, and it
is justified in doc 06. Every panel has its own loading skeleton and error state, so a failing shop
panel does not blank the page.

### 4.3 Enquiry list
```
Enquiries                                                    [Export CSV]
[ Search name, email or SD-ref…  ]  Status [NEW ✕][CONTACTED ✕] ▾
Clinic [All ▾]  Treatment [All ▾]  Type [All ▾]  From [ ] To [ ]  [Clear]
┌──────────┬──────────────┬───────────┬───────────┬──────────┬──────────┐
│ Ref      │ Name         │ Treatment │ Clinic    │ Status   │ Received ▼│
├──────────┼──────────────┼───────────┼───────────┼──────────┼──────────┤
│ SD-7K3M9Q│ Jamie F.     │ Implants  │ Riverside │ [NEW]    │ 2 h ago  │
│ …        │              │           │           │          │          │
└──────────┴──────────────┴───────────┴───────────┴──────────┴──────────┘
Showing 21–40 of 134            [‹ Prev] 1 2 [3] 4 … 7 [Next ›]
```
Filter, sort and page state live in the **URL query string**, so views can be bookmarked, the back
button works and a refresh keeps the view. Search is debounced (300 ms). The table is a real
`<table>` with `<th scope>` and `aria-sort`, and rows are opened with a link in the reference cell
(not a clickable `<tr>`), which keeps them keyboard accessible. Below 640 px the rows become stacked
cards.

### 4.4 Enquiry detail
```
‹ Back to list     SD-7K3M9Q  [CONTACTED]          Appointment request
┌─ Contact ───────────────────────┐ ┌─ Status ───────────────────────────┐
│ Jamie Fictional                  │ │ Move to: [Qualified] [Close…]      │
│ jamie@example.test  (mailto)     │ │  (buttons = allowed_transitions)   │
│ +44 7700 900123     (tel)        │ │ Close… opens dialog: reason* + note│
│ Riverside · Dental implants      │ ├─ Timeline ─────────────────────────┤
│ Preferred: Mon 24 Nov, morning   │ │ ● NEW         13 Nov 10:15 system  │
│ Message: "…"                     │ │ ● CONTACTED   13 Nov 11:02 A.Morgan│
│ Consent given 13 Nov 10:15       │ ├─ Notes ────────────────────────────┤
└──────────────────────────────────┘ │ [Add note… no clinical info] [Add] │
 [Delete enquiry] (ADMIN, confirm)   │ A. Morgan · 11:03 "Left voicemail" │
                                     └────────────────────────────────────┘
```
Status changes use the server's response to update the page (no optimistic update). Because the
change also affects analytics and history, a wrong optimistic state would be misleading. The
dialog is a native `<dialog>` with focus returned to the trigger on close.

### 4.5 Clinics / treatments (ADMIN)
These are list tables with `[Add]`, plus an edit form in a side panel. "Deactivate" replaces
"Delete" and is labelled as such. STAFF see the same lists read-only, with no action buttons.

## 5. Phase 2 decision: WordPress theme approach (FR-WP-03)

**Decision (proposed): a custom classic PHP theme, `synergy-theme`, with `theme.json` for editor
settings.** It is not a child theme of a commercial theme, and it is not a full-site-editing
block theme.

| Option | For | Against |
|---|---|---|
| **Custom classic theme** ✔ | Template hierarchy written in PHP (`single-treatment.php`, `archive-clinic.php`), so WordPress/PHP skills are visible; full control of markup for performance and accessibility; easy to explain | More templates to write than a child theme |
| Child theme of Twenty Twenty-Five (block theme) | Fast to start, modern full-site editing | Most templates are HTML block markup rather than PHP, so it shows less of the PHP skill the role asks for; inherits the parent's CSS and JS |
| Child theme of a page-builder theme | Fast visual results | Heavy front-end, vendor lock-in, poor Lighthouse scores, little real code |

The theme only handles presentation. CPTs, fields, the form, the API client and the Woo
customisations live in the `synergy-integration` plugin (ADR in doc 03, component table), so
changing the theme loses no functionality.
