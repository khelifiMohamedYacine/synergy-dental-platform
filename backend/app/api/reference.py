"""Clinics, treatment categories and treatments (doc 05 §5.4).

Public reads return active records only; `include_inactive=true` needs an ADMIN token.
"""

from typing import Annotated

from fastapi import APIRouter, Query, Response
from sqlalchemy import select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session, selectinload

from app.core.deps import AdminUser, DbSession, OptionalUser
from app.core.errors import ApiError, not_found
from app.models import Clinic, Role, Treatment, TreatmentCategory, clinic_treatments
from app.schemas import (
    CategoryCreate,
    CategoryOut,
    CategoryUpdate,
    ClinicCreate,
    ClinicDetailOut,
    ClinicOut,
    ClinicUpdate,
    TreatmentCreate,
    TreatmentOut,
    TreatmentUpdate,
)

router = APIRouter(tags=["reference data"])
PUBLIC_CACHE = "public, max-age=300"


def _check_admin_for_inactive(include_inactive: bool, user) -> None:
    if include_inactive and (user is None or user.role != Role.ADMIN):
        raise ApiError(403, "FORBIDDEN", "include_inactive requires an administrator.")


def _commit_or_conflict(db: Session, what: str) -> None:
    try:
        db.commit()
    except IntegrityError as exc:
        db.rollback()
        constraint = getattr(getattr(exc.orig, "diag", None), "constraint_name", "") or ""
        if constraint.startswith("fk_"):
            raise ApiError(
                422, "VALIDATION_ERROR", f"The {what} refers to a missing record."
            ) from exc
        raise ApiError(409, "CONFLICT", f"A {what} with this slug or name already exists.") from exc


def _treatments_by_ids(db: Session, ids: list[int]) -> list[Treatment]:
    found = list(db.scalars(select(Treatment).where(Treatment.id.in_(ids))))
    missing = set(ids) - {t.id for t in found}
    if missing:
        raise ApiError(
            422,
            "VALIDATION_ERROR",
            "Some fields are invalid.",
            [{"field": "treatment_ids", "message": f"Unknown treatment id(s): {sorted(missing)}"}],
        )
    return found


def _clinic_detail(clinic: Clinic) -> ClinicDetailOut:
    data = ClinicOut.model_validate(clinic).model_dump()
    return ClinicDetailOut(**data, treatment_ids=sorted(t.id for t in clinic.treatments))


# ------------------------------------------------------------------ clinics
@router.get("/clinics", response_model=list[ClinicOut])
def list_clinics(
    response: Response, db: DbSession, user: OptionalUser, include_inactive: bool = False
) -> list[Clinic]:
    _check_admin_for_inactive(include_inactive, user)
    stmt = select(Clinic).order_by(Clinic.name)
    if not include_inactive:
        stmt = stmt.where(Clinic.is_active)
        response.headers["Cache-Control"] = PUBLIC_CACHE
    return list(db.scalars(stmt))


@router.get("/clinics/{clinic_id}", response_model=ClinicDetailOut)
def get_clinic(clinic_id: int, db: DbSession, user: OptionalUser) -> ClinicDetailOut:
    clinic = db.scalar(
        select(Clinic).options(selectinload(Clinic.treatments)).where(Clinic.id == clinic_id)
    )
    if clinic is None or (not clinic.is_active and (user is None or user.role != Role.ADMIN)):
        raise not_found("Clinic")
    return _clinic_detail(clinic)


@router.post("/clinics", response_model=ClinicDetailOut, status_code=201)
def create_clinic(body: ClinicCreate, db: DbSession, _user: AdminUser) -> ClinicDetailOut:
    data = body.model_dump(exclude={"treatment_ids"})
    clinic = Clinic(**data)
    clinic.treatments = _treatments_by_ids(db, body.treatment_ids)
    db.add(clinic)
    _commit_or_conflict(db, "clinic")
    db.refresh(clinic)
    return _clinic_detail(clinic)


@router.patch("/clinics/{clinic_id}", response_model=ClinicDetailOut)
def update_clinic(
    clinic_id: int, body: ClinicUpdate, db: DbSession, _user: AdminUser
) -> ClinicDetailOut:
    clinic = db.get(Clinic, clinic_id)
    if clinic is None:
        raise not_found("Clinic")
    changes = body.model_dump(exclude_unset=True)
    treatment_ids = changes.pop("treatment_ids", None)
    for field, value in changes.items():
        setattr(clinic, field, value)
    if treatment_ids is not None:
        clinic.treatments = _treatments_by_ids(db, treatment_ids)
    _commit_or_conflict(db, "clinic")
    db.refresh(clinic)
    return _clinic_detail(clinic)


# ------------------------------------------------------------------ categories
@router.get("/treatment-categories", response_model=list[CategoryOut])
def list_categories(response: Response, db: DbSession) -> list[TreatmentCategory]:
    response.headers["Cache-Control"] = PUBLIC_CACHE
    return list(
        db.scalars(
            select(TreatmentCategory).order_by(
                TreatmentCategory.display_order, TreatmentCategory.name
            )
        )
    )


@router.post("/treatment-categories", response_model=CategoryOut, status_code=201)
def create_category(body: CategoryCreate, db: DbSession, _user: AdminUser) -> TreatmentCategory:
    category = TreatmentCategory(**body.model_dump())
    db.add(category)
    _commit_or_conflict(db, "category")
    db.refresh(category)
    return category


@router.patch("/treatment-categories/{category_id}", response_model=CategoryOut)
def update_category(
    category_id: int, body: CategoryUpdate, db: DbSession, _user: AdminUser
) -> TreatmentCategory:
    category = db.get(TreatmentCategory, category_id)
    if category is None:
        raise not_found("Category")
    for field, value in body.model_dump(exclude_unset=True).items():
        setattr(category, field, value)
    _commit_or_conflict(db, "category")
    db.refresh(category)
    return category


@router.delete("/treatment-categories/{category_id}", status_code=204)
def delete_category(category_id: int, db: DbSession, _user: AdminUser) -> Response:
    category = db.get(TreatmentCategory, category_id)
    if category is None:
        raise not_found("Category")
    db.delete(category)
    try:
        db.commit()
    except IntegrityError as exc:
        db.rollback()
        raise ApiError(
            409, "CATEGORY_IN_USE", "Move or delete this category's treatments first."
        ) from exc
    return Response(status_code=204)


# ------------------------------------------------------------------ treatments
@router.get("/treatments", response_model=list[TreatmentOut])
def list_treatments(
    response: Response,
    db: DbSession,
    user: OptionalUser,
    category_id: int | None = None,
    clinic_id: int | None = None,
    include_inactive: Annotated[bool, Query()] = False,
) -> list[Treatment]:
    _check_admin_for_inactive(include_inactive, user)
    stmt = select(Treatment).order_by(Treatment.name)
    if not include_inactive:
        stmt = stmt.where(Treatment.is_active)
        response.headers["Cache-Control"] = PUBLIC_CACHE
    if category_id is not None:
        stmt = stmt.where(Treatment.category_id == category_id)
    if clinic_id is not None:
        stmt = stmt.join(clinic_treatments).where(clinic_treatments.c.clinic_id == clinic_id)
    return list(db.scalars(stmt))


@router.post("/treatments", response_model=TreatmentOut, status_code=201)
def create_treatment(body: TreatmentCreate, db: DbSession, _user: AdminUser) -> Treatment:
    treatment = Treatment(**body.model_dump())
    db.add(treatment)
    _commit_or_conflict(db, "treatment")
    db.refresh(treatment)
    return treatment


@router.patch("/treatments/{treatment_id}", response_model=TreatmentOut)
def update_treatment(
    treatment_id: int, body: TreatmentUpdate, db: DbSession, _user: AdminUser
) -> Treatment:
    treatment = db.get(Treatment, treatment_id)
    if treatment is None:
        raise not_found("Treatment")
    for field, value in body.model_dump(exclude_unset=True).items():
        setattr(treatment, field, value)
    _commit_or_conflict(db, "treatment")
    db.refresh(treatment)
    return treatment
