# Project Checklist

Legend: `[x]` done and verified · `[~]` in progress · `[ ]` not started. An item is only ticked once
it is implemented **and** tested or reviewed.

_Last updated: 2026-11-13 (Phase 1 closed; Phase 2 started)_

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
- [ ] WSL 2 installed (owner installing) (checked 2026-11-13: not installed; `wsl` reports `REGDB_E_CLASSNOTREG`; hypervisor present, 32 GB RAM, ~815 GB free)
- [ ] Docker Desktop installed and `docker run hello-world` verified
- [x] Final repository location agreed: `C:\dev\synergy-dental-platform`
- [x] Git repository initialised (branch `main`)
- [ ] GitHub remote created (needs owner go-ahead; repo will be public)

## Phase 2 – Design
- [ ] Public site wireframes
- [ ] Dashboard wireframes
- [ ] Design tokens (colour, type, spacing)
- [ ] Full ERD + table descriptions (doc 04)
- [ ] API contract (doc 05 draft)
- [x] `.gitignore`, `.gitattributes`, `.env.example`
- [ ] Repo skeleton, docker-compose skeleton, CI skeleton (waits for Docker)

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
