"""Seed the database (doc 04 §9).

    python -m app.seed               # starter clinics + treatments and the first admin account
    python -m app.seed --demo        # also demo staff accounts and 5,000 sample enquiries
    python -m app.seed --purge-demo  # remove sample enquiries and demo staff accounts

A clean install (the default) has no sample enquiries and no demo accounts. The starter clinics
and treatments are meant to be edited in the dashboard. Demo data is for development only.

Demo data is fictional: names are invented, emails use the reserved .test domain, and phone
numbers come from Ofcom's ranges reserved for TV and drama (020 7946 0xxx, 07700 900xxx).
A fixed random seed makes the data, and therefore the analytics, reproducible.
"""

import argparse
import random
import uuid
from datetime import UTC, datetime, timedelta

from sqlalchemy import delete, func, insert, select
from sqlalchemy.orm import Session

from app.core.config import get_settings
from app.core.db import get_engine
from app.core.security import hash_password
from app.models import (
    Clinic,
    CloseReason,
    Enquiry,
    EnquiryNote,
    EnquiryStatus,
    EnquiryStatusHistory,
    EnquiryType,
    Role,
    TimeWindow,
    Treatment,
    TreatmentCategory,
    User,
)
from app.services.enquiries import generate_reference

RANDOM_SEED = 20261113

WEEKDAYS = {d: [["08:30", "17:30"]] for d in ("mon", "tue", "wed", "thu", "fri")}

CLINICS = [
    {
        "slug": "riverside",
        "name": "Riverside Dental Studio",
        "address_line1": "12 Riverside Walk",
        "city": "Leeds",
        "postcode": "LS1 4AB",
        "phone": "020 7946 0101",
        "email": "riverside@example.test",
        "opening_hours": {**WEEKDAYS, "sat": [["09:00", "13:00"]]},
    },
    {
        "slug": "harbour-view",
        "name": "Harbour View Dental",
        "address_line1": "3 Quayside Terrace",
        "city": "Bristol",
        "postcode": "BS1 5TX",
        "phone": "020 7946 0202",
        "email": "harbourview@example.test",
        "opening_hours": {**WEEKDAYS, "thu": [["08:30", "20:00"]]},
    },
    {
        "slug": "parkside",
        "name": "Parkside Smile Clinic",
        "address_line1": "48 Park Lane",
        "address_line2": "First Floor",
        "city": "Manchester",
        "postcode": "M2 3HQ",
        "phone": "020 7946 0303",
        "email": "parkside@example.test",
        "opening_hours": WEEKDAYS,
    },
    {
        "slug": "old-town",
        "name": "Old Town Dental Practice",
        "address_line1": "7 Market Street",
        "city": "York",
        "postcode": "YO1 8RS",
        "phone": "020 7946 0404",
        "email": "oldtown@example.test",
        "opening_hours": {**WEEKDAYS, "sat": [["09:00", "12:30"]]},
    },
]

CATEGORIES = [
    ("general-dentistry", "General dentistry"),
    ("cosmetic-dentistry", "Cosmetic dentistry"),
    ("orthodontics", "Orthodontics"),
    ("restorative-dentistry", "Restorative dentistry"),
    ("emergency-care", "Emergency care"),
]

# slug, category, name, summary, price_from_pence, minutes, weight (how often enquired about)
TREATMENTS = [
    ("check-up", "general-dentistry", "Dental check-up",
     "A full examination of teeth and gums with advice.", 6500, 30, 14),
    ("hygienist", "general-dentistry", "Hygienist appointment",
     "Professional scale and polish with home-care advice.", 7500, 45, 12),
    ("white-fillings", "general-dentistry", "White fillings",
     "Tooth-coloured fillings that match your natural teeth.", 12000, 45, 6),
    ("teeth-whitening", "cosmetic-dentistry", "Teeth whitening",
     "Dentist-supervised home whitening with custom trays.", 29500, 60, 13),
    ("composite-bonding", "cosmetic-dentistry", "Composite bonding",
     "Reshape chipped or uneven teeth in a single visit.", 25000, 90, 9),
    ("porcelain-veneers", "cosmetic-dentistry", "Porcelain veneers",
     "Thin custom shells that change the shape and shade of teeth.", 75000, 90, 4),
    ("clear-aligners", "orthodontics", "Clear aligners",
     "Nearly invisible removable aligners to straighten teeth.", 199500, 60, 10),
    ("fixed-braces", "orthodontics", "Fixed braces",
     "Traditional or tooth-coloured braces for complex cases.", 250000, 60, 3),
    ("dental-implants", "restorative-dentistry", "Dental implants",
     "A permanent replacement for one or more missing teeth.", 225000, 90, 11),
    ("crowns", "restorative-dentistry", "Crowns",
     "Strengthen and protect a damaged tooth.", 55000, 60, 4),
    ("root-canal", "restorative-dentistry", "Root canal treatment",
     "Treat infection inside a tooth and save it.", 35000, 90, 3),
    ("emergency-appointment", "emergency-care", "Emergency appointment",
     "Same-day help for pain, swelling or a broken tooth.", 9500, 30, 8),
]  # fmt: skip

# Which treatments each clinic does NOT offer (everything else is offered).
NOT_OFFERED = {
    "old-town": {"fixed-braces", "porcelain-veneers"},
    "parkside": {"root-canal"},
}

FIRST = ["Jamie", "Alex", "Sam", "Morgan", "Taylor", "Jordan", "Casey", "Riley", "Avery", "Quinn",
         "Harper", "Rowan", "Elliot", "Robin", "Charlie", "Frankie", "Sasha", "Jesse", "Kai",
         "Noor", "Priya", "Tomasz", "Aisha", "Mateo", "Freya", "Oisin", "Leila",
         "Callum"]  # fmt: skip
LAST = ["Fictional", "Example", "Sample", "Placeholder", "Testwell", "Demoson", "Mockford",
        "Notreal", "Imaginary", "Madeup", "Pretendy", "Storybrook", "Inventa",
        "Fablewood"]  # fmt: skip
MESSAGES = [
    None,
    None,
    "Interested in a consultation.",
    "Could you call me in the afternoon please?",
    "I have been nervous about dentists, is sedation available?",
    "Looking for prices and availability.",
    "Is there parking near the clinic?",
    "My previous dentist retired and I am looking for a new practice.",
]

DEMO_STAFF = [
    ("staff@example.test", "Sam Receptionist", Role.STAFF),
    ("manager@example.test", "Jordan Practice Manager", Role.STAFF),
]


def seed_reference(db: Session) -> None:
    if db.scalar(select(func.count(Clinic.id))):
        print("Reference data already present; skipping.")
        return
    categories = {}
    for order, (slug, name) in enumerate(CATEGORIES):
        categories[slug] = TreatmentCategory(slug=slug, name=name, display_order=order)
        db.add(categories[slug])
    treatments = {}
    for slug, cat, name, summary, price, minutes, _w in TREATMENTS:
        treatments[slug] = Treatment(
            slug=slug,
            category=categories[cat],
            name=name,
            summary=summary,
            price_from_pence=price,
            typical_duration_minutes=minutes,
        )
        db.add(treatments[slug])
    for data in CLINICS:
        clinic = Clinic(**data)
        excluded = NOT_OFFERED.get(data["slug"], set())
        clinic.treatments = [t for s, t in treatments.items() if s not in excluded]
        db.add(clinic)
    db.commit()
    print(f"Seeded {len(CLINICS)} clinics, {len(TREATMENTS)} treatments.")


def seed_users(db: Session, staff_password: str | None) -> None:
    settings = get_settings()
    accounts = []
    if settings.seed_admin_password:
        accounts.append((settings.seed_admin_email.lower(), settings.seed_admin_name,
                         Role.ADMIN, settings.seed_admin_password))  # fmt: skip
    if staff_password:
        accounts += [(e, n, r, staff_password) for e, n, r in DEMO_STAFF]
    for email, name, role, password in accounts:
        if db.scalar(select(User).where(User.email == email)):
            continue
        db.add(User(email=email, full_name=name, role=role, password_hash=hash_password(password)))
        print(f"Created {role.value} user {email}")
    db.commit()


def _path(rng: random.Random, age_days: float) -> list[tuple[EnquiryStatus, CloseReason | None]]:
    """A plausible status journey. Older enquiries have had more time to progress."""
    steps: list[tuple[EnquiryStatus, CloseReason | None]] = []
    maturity = min(1.0, age_days / 21)
    if rng.random() > 0.15 + 0.8 * maturity:
        return steps  # still NEW
    if rng.random() < 0.06:
        return [(EnquiryStatus.CLOSED, rng.choice([CloseReason.DUPLICATE, CloseReason.OTHER]))]
    steps.append((EnquiryStatus.CONTACTED, None))
    if rng.random() < 0.15:
        return [*steps, (EnquiryStatus.CLOSED, CloseReason.UNREACHABLE)]
    if rng.random() > 0.7 * maturity + 0.1:
        return steps
    steps.append((EnquiryStatus.QUALIFIED, None))
    if rng.random() < 0.25:
        return [*steps, (EnquiryStatus.CLOSED, CloseReason.NOT_INTERESTED)]
    if rng.random() > 0.75:
        return steps
    steps.append((EnquiryStatus.BOOKED, None))
    if age_days > 30 and rng.random() < 0.7:
        steps.append((EnquiryStatus.CLOSED, CloseReason.TREATED))
    return steps


def seed_enquiries(db: Session, count: int) -> None:
    if count <= 0:
        return
    if db.scalar(select(func.count(Enquiry.id))):
        print("Enquiries already present; skipping.")
        return
    rng = random.Random(RANDOM_SEED)  # noqa: S311 - reproducible fictional data, not security
    clinics = list(db.scalars(select(Clinic).order_by(Clinic.id)))
    offered = {c.id: [t for t in c.treatments] for c in clinics}
    weights = {slug: w for slug, *_rest, w in TREATMENTS}
    staff_ids = list(db.scalars(select(User.id).order_by(User.id))) or [None]
    now = datetime.now(UTC)
    references: set[str] = set()
    enquiries, history = [], []

    for _ in range(count):
        # Skew towards recent months (growing business) using a triangular distribution.
        age_days = rng.triangular(0, 365, 0)
        created = now - timedelta(days=age_days, minutes=rng.randint(0, 600))
        clinic = rng.choices(clinics, weights=[4, 3, 3, 2][: len(clinics)])[0]
        options = offered[clinic.id]
        treatment = (
            None
            if rng.random() < 0.12
            else rng.choices(options, weights=[weights[t.slug] for t in options])[0]
        )
        etype = EnquiryType.APPOINTMENT if rng.random() < 0.55 else EnquiryType.GENERAL
        ref = generate_reference()
        while ref in references:
            ref = generate_reference()
        references.add(ref)
        first, last = rng.choice(FIRST), rng.choice(LAST)
        eid = uuid.UUID(int=rng.getrandbits(128), version=4)
        path = _path(rng, age_days)
        final_status, final_reason = path[-1] if path else (EnquiryStatus.NEW, None)

        history.append(
            {
                "enquiry_id": eid,
                "from_status": None,
                "to_status": EnquiryStatus.NEW,
                "close_reason": None,
                "changed_by_id": None,
                "changed_at": created,
            }  # fmt: skip
        )
        at, prev = created, EnquiryStatus.NEW
        for status, reason in path:
            gap_hours = rng.uniform(0.5, 30) if prev == EnquiryStatus.NEW else rng.uniform(4, 240)
            at = min(at + timedelta(hours=gap_hours), now - timedelta(minutes=1))
            history.append(
                {"enquiry_id": eid, "from_status": prev, "to_status": status,
                 "close_reason": reason, "changed_by_id": rng.choice(staff_ids), "changed_at": at}
            )  # fmt: skip
            prev = status

        enquiries.append(
            {
                "id": eid,
                "reference": ref,
                "enquiry_type": etype,
                "status": final_status,
                "close_reason": final_reason,
                "full_name": f"{first} {last}",
                "email": f"{first}.{last}.{rng.randint(1, 9999)}@example.test".lower(),
                "phone": f"+447700900{rng.randint(0, 999):03d}",
                "clinic_id": clinic.id,
                "treatment_id": treatment.id if treatment else None,
                "preferred_date": (created + timedelta(days=rng.randint(2, 30))).date()
                if etype == EnquiryType.APPOINTMENT
                else None,
                "preferred_time_window": rng.choice([*TimeWindow, None]),
                "message": rng.choice(MESSAGES),
                "consent": True,
                "consent_at": created,
                "created_at": created,
                "updated_at": at,
            }
        )
    for i in range(0, len(enquiries), 1000):
        db.execute(insert(Enquiry), enquiries[i : i + 1000])
    for i in range(0, len(history), 2000):
        db.execute(insert(EnquiryStatusHistory), history[i : i + 2000])
    db.commit()
    print(f"Seeded {len(enquiries)} enquiries with {len(history)} status changes.")


def purge_demo(db: Session) -> None:
    """Delete sample enquiries (all use the reserved example.test domain) and demo staff."""
    deleted = db.execute(delete(Enquiry).where(Enquiry.email.like("%@example.test"))).rowcount
    print(f"Deleted {deleted} sample enquiries.")
    # The end-to-end tests' staff account (e2e/tests/global-setup.js) is test data too.
    for email in [*(e for e, _n, _r in DEMO_STAFF), "e2e.staff@example.test"]:
        user = db.scalar(select(User).where(User.email == email))
        if user is None:
            continue
        in_use = db.scalar(
            select(func.count(EnquiryStatusHistory.id)).where(
                EnquiryStatusHistory.changed_by_id == user.id
            )
        ) or db.scalar(select(func.count(EnquiryNote.id)).where(EnquiryNote.author_id == user.id))
        if in_use:
            user.is_active = False  # still named in real history: keep the record, block sign-in
            print(f"Deactivated test account {email} (referenced by history).")
        else:
            db.delete(user)
            print(f"Deleted test account {email}.")
    admin = db.scalar(select(User).where(User.full_name.like("%(demo admin)")))
    if admin:
        admin.full_name = get_settings().seed_admin_name
    db.commit()


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--demo", action="store_true", help="Add demo staff and sample enquiries")
    parser.add_argument("--enquiries", type=int, default=5000, help="Sample enquiries with --demo")
    parser.add_argument("--purge-demo", action="store_true", help="Remove all demo data")
    args = parser.parse_args()
    import os

    with Session(get_engine()) as db:
        if args.purge_demo:
            purge_demo(db)
            return
        seed_reference(db)
        staff_password = os.environ.get("SEED_STAFF_PASSWORD") if args.demo else None
        seed_users(db, staff_password)
        if args.demo:
            seed_enquiries(db, args.enquiries)


if __name__ == "__main__":
    main()
