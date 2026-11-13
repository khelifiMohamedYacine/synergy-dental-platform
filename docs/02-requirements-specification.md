# 02 – Requirements Specification

**Status:** Baselined at the end of Phase 1 (2026-11-13). Requirement IDs are stable and will be referenced from tests and
from the final evaluation (doc 11). · **Last updated:** 2026-11-13

Priority uses MoSCoW: **M**ust, **S**hould, **C**ould, **W**on't (this release).

## 1. Actors

| Actor | Description |
|---|---|
| Visitor / prospective patient | Anonymous user of the public website and shop |
| Customer | Visitor who places a shop order |
| Staff | Clinic employee; manages enquiries |
| Administrator | Manages users, clinics, treatments and settings; has full access |
| WordPress editor | Maintains website content in WP admin (role managed inside WordPress) |
| System (WordPress plugin) | Machine client that calls the FastAPI backend with a service credential |

## 2. Functional requirements

### FR-PUB – Public website (WordPress)

| ID | Requirement | Pri |
|---|---|---|
| FR-PUB-01 | Homepage with hero, featured treatments, clinic summary, trust signals and enquiry call to action | M |
| FR-PUB-02 | About page | M |
| FR-PUB-03 | Treatments listing grouped by category | M |
| FR-PUB-04 | Individual treatment pages driven by custom fields (summary, typical duration, price-from, FAQs) | M |
| FR-PUB-05 | Clinic listing and detail pages (address, hours, map link, treatments offered) | M |
| FR-PUB-06 | Contact page | M |
| FR-PUB-07 | Appointment enquiry form available on a dedicated page and in a call to action on treatment pages | M |
| FR-PUB-08 | Responsive navigation (keyboard accessible) on mobile and desktop | M |
| FR-PUB-09 | SEO: unique titles/meta descriptions, semantic headings, Open Graph tags, XML sitemap, robots.txt, `Dentist`/`MedicalClinic` structured data | S |

### FR-ENQ – Enquiries and appointment requests

| ID | Requirement | Pri |
|---|---|---|
| FR-ENQ-01 | Visitor submits name, email, phone, treatment of interest, preferred clinic, optional preferred date/time window, optional message, and explicit consent to be contacted | M |
| FR-ENQ-02 | Server-side validation (Pydantic) with field-level error messages returned to the form | M |
| FR-ENQ-03 | Enquiry stored in PostgreSQL with status `NEW` | M |
| FR-ENQ-04 | Unique, non-sequential, human-readable reference generated (e.g. `SD-7K3M9Q`) | M |
| FR-ENQ-05 | Confirmation page that shows the reference | M |
| FR-ENQ-06 | Duplicate prevention: (a) an idempotency key stops double-submits; (b) a repeat open enquiry for the same email + treatment + clinic within 24 h is rejected with a friendly message | M |
| FR-ENQ-07 | Public enquiry endpoint is rate limited | M |
| FR-ENQ-08 | Confirmation email (captured locally by a dev mail catcher, never sent to real addresses) | C |
| FR-ENQ-09 | Spam protection with a honeypot field | S |

### FR-DASH – Staff dashboard (React)

| ID | Requirement | Pri |
|---|---|---|
| FR-DASH-01 | Secure login and logout | M |
| FR-DASH-02 | Role-based access: `ADMIN`, `STAFF` (see permission matrix §5) | M |
| FR-DASH-03 | Overview page with KPI cards and charts | M |
| FR-DASH-04 | Enquiry list with search (name, email, reference), filters (status, clinic, treatment, type, date range), sorting and server-side pagination | M |
| FR-DASH-05 | Enquiry detail view with status history and internal notes | M |
| FR-DASH-06 | Status workflow `NEW → CONTACTED → QUALIFIED → BOOKED → CLOSED` with validated transitions | M |
| FR-DASH-07 | Appointment-request view (enquiries that include a preferred date/time) | M |
| FR-DASH-08 | Clinic management (CRUD, ADMIN only; deactivate instead of delete) | M |
| FR-DASH-09 | Treatment and treatment-category management (CRUD, ADMIN only) | M |
| FR-DASH-10 | Analytics: total, new, booked, by treatment, by clinic, conversion rate, all computed from DB records | M |
| FR-DASH-11 | Shop summary panel (recent WooCommerce orders and revenue via the WC REST API) | S |
| FR-DASH-12 | User management (ADMIN) | S |
| FR-DASH-13 | CSV export of filtered enquiries | C |

### FR-SHOP – WooCommerce

| ID | Requirement | Pri |
|---|---|---|
| FR-SHOP-01 | Product catalogue of fictional, non-clinical products (care kits, brushes, floss, whitening toothpaste) | M |
| FR-SHOP-02 | Product detail pages | M |
| FR-SHOP-03 | Cart | M |
| FR-SHOP-04 | Simulated checkout. No real payment; uses an offline "Demo payment" gateway | M |
| FR-SHOP-05 | Orders manageable in WooCommerce admin | M |
| FR-SHOP-06 | WooCommerce customisation in PHP (e.g. demo-mode checkout notice, custom product field "Recommended after: <treatment>") | M |

### FR-WP – WordPress engineering

| ID | Requirement | Pri |
|---|---|---|
| FR-WP-01 | Custom post types `treatment` and `clinic`, plus taxonomy `treatment_category` | M |
| FR-WP-02 | Custom fields registered with `register_post_meta` (exposed in REST) | M |
| FR-WP-03 | Custom theme (block theme or classic child theme, decided in Phase 2) | M |
| FR-WP-04 | Custom plugin `synergy-integration` for the enquiry form, API client, settings page and REST route | M |
| FR-WP-05 | Hardened configuration: secrets from env, `DISALLOW_FILE_EDIT`, XML-RPC disabled, no default `admin` user | M |

## 3. Non-functional requirements

| ID | Category | Requirement |
|---|---|---|
| NFR-01 | Performance | Public pages: Lighthouse Performance ≥ 85 on mobile (target, measured not assumed); API p95 < 300 ms locally for list/analytics endpoints with ~5,000 seeded enquiries |
| NFR-02 | Accessibility | WCAG 2.2 AA intent; Lighthouse Accessibility ≥ 95; full keyboard navigation; visible focus |
| NFR-03 | Security | Argon2 password hashing, JWT auth, RBAC on every protected endpoint, ORM parameterised queries, strict CORS, secrets only in env vars, rate limiting |
| NFR-04 | Privacy | Fictional data only; minimal personal data; no health data beyond treatment of interest; consent captured |
| NFR-05 | Maintainability | Modular code, PEP 8 (ruff), ESLint/Prettier, type hints, ≥ 80 % backend line coverage target |
| NFR-06 | Portability | Full stack starts with one `docker compose up` |
| NFR-07 | Reliability | Clear error and loading states; consistent JSON error format; DB migrations are repeatable |
| NFR-08 | Responsiveness | Usable from 360 px to 1440 px+ |

## 4. User stories (summary, refined in Phase 2)

| ID | Story | Acceptance criteria |
|---|---|---|
| US-01 | As a visitor, I want to read about a treatment so I can decide whether to enquire | Treatment page shows custom-field content; enquiry CTA pre-selects that treatment |
| US-02 | As a visitor, I want to request an appointment so the clinic contacts me | Valid submit → confirmation with reference; invalid → field errors; record saved as `NEW` |
| US-03 | As a visitor, I want reassurance that my request was received | Confirmation shows reference and next steps |
| US-04 | As staff, I want to see new enquiries first so none are missed | Default list sorted newest first, filterable by `NEW` |
| US-05 | As staff, I want to update an enquiry's status and add notes | Allowed transitions succeed and are recorded in history; disallowed transitions return 409 |
| US-06 | As an admin, I want to see conversion by clinic and treatment | Analytics match hand-calculated values from seeded data (tested) |
| US-07 | As an admin, I want to manage clinics and treatments | CRUD works; STAFF receives 403 |
| US-08 | As a customer, I want to buy a care kit | Add to cart → checkout with demo gateway → order confirmation; order visible in WooCommerce admin |

## 5. Permission matrix (initial)

| Capability | Anonymous | STAFF | ADMIN |
|---|---|---|---|
| Submit enquiry (via WP plugin) | ✔ | – | – |
| View/search enquiries | ✗ | ✔ | ✔ |
| Update status / add notes | ✗ | ✔ | ✔ |
| Delete enquiry | ✗ | ✗ | ✔ |
| View analytics | ✗ | ✔ | ✔ |
| Manage clinics / treatments | ✗ | Read | ✔ |
| Manage users | ✗ | ✗ | ✔ |

## 6. Constraints

- Mandated stack: React, FastAPI, PostgreSQL/SQLAlchemy/Alembic, WordPress/PHP/WooCommerce.
- **WordPress cannot run on PostgreSQL.** It requires MySQL or MariaDB, so the system has two
  databases (see doc 03, ADR-03).
- Fictional data only; no real payments.
- Single developer; hosting must be low cost or free tier.
- Development machine: Windows 11. Docker Desktop is not installed yet.

## 7. Gaps and ambiguities found in the brief (resolutions proposed)

| # | Gap | Proposed resolution |
|---|---|---|
| G1 | The brief lists both "enquiry management" and "appointment request management" without defining the difference | One `enquiry` entity with `enquiry_type` = `GENERAL` \| `APPOINTMENT`. Appointment requests also carry a preferred date and time window. No real booking calendar |
| G2 | `CLOSED` is ambiguous: closed after booking, or lost? This breaks conversion maths | `CLOSED` requires a `close_reason` (`TREATED`, `NOT_INTERESTED`, `UNREACHABLE`, `DUPLICATE`, `OTHER`). Conversion = enquiries that reached `BOOKED` ÷ all enquiries in the period, measured from status history |
| G3 | Clinics and treatments are needed in both WordPress (content) and PostgreSQL (foreign keys for enquiries and analytics) | PostgreSQL holds the canonical business records. WordPress CPTs hold marketing content and link by `slug`. The plugin reads the canonical list from the API (cached) for the form |
| G4 | "Public website" could mean React or WordPress | WordPress serves the public site and shop. React is used for the staff dashboard (**approved 2026-11-13**, doc 03 ADR-01) |
| G5 | Who submits enquiries to the API, the browser or WordPress? | The WP plugin handles the form server-side and calls FastAPI with a service token. No public CORS surface, and the form works without JavaScript |
| G6 | The brief lists Jest-style testing but the frontend build tool is unspecified | Vite + Vitest + React Testing Library (same RTL API, native to Vite) |
| G7 | No PHP testing tool is listed | Test the plugin's PHP through REST/HTTP integration tests (pytest) and Playwright. PHPUnit unit tests for the plugin are a *Could* |
| G8 | The job advert itself was not available to analyse (fetch blocked) | **Open.** Need the advert text to map requirements to responsibilities (doc 01 §objectives) |
| G9 | "Confirmation" channel is not defined | On-screen confirmation is a Must. Email through a local mail catcher is a Could |
