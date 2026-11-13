"""Enquiry endpoints (doc 05 §5.3)."""

import csv
import io
import uuid
from datetime import date
from typing import Annotated, Literal

from fastapi import APIRouter, Depends, Header, Query, Request, Response
from fastapi.responses import JSONResponse, StreamingResponse

from app.core.config import get_settings
from app.core.deps import AdminUser, DbSession, StaffUser, require_service_key
from app.core.errors import ApiError
from app.core.ratelimit import client_ip, enforce
from app.models import Enquiry, EnquiryStatus, EnquiryType
from app.schemas import (
    EnquiryCreate,
    EnquiryCreated,
    EnquiryDetail,
    EnquiryListItem,
    NoteCreate,
    NoteOut,
    Page,
    StatusChange,
)
from app.services import enquiries as service

router = APIRouter(prefix="/enquiries", tags=["enquiries"])

SortField = Literal[
    "created_at",
    "-created_at",
    "preferred_date",
    "-preferred_date",
    "status",
    "-status",
    "full_name",
    "-full_name",
]


def _detail(enquiry: Enquiry) -> EnquiryDetail:
    detail = EnquiryDetail.model_validate(enquiry)
    detail.allowed_transitions = service.allowed_transitions(enquiry.status)
    return detail


def _created_body(enquiry: Enquiry) -> dict:
    return EnquiryCreated(
        id=enquiry.id,
        reference=enquiry.reference,
        status=enquiry.status,
        created_at=enquiry.created_at,
    ).model_dump(mode="json")


@router.post(
    "",
    dependencies=[Depends(require_service_key)],
    response_model=EnquiryCreated,
    status_code=201,
    responses={200: {"description": "Idempotent replay of an earlier request"}},
)
def create_enquiry(
    request: Request,
    db: DbSession,
    body: EnquiryCreate,
    idempotency_key: Annotated[str | None, Header()] = None,
) -> JSONResponse:
    enforce(get_settings().enquiry_rate_limit, "enquiry", client_ip(request))
    key = None
    if idempotency_key:
        try:
            key = uuid.UUID(idempotency_key)
        except ValueError as exc:
            raise ApiError(
                422,
                "VALIDATION_ERROR",
                "Some fields are invalid.",
                [{"field": "Idempotency-Key", "message": "Must be a UUID."}],
            ) from exc
    result = service.create_enquiry(db, body, key)
    if not result.created:
        return JSONResponse(_created_body(result.enquiry), status_code=200)
    return JSONResponse(
        _created_body(result.enquiry),
        status_code=201,
        headers={"Location": f"/api/v1/enquiries/{result.enquiry.id}"},
    )


def _filters(
    q: Annotated[str | None, Query(max_length=120)] = None,
    status: Annotated[list[EnquiryStatus] | None, Query()] = None,
    enquiry_type: EnquiryType | None = None,
    clinic_id: int | None = None,
    treatment_id: int | None = None,
    created_from: date | None = None,
    created_to: date | None = None,
) -> service.EnquiryFilters:
    return service.EnquiryFilters(
        q=q,
        status=status,
        enquiry_type=enquiry_type,
        clinic_id=clinic_id,
        treatment_id=treatment_id,
        created_from=created_from,
        created_to=created_to,
    )


Filters = Annotated[service.EnquiryFilters, Depends(_filters)]


@router.get("", response_model=Page[EnquiryListItem])
def list_enquiries(
    db: DbSession,
    _user: StaffUser,
    filters: Filters,
    sort: SortField = "-created_at",
    page: Annotated[int, Query(ge=1)] = 1,
    page_size: Annotated[int, Query(ge=1, le=100)] = 20,
) -> Page[EnquiryListItem]:
    items, total, pages = service.list_enquiries(db, filters, sort, page, page_size)
    return Page[EnquiryListItem](
        items=[EnquiryListItem.model_validate(e) for e in items],
        page=page,
        page_size=page_size,
        total=total,
        pages=pages,
    )


@router.get("/export.csv", response_class=StreamingResponse)
def export_csv(
    db: DbSession, _user: StaffUser, filters: Filters, sort: SortField = "-created_at"
) -> StreamingResponse:
    items, _, _ = service.list_enquiries(db, filters, sort, page=1, page_size=10_000)
    buffer = io.StringIO()
    writer = csv.writer(buffer)
    writer.writerow(
        [
            "reference",
            "type",
            "status",
            "full_name",
            "email",
            "clinic",
            "treatment",
            "preferred_date",
            "created_at",
        ]  # fmt: skip
    )
    for e in items:
        writer.writerow(
            [
                e.reference,
                e.enquiry_type.value,
                e.status.value,
                _csv_safe(e.full_name),
                _csv_safe(e.email),
                e.clinic.name,
                e.treatment.name if e.treatment else "Not sure yet",
                e.preferred_date.isoformat() if e.preferred_date else "",
                e.created_at.isoformat(),
            ]
        )
    return StreamingResponse(
        iter([buffer.getvalue()]),
        media_type="text/csv",
        headers={"Content-Disposition": 'attachment; filename="enquiries.csv"'},
    )


def _csv_safe(value: str) -> str:
    # Prevent spreadsheet formula injection from user-supplied text.
    return "'" + value if value[:1] in ("=", "+", "-", "@", "\t", "\r") else value


@router.get("/{enquiry_id}", response_model=EnquiryDetail)
def get_enquiry(enquiry_id: uuid.UUID, db: DbSession, _user: StaffUser) -> EnquiryDetail:
    return _detail(service.get_enquiry(db, enquiry_id))


@router.patch("/{enquiry_id}/status", response_model=EnquiryDetail)
def change_status(
    enquiry_id: uuid.UUID, body: StatusChange, db: DbSession, user: StaffUser
) -> EnquiryDetail:
    return _detail(service.change_status(db, enquiry_id, body, user))


@router.post("/{enquiry_id}/notes", response_model=NoteOut, status_code=201)
def add_note(enquiry_id: uuid.UUID, body: NoteCreate, db: DbSession, user: StaffUser) -> NoteOut:
    return NoteOut.model_validate(service.add_note(db, enquiry_id, body.body, user))


@router.delete("/{enquiry_id}", status_code=204)
def delete_enquiry(enquiry_id: uuid.UUID, db: DbSession, _user: AdminUser) -> Response:
    service.delete_enquiry(db, enquiry_id)
    return Response(status_code=204)
