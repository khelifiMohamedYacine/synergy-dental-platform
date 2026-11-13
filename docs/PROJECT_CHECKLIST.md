# Project Checklist

Legend: `[x]` done and verified · `[~]` in progress · `[ ]` not started. An item is only ticked once
it is implemented **and** tested or reviewed.

_Last updated: 2026-11-13 (Phase 2: repo + Docker skeleton running)_

## Phase 1 – Research and planning
- [~] Analyse job description: **blocked**, advert page could not be fetched; awaiting text from owner
- [x] Research company's public profile (search summaries only; see doc 01 §2)
- [x] Identify business needs (doc 01 §3)
- [x] Define scope (doc 01 §5)
- [x] Initial requirements + gap analysis (doc 02)
- [x] Proposed architecture (doc 03)
- [x] Development roadmap (ROADMAP.md)
- [x] Owner approval of architecture and open decisions (2026-11-13, recorded in doc 03 §9)

## Environment
- [x] Git 2.54, Node 24, npm 11, Python 3.14, GitHub CLI present
- [x] WSL 2 installed (verified 2026-11-13: WSL 3.0.5.0, kernel 6.18.58.1-1)
- [x] Docker Desktop installed and `docker run hello-world` verified (2026-11-13: Engine 29.9.3, Compose v5.7.0, WSL 2 backend)
- [x] Final repository location agreed: `C:\dev\synergy-dental-platform`
- [x] Git repository initialised (branch `main`)
- [x] GitHub remote created: public repo `khelifiMohamedYacine/synergy-dental-platform` (2026-11-13; commits use the GitHub no-reply email)

## Phase 2 – Design
- [~] Public site wireframes (drafted in `docs/design/ui-design.md`; awaiting owner review)
- [~] Dashboard wireframes (drafted; awaiting owner review)
- [~] Design tokens (drafted; text contrast ratios computed 2026-11-13, badge colours still to measure)
- [~] WordPress theme approach: custom classic theme proposed (ui-design §5; awaiting owner review)
- [~] Full ERD + table descriptions (doc 04 draft; awaiting owner review)
- [~] API contract (doc 05 draft; awaiting owner review)
- [x] `.gitignore`, `.gitattributes`, `.env.example`
- [x] Repo skeleton, docker-compose skeleton, CI skeleton (2026-11-13: `docker compose up --build` starts all 6 services; API `/health` + `/health/ready` verified through Caddy incl. 503 with DB stopped; backend 2 pytest + ruff clean; dashboard 2 Vitest tests, lint, build pass. first GitHub Actions run green: backend, dashboard, compose)

## Phase 3 – Backend
- [ ] Models + Alembic migrations · [ ] Seed data · [ ] Auth + RBAC · [ ] Enquiry endpoints
- [ ] Status workflow · [ ] Clinics/treatments · [ ] Analytics · [ ] Rate limiting · [ ] Pytest suite · [ ] Postman collection

## Phase 4 – Dashboard
- [ ] Auth flow · [ ] Enquiry list/filter/paginate · [ ] Detail + status · [ ] CRUD screens · [ ] Charts · [ ] RTL tests

## Phase 5 – WordPress & WooCommerce
- [ ] WP-CLI provisioning · [ ] Theme · [ ] Plugin: CPTs + meta · [ ] Enquiry form + API client · [ ] Woo products + demo gateway · [ ] Woo customisations · [ ] SEO/sitemap

## Phase 6 – Integration
- [ ] Shop panel via WC REST · [ ] Slug sync check · [ ] 5 Playwright journeys

## Phase 7 – QA
- [ ] Full test run recorded · [ ] Security checklist · [ ] Lighthouse measurements · [ ] Accessibility fixes

## Phase 8 – Deployment and presentation
- [ ] Cloud deployment · [ ] Docs 10–11 · [ ] Screenshots · [ ] Demo video · [ ] Presentation notes
