---
title: Synergy Dental
emoji: 🦷
colorFrom: green
colorTo: blue
sdk: docker
app_port: 7860
pinned: false
short_description: Dental clinic website, shop, staff dashboard and API
---

# Synergy Dental – Full-Stack Digital Platform (technical demonstration)

> Independent technical demonstration built by a job applicant. Not affiliated with, commissioned
> by or endorsed by Synergy Dental Group. All clinics, people, products and data are fictional.
> No real payments are processed.

| Part | Path |
|---|---|
| Public website and shop (WordPress + WooCommerce) | `/` |
| Staff dashboard (React) | `/dashboard/` |
| Business API docs (FastAPI) | `/api/v1/docs` |

The whole stack (PostgreSQL, MariaDB, PHP-FPM, FastAPI, Caddy) runs in this one container; see
`deploy/huggingface/README.md`. This free Space has no persistent disk, so the demo data is
reset to a clean install whenever the Space restarts.

Source: https://github.com/khelifiMohamedYacine/synergy-dental-platform
