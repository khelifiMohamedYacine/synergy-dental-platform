"""Enquiry business rules: creation, duplicate prevention, status machine, search.

Rules come from doc 04 §6 and doc 05 §5.3 / §6. Kept free of HTTP concerns so they can be
unit-tested directly.
"""

import math
import secrets
import uuid
from dataclasses import dataclass
from datetime import date, datetime, timedelta

from sqlalchemy import Select, func, or_, select, text
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session, joinedload, selectinload

from app.core.errors import ApiError, not_found
from app.models import (
    Clinic,
    Enquiry,
    EnquiryNote,
    EnquiryStatus,
    EnquiryStatusHistory,
    EnquiryType,
    Treatment,
    User,
)
from app.schemas import LONDON, EnquiryCreate, StatusChange

REFERENCE_ALPHABET = "23456789ABCDEFGHJKMNPQRSTUVWXYZ"
REFERENCE_ATTEMPTS = 5
DUPLICATE_WINDOW = timedelta(hours=24)

TRANSITIONS: dict[EnquiryStatus, tuple[EnquiryStatus, ...]] = {
    EnquiryStatus.NEW: (EnquiryStatus.CONTACTED, EnquiryStatus.CLOSED),
    EnquiryStatus.CONTACTED: (EnquiryStatus.QUALIFIED, EnquiryStatus.CLOSED),
    EnquiryStatus.QUALIFIED: (EnquiryStatus.BOOKED, EnquiryStatus.CLOSED),
    EnquiryStatus.BOOKED: (EnquiryStatus.CLOSED, EnquiryStatus.QUALIFIED),
    EnquiryStatus.CLOSED: (),
}

SORTS = {
    "created_at": Enquiry.created_at.asc(),
    "-created_at": Enquiry.created_at.desc(),
    "preferred_date": Enquiry.preferred_date.asc().nulls_last(),
    "-preferred_date": Enquiry.preferred_date.desc().nulls_last(),
    "status": Enquiry.status.asc(),
    "-status": Enquiry.status.desc(),
    "full_name": Enquiry.full_name.asc(),
    "-full_name": Enquiry.full_name.desc(),
}


def generate_reference() -> str:
    return "SD-" + "".join(secrets.choice(REFERENCE_ALPHABET) for _ in range(6))


def allowed_transitions(status: EnquiryStatus) -> list[EnquiryStatus]:
    return list(TRANSITIONS[status])


@dataclass
class CreateResult:
    enquiry: Enquiry
    created: bool  # False = idempotent replay


def _by_idempotency_key(db: Session, key: uuid.UUID) -> Enquiry | None:
    return db.scalar(select(Enquiry).where(Enquiry.idempotency_key == key))


def _resolve_reference_data(db: Session, data: EnquiryCreate) -> tuple[Clinic, Treatment | None]:
    clinic = db.scalar(select(Clinic).where(Clinic.slug == data.clinic_slug, Clinic.is_active))
    if clinic is None:
        raise ApiError(
            422,
            "VALIDATION_ERROR",
            "Some fields are invalid.",
            [{"field": "clinic_slug", "message": "Choose one of our clinics."}],
        )
    treatment = None
    if data.treatment_slug:
        treatment = db.scalar(
            select(Treatment)
            .join(Treatment.clinics)
            .where(
                Treatment.slug == data.treatment_slug,
                Treatment.is_active,
                Clinic.id == clinic.id,
            )
        )
        if treatment is None:
            raise ApiError(
                422,
                "VALIDATION_ERROR",
                "Some fields are invalid.",
                [
                    {
                        "field": "treatment_slug",
                        "message": "This treatment is not offered at the chosen clinic.",
                    }
                ],
            )
    return clinic, treatment


def create_enquiry(
    db: Session, data: EnquiryCreate, idempotency_key: uuid.UUID | None
) -> CreateResult:
    if idempotency_key is not None:
        existing = _by_idempotency_key(db, idempotency_key)
        if existing is not None:
            return CreateResult(existing, created=False)

    clinic, treatment = _resolve_reference_data(db, data)

    # Serialise submissions per email address so two near-simultaneous requests cannot both
    # pass the duplicate check (doc 04 §6.1). Released automatically at commit/rollback.
    db.execute(text("SELECT pg_advisory_xact_lock(hashtext(:email))"), {"email": data.email})

    treatment_match = (
        Enquiry.treatment_id.is_(None)
        if treatment is None
        else Enquiry.treatment_id == treatment.id
    )
    duplicate = db.scalar(
        select(Enquiry.reference).where(
            func.lower(Enquiry.email) == data.email,
            Enquiry.clinic_id == clinic.id,
            treatment_match,
            Enquiry.status != EnquiryStatus.CLOSED,
            Enquiry.created_at >= func.now() - DUPLICATE_WINDOW,
        )
    )
    if duplicate:
        db.rollback()
        raise ApiError(
            409,
            "DUPLICATE_ENQUIRY",
            "We already have a recent request from you for this clinic and treatment.",
            [{"field": "reference", "message": duplicate}],
        )

    for attempt in range(REFERENCE_ATTEMPTS):
        enquiry = Enquiry(
            reference=generate_reference(),
            enquiry_type=data.enquiry_type,
            full_name=data.full_name,
            email=data.email,
            phone=data.phone,
            clinic_id=clinic.id,
            treatment_id=treatment.id if treatment else None,
            preferred_date=data.preferred_date,
            preferred_time_window=data.preferred_time_window,
            message=data.message,
            consent=True,
            idempotency_key=idempotency_key,
        )
        savepoint = db.begin_nested()
        try:
            db.add(enquiry)
            db.flush()
            db.add(
                EnquiryStatusHistory(
                    enquiry_id=enquiry.id, from_status=None, to_status=EnquiryStatus.NEW
                )
            )
            db.flush()
            savepoint.commit()
            break
        except IntegrityError as exc:
            savepoint.rollback()
            constraint = getattr(getattr(exc.orig, "diag", None), "constraint_name", "")
            if constraint == "uq_enquiries_idempotency_key" and idempotency_key is not None:
                # Lost a race with an identical request: return the winner.
                db.rollback()
                winner = _by_idempotency_key(db, idempotency_key)
                if winner is not None:
                    return CreateResult(winner, created=False)
            if constraint != "uq_enquiries_reference" or attempt == REFERENCE_ATTEMPTS - 1:
                raise
    db.commit()
    db.refresh(enquiry)
    return CreateResult(enquiry, created=True)


def _detail_query() -> Select:
    return select(Enquiry).options(
        joinedload(Enquiry.clinic),
        joinedload(Enquiry.treatment),
        selectinload(Enquiry.history).joinedload(EnquiryStatusHistory.changed_by),
        selectinload(Enquiry.notes).joinedload(EnquiryNote.author),
    )


def get_enquiry(db: Session, enquiry_id: uuid.UUID) -> Enquiry:
    enquiry = db.scalar(_detail_query().where(Enquiry.id == enquiry_id))
    if enquiry is None:
        raise not_found("Enquiry")
    return enquiry


def change_status(db: Session, enquiry_id: uuid.UUID, change: StatusChange, user: User) -> Enquiry:
    # Row lock: two staff changing the same enquiry cannot both act on a stale status.
    enquiry = db.scalar(select(Enquiry).where(Enquiry.id == enquiry_id).with_for_update())
    if enquiry is None:
        raise not_found("Enquiry")
    allowed = TRANSITIONS[enquiry.status]
    if change.status not in allowed:
        db.rollback()
        raise ApiError(
            409,
            "INVALID_TRANSITION",
            f"An enquiry that is {enquiry.status.value} cannot move to {change.status.value}.",
            [{"field": "status", "message": s.value} for s in allowed],
        )
    from_status = enquiry.status
    enquiry.status = change.status
    enquiry.close_reason = change.close_reason
    db.add(
        EnquiryStatusHistory(
            enquiry_id=enquiry.id,
            from_status=from_status,
            to_status=change.status,
            close_reason=change.close_reason,
            changed_by_id=user.id,
        )
    )
    if change.note:
        db.add(EnquiryNote(enquiry_id=enquiry.id, author_id=user.id, body=change.note))
    db.commit()
    db.expire_all()
    return get_enquiry(db, enquiry_id)


def add_note(db: Session, enquiry_id: uuid.UUID, body: str, user: User) -> EnquiryNote:
    if db.get(Enquiry, enquiry_id) is None:
        raise not_found("Enquiry")
    note = EnquiryNote(enquiry_id=enquiry_id, author_id=user.id, body=body)
    db.add(note)
    db.commit()
    db.refresh(note)
    return note


def delete_enquiry(db: Session, enquiry_id: uuid.UUID) -> None:
    enquiry = db.get(Enquiry, enquiry_id)
    if enquiry is None:
        raise not_found("Enquiry")
    db.delete(enquiry)
    db.commit()


@dataclass
class EnquiryFilters:
    q: str | None = None
    status: list[EnquiryStatus] | None = None
    enquiry_type: EnquiryType | None = None
    clinic_id: int | None = None
    treatment_id: int | None = None
    created_from: date | None = None
    created_to: date | None = None


def _london_midnight_utc(day: date) -> datetime:
    return datetime(day.year, day.month, day.day, tzinfo=LONDON)


def filtered_query(filters: EnquiryFilters) -> Select:
    stmt = select(Enquiry)
    if filters.q:
        term = filters.q.strip()
        if term.upper().startswith("SD-"):
            stmt = stmt.where(Enquiry.reference.startswith(term.upper(), autoescape=True))
        else:
            escaped = term.replace("\\", "\\\\").replace("%", r"\%").replace("_", r"\_")
            pattern = f"%{escaped}%"
            stmt = stmt.where(
                or_(
                    Enquiry.full_name.ilike(pattern, escape="\\"),
                    Enquiry.email.ilike(pattern, escape="\\"),
                )
            )
    if filters.status:
        stmt = stmt.where(Enquiry.status.in_(filters.status))
    if filters.enquiry_type:
        stmt = stmt.where(Enquiry.enquiry_type == filters.enquiry_type)
    if filters.clinic_id:
        stmt = stmt.where(Enquiry.clinic_id == filters.clinic_id)
    if filters.treatment_id:
        stmt = stmt.where(Enquiry.treatment_id == filters.treatment_id)
    if filters.created_from:
        stmt = stmt.where(Enquiry.created_at >= _london_midnight_utc(filters.created_from))
    if filters.created_to:
        end = _london_midnight_utc(filters.created_to + timedelta(days=1))
        stmt = stmt.where(Enquiry.created_at < end)
    return stmt


def list_enquiries(
    db: Session, filters: EnquiryFilters, sort: str, page: int, page_size: int
) -> tuple[list[Enquiry], int, int]:
    base = filtered_query(filters)
    total = db.scalar(select(func.count()).select_from(base.subquery())) or 0
    stmt = (
        base.options(joinedload(Enquiry.clinic), joinedload(Enquiry.treatment))
        .order_by(SORTS[sort], Enquiry.id)
        .offset((page - 1) * page_size)
        .limit(page_size)
    )
    items = list(db.scalars(stmt))
    return items, total, max(1, math.ceil(total / page_size))
