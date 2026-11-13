"""Analytics endpoints (doc 05 §5.5). All require STAFF."""

from datetime import date
from typing import Annotated, Literal

from fastapi import APIRouter, Depends, Query
from pydantic import BaseModel

from app.core.deps import DbSession, StaffUser
from app.core.errors import ApiError
from app.schemas import Summary, TimeseriesRow
from app.services import analytics as service

router = APIRouter(prefix="/analytics", tags=["analytics"])

MAX_RANGE_DAYS = 3 * 366


class TreatmentRow(BaseModel):
    treatment_id: int | None
    treatment_name: str
    total: int
    booked: int
    conversion_rate: float | None


class ClinicRow(BaseModel):
    clinic_id: int
    clinic_name: str
    total: int
    booked: int
    conversion_rate: float | None


def _period(
    from_: Annotated[date | None, Query(alias="from")] = None,
    to: date | None = None,
    clinic_id: int | None = None,
) -> service.Period:
    period = service.Period.build(from_, to, clinic_id)
    if period.start > period.end:
        raise ApiError(
            422,
            "VALIDATION_ERROR",
            "Some fields are invalid.",
            [{"field": "from", "message": "'from' must be on or before 'to'."}],
        )
    if (period.end - period.start).days > MAX_RANGE_DAYS:
        raise ApiError(
            422,
            "VALIDATION_ERROR",
            "Some fields are invalid.",
            [{"field": "from", "message": "The range can be at most 3 years."}],
        )
    return period


PeriodDep = Annotated[service.Period, Depends(_period)]


@router.get("/summary", response_model=Summary)
def summary(db: DbSession, _user: StaffUser, period: PeriodDep) -> dict:
    return service.summary(db, period)


@router.get("/by-treatment", response_model=list[TreatmentRow])
def by_treatment(db: DbSession, _user: StaffUser, period: PeriodDep) -> list[dict]:
    return service.by_treatment(db, period)


@router.get("/by-clinic", response_model=list[ClinicRow])
def by_clinic(db: DbSession, _user: StaffUser, period: PeriodDep) -> list[dict]:
    return service.by_clinic(db, period)


@router.get("/timeseries", response_model=list[TimeseriesRow])
def timeseries(
    db: DbSession,
    _user: StaffUser,
    period: PeriodDep,
    interval: Literal["day", "week", "month"] = "week",
) -> list[dict]:
    return service.timeseries(db, period, interval)
