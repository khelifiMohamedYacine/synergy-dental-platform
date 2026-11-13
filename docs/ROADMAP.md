# Development Roadmap

Effort estimates are in **focused working days** for one developer. They are estimates, not
commitments, and will be revised at the end of each phase with actual figures.

| Phase | Goal | Key outputs | Est. days | Exit criteria |
|---|---|---|---|---|
| 1 Research & planning | Agree scope and architecture | Docs 01–03 drafts, roadmap, checklist | 1 | Owner approves architecture and open decisions |
| 2 Design | Lock contracts before coding | Wireframes (public + dashboard), design tokens, full ERD (doc 04), OpenAPI contract draft (doc 05), repo skeleton, Docker Compose skeleton, CI skeleton | 2–3 | `docker compose up` starts empty services; contracts reviewed |
| 3 Backend | Working, tested API | Models + Alembic migrations, seed script (fictional data), auth + RBAC, enquiry/clinic/treatment/analytics/user endpoints, rate limiting, error format, pytest suite on real PostgreSQL, Postman collection | 5–6 | All endpoints tested; coverage measured and recorded |
| 4 Dashboard | Working React dashboard | Layout, auth flow, enquiry list (search/filter/paginate), detail + status workflow, clinics/treatments CRUD, analytics charts, loading/error states, Vitest + RTL tests | 5–6 | Dashboard usable end to end against the real API; tests pass |
| 5 WordPress & WooCommerce | Real CMS + shop | Theme, plugin (CPTs, meta, form, API client, settings, WP-CLI), WP-CLI provisioning script, WooCommerce products + demo gateway + customisations, SEO/sitemap | 5–6 | Fresh environment provisions automatically; form creates an enquiry in PostgreSQL |
| 6 Integration | Connect everything | Shop panel via WC REST, slug sync check, Playwright journeys (enquiry → dashboard → status → analytics; purchase) | 2–3 | All five E2E journeys pass in CI |
| 7 QA | Evidence of quality | Full test run, security checklist (doc 08), Lighthouse + query timings (doc 09), accessibility fixes | 2–3 | Docs 07–09 written with real numbers |
| 8 Deploy & present | Shareable result | Cloud deployment, docs 10–11, screenshots, README polish, demo video script, interview notes | 2–3 | Public repo + live URL (if deployed) |
| **Total** | | | **~24–31 days** | |

## Minimum viable demonstration (if time runs short)

These are protected first: enquiry submit → dashboard → status update → analytics; WordPress
CPTs + plugin form; WooCommerce simulated checkout; the tests behind each; and Docker Compose.

Dropped first: refresh-token rotation (fall back to access-only JWT), CSV export, confirmation
email, user-management UI, cloud deployment (replaced by documented deployment steps).

## Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Docker Desktop not yet installed (Windows) | Blocks Phase 2 | **Resolved:** Docker Desktop 29.9.3 on WSL 2 verified with `hello-world` (2026-11-13) |
| Project folder is inside OneDrive and its name contains spaces and an en dash | OneDrive syncing `node_modules` and Docker volumes causes slowness and file-lock errors; some tools mishandle the path | **Resolved:** repo moved to `C:\dev\synergy-dental-platform` (2026-11-13) |
| WordPress setup is manual and hard to reproduce | Reviewers can't run it | Provision entirely with WP-CLI scripts run on container start |
| Scope creep | Late delivery | MVP list above; checklist reviewed every phase |
| Host machine has Python 3.14; some libraries may lag | Install failures | Pin Python 3.13 inside the API container; local venv optional |
