# 01 – Project Proposal

**Status:** Draft (Phase 1) · **Last updated:** 2026-11-13

> Independent technical demonstration. Not affiliated with or endorsed by Synergy Dental Group.

## 1. Introduction

This project is a working full-stack platform for a fictional multi-site dental group. I am building
it as a technical demonstration for a Full Stack Web Developer application. It shows how a dental
group's public website, online shop and internal enquiry handling can be connected into one system.
It is built and tested as real software, not as a visual mock-up.

## 2. Background

Multi-practice dental groups in the UK usually:

- run several clinics, each with its own location, opening hours and range of treatments;
- earn a large share of revenue from private treatments such as implants, Invisalign/orthodontics,
  cosmetic dentistry and whitening, where patients research online before they enquire;
- depend on a marketing website, often WordPress, to generate those enquiries;
- sell products online, such as whitening kits and aftercare, through e-commerce, often WooCommerce;
- handle incoming enquiries with a mix of email inboxes, spreadsheets and practice-management
  software.

Public sources describe Synergy Dental as a growing North West England group focused on implant,
cosmetic and digital dentistry. This project does not copy their branding, content or assets. It
uses a fictional brand and fictional clinics.

## 3. Business problem

1. **Lost or slow-moving enquiries.** When website enquiries arrive by email, they are hard to
   track, assign and follow up. Nobody can easily see which enquiries were contacted, booked or lost.
2. **No reliable performance data.** Management cannot easily answer questions like "Which treatments
   generate the most enquiries?", "Which clinic converts best?" or "How many enquiries are still
   uncontacted?"
3. **Disconnected systems.** Website content, the shop and enquiry handling run separately, so the
   same information (clinics, treatments) gets maintained in several places.

## 4. Project objectives

| ID | Objective | Measured by |
|---|---|---|
| O1 | Provide a professional, responsive, SEO-friendly public website that generates enquiries | Pages delivered; Lighthouse SEO/Accessibility scores recorded |
| O2 | Capture validated appointment enquiries in a central database with unique references | Enquiry API + tests; duplicate and invalid submissions rejected |
| O3 | Give staff a secure dashboard to manage, filter and progress enquiries | Login, RBAC, status workflow, search/filter/pagination working and tested |
| O4 | Provide analytics calculated from real database records | Analytics endpoints tested against seeded data with known expected values |
| O5 | Show genuine WordPress/PHP and WooCommerce development | Custom theme/child theme, custom plugin, CPTs, custom fields, REST integration, simulated checkout |
| O6 | Show professional engineering practice | Automated tests, security controls, Docker, CI, documentation set 01–11 |

## 5. Project scope

### In scope

- Public website with home, about, treatments (list + detail), clinics, contact and enquiry form.
- Enquiry/appointment-request capture, validation, storage and confirmation.
- Staff dashboard: login, roles, overview, enquiry management, status tracking, clinics,
  treatments/categories, analytics, search, filtering, pagination and charts.
- WooCommerce shop with fictional, non-clinical products and a simulated checkout.
- REST integration between WordPress and the FastAPI backend.
- Docker Compose environment, automated tests, CI on GitHub Actions, and deployment to one cloud VM
  (if practical).

### Out of scope

- Real patient data, clinical records, or any health information beyond "treatment of interest".
- Real payments (only offline or sandbox gateways are used).
- A real appointment calendar or integration with practice-management software (e.g. SOE, Dentally).
  Appointment *requests* are captured; actual bookings are recorded manually by staff.
- Native mobile apps, multi-language support and marketing automation.

## 6. Expected outcomes

- A public GitHub repository that a reviewer can clone and run with `docker compose up`.
- A working, tested demonstration of the full patient journey: browse → enquire → staff follow-up →
  analytics update, plus browse → cart → simulated purchase → order visible to staff.
- A documentation set (01–11) that explains and justifies every major technical decision.
- Supporting presentation material: screenshots, a demo video and interview talking points.
