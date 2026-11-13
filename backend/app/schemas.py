"""Pydantic request/response models. Contract: docs/05-api-documentation.md."""

import re
import uuid
from datetime import date, datetime, timedelta
from typing import Annotated
from zoneinfo import ZoneInfo

import email_validator
import phonenumbers
from pydantic import (
    BaseModel,
    ConfigDict,
    EmailStr,
    Field,
    StringConstraints,
    field_validator,
    model_validator,
)

from app.models import CloseReason, EnquiryStatus, EnquiryType, Role, TimeWindow

# All demo data uses the reserved ".test" domain (RFC 6761) so no real inbox is ever involved.
# email-validator rejects special-use domains by default; allow only that one.
email_validator.SPECIAL_USE_DOMAIN_NAMES = [
    d for d in email_validator.SPECIAL_USE_DOMAIN_NAMES if d != "test"
]

LONDON = ZoneInfo("Europe/London")
MAX_DAYS_AHEAD = 180
NAME_RE = re.compile(r"^[^\W\d_]+(?:[ '\-.][^\W\d_]*)*$", re.UNICODE)

# Ofcom ranges reserved for TV/drama (fictional) use. libphonenumber reports them as invalid,
# but the demo uses them on purpose so that no real person's number is ever stored.
OFCOM_DRAMA_PREFIXES = (
    "7700900",  # mobile 07700 900000-900999
    "2079460",  # London 020 7946 0000-0999
    "1134960",  # Leeds
    "1214960",  # Birmingham
    "1314960",  # Edinburgh
    "1614960",  # Manchester
    "1174960",  # Bristol
)


def _is_drama_number(parsed: phonenumbers.PhoneNumber) -> bool:
    national = str(parsed.national_number)
    return (
        parsed.country_code == 44
        and phonenumbers.is_possible_number(parsed)
        and national.startswith(OFCOM_DRAMA_PREFIXES)
    )


Slug = Annotated[
    str, StringConstraints(pattern=r"^[a-z0-9]+(-[a-z0-9]+)*$", min_length=1, max_length=80)
]


def london_today() -> date:
    return datetime.now(LONDON).date()


class Strict(BaseModel):
    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)


class Out(BaseModel):
    model_config = ConfigDict(from_attributes=True)


class Page[T](BaseModel):
    items: list[T]
    page: int
    page_size: int
    total: int
    pages: int


# ---------------------------------------------------------------- users / auth
class UserOut(Out):
    id: int
    email: str
    full_name: str
    role: Role


class UserAdminOut(UserOut):
    is_active: bool
    last_login_at: datetime | None
    created_at: datetime


class TokenOut(BaseModel):
    access_token: str
    token_type: str = "bearer"  # noqa: S105 - OAuth2 token type, not a secret
    expires_in: int
    user: UserOut


COMMON_PASSWORDS = {
    "password1234",
    "passwordpassword",
    "123456789012",
    "qwertyuiopas",
    "letmeinletmein",
    "welcome12345",
    "administrator",
    "iloveyou1234",
    "changeme1234",
    "synergydental",
}


def _check_password(value: str) -> str:
    if len(value) < 12:
        raise ValueError("Password must be at least 12 characters.")
    if value.lower() in COMMON_PASSWORDS:
        raise ValueError("This password is too common.")
    return value


class UserCreate(Strict):
    email: EmailStr
    full_name: Annotated[str, StringConstraints(min_length=2, max_length=120)]
    role: Role = Role.STAFF
    password: str

    _email_lower = field_validator("email")(lambda v: v.lower())
    _password = field_validator("password")(_check_password)


class UserUpdate(Strict):
    full_name: Annotated[str, StringConstraints(min_length=2, max_length=120)] | None = None
    role: Role | None = None
    is_active: bool | None = None
    password: str | None = None

    @field_validator("password")
    @classmethod
    def _password(cls, v: str | None) -> str | None:
        return None if v is None else _check_password(v)


# ---------------------------------------------------------------- reference data
class CategoryOut(Out):
    id: int
    slug: str
    name: str
    display_order: int


class CategoryCreate(Strict):
    slug: Slug
    name: Annotated[str, StringConstraints(min_length=2, max_length=80)]
    display_order: int = 0


class CategoryUpdate(Strict):
    slug: Slug | None = None
    name: Annotated[str, StringConstraints(min_length=2, max_length=80)] | None = None
    display_order: int | None = None


class TreatmentOut(Out):
    id: int
    category_id: int
    slug: str
    name: str
    summary: str | None
    price_from_pence: int | None
    typical_duration_minutes: int | None
    is_active: bool


class TreatmentCreate(Strict):
    category_id: int
    slug: Slug
    name: Annotated[str, StringConstraints(min_length=2, max_length=120)]
    summary: Annotated[str, StringConstraints(max_length=300)] | None = None
    price_from_pence: Annotated[int, Field(ge=0)] | None = None
    typical_duration_minutes: Annotated[int, Field(gt=0, le=1440)] | None = None
    is_active: bool = True


class TreatmentUpdate(Strict):
    category_id: int | None = None
    slug: Slug | None = None
    name: Annotated[str, StringConstraints(min_length=2, max_length=120)] | None = None
    summary: Annotated[str, StringConstraints(max_length=300)] | None = None
    price_from_pence: Annotated[int, Field(ge=0)] | None = None
    typical_duration_minutes: Annotated[int, Field(gt=0, le=1440)] | None = None
    is_active: bool | None = None


DAYS = ("mon", "tue", "wed", "thu", "fri", "sat", "sun")
HHMM = re.compile(r"^([01]\d|2[0-3]):[0-5]\d$")
OpeningHours = dict[str, list[tuple[str, str]]]


def _check_hours(value: OpeningHours) -> OpeningHours:
    for day, ranges in value.items():
        if day not in DAYS:
            raise ValueError(f"Unknown day '{day}'. Use mon..sun.")
        for start, end in ranges:
            if not (HHMM.match(start) and HHMM.match(end)) or start >= end:
                raise ValueError(f"Invalid opening time range {start}-{end} on {day}.")
    return value


class ClinicOut(Out):
    id: int
    slug: str
    name: str
    address_line1: str
    address_line2: str | None
    city: str
    postcode: str
    phone: str
    email: str | None
    opening_hours: dict
    is_active: bool


class ClinicDetailOut(ClinicOut):
    treatment_ids: list[int]


class ClinicCreate(Strict):
    slug: Slug
    name: Annotated[str, StringConstraints(min_length=2, max_length=120)]
    address_line1: Annotated[str, StringConstraints(min_length=2, max_length=120)]
    address_line2: Annotated[str, StringConstraints(max_length=120)] | None = None
    city: Annotated[str, StringConstraints(min_length=2, max_length=80)]
    postcode: Annotated[str, StringConstraints(min_length=5, max_length=10)]
    phone: Annotated[str, StringConstraints(min_length=6, max_length=32)]
    email: EmailStr | None = None
    opening_hours: OpeningHours = Field(default_factory=dict)
    is_active: bool = True
    treatment_ids: list[int] = Field(default_factory=list)

    _hours = field_validator("opening_hours")(_check_hours)


class ClinicUpdate(Strict):
    slug: Slug | None = None
    name: Annotated[str, StringConstraints(min_length=2, max_length=120)] | None = None
    address_line1: Annotated[str, StringConstraints(min_length=2, max_length=120)] | None = None
    address_line2: Annotated[str, StringConstraints(max_length=120)] | None = None
    city: Annotated[str, StringConstraints(min_length=2, max_length=80)] | None = None
    postcode: Annotated[str, StringConstraints(min_length=5, max_length=10)] | None = None
    phone: Annotated[str, StringConstraints(min_length=6, max_length=32)] | None = None
    email: EmailStr | None = None
    opening_hours: OpeningHours | None = None
    is_active: bool | None = None
    treatment_ids: list[int] | None = None

    @field_validator("opening_hours")
    @classmethod
    def _hours(cls, v: OpeningHours | None) -> OpeningHours | None:
        return None if v is None else _check_hours(v)


# ---------------------------------------------------------------- enquiries
class EnquiryCreate(Strict):
    enquiry_type: EnquiryType
    full_name: str
    email: EmailStr
    phone: str
    clinic_slug: Slug
    treatment_slug: Slug | None = None
    preferred_date: date | None = None
    preferred_time_window: TimeWindow | None = None
    message: Annotated[str, StringConstraints(max_length=2000)] | None = None
    consent: bool

    @field_validator("full_name")
    @classmethod
    def _name(cls, v: str) -> str:
        v = " ".join(v.split())
        if not 2 <= len(v) <= 120:
            raise ValueError("Enter your full name (2 to 120 characters).")
        if not NAME_RE.match(v):
            raise ValueError("Use letters, spaces, apostrophes, hyphens and full stops only.")
        return v

    @field_validator("email")
    @classmethod
    def _email(cls, v: str) -> str:
        return v.lower()

    @field_validator("phone")
    @classmethod
    def _phone(cls, v: str) -> str:
        try:
            parsed = phonenumbers.parse(v, "GB")
        except phonenumbers.NumberParseException as exc:
            raise ValueError("Enter a valid UK phone number.") from exc
        if not (phonenumbers.is_valid_number(parsed) or _is_drama_number(parsed)):
            raise ValueError("Enter a valid UK phone number.")
        return phonenumbers.format_number(parsed, phonenumbers.PhoneNumberFormat.E164)

    @field_validator("message")
    @classmethod
    def _message(cls, v: str | None) -> str | None:
        return v or None

    @field_validator("consent")
    @classmethod
    def _consent(cls, v: bool) -> bool:
        if not v:
            raise ValueError("Consent is required to send an enquiry.")
        return v

    @model_validator(mode="after")
    def _dates(self) -> "EnquiryCreate":
        if self.enquiry_type == EnquiryType.APPOINTMENT and self.preferred_date is None:
            raise ValueError("preferred_date: A preferred date is required for appointments.")
        if self.preferred_date is not None:
            today = london_today()
            if self.preferred_date < today:
                raise ValueError("preferred_date: Date cannot be in the past.")
            if self.preferred_date > today + timedelta(days=MAX_DAYS_AHEAD):
                raise ValueError(
                    f"preferred_date: Date must be within {MAX_DAYS_AHEAD} days from today."
                )
        return self


class EnquiryCreated(BaseModel):
    id: uuid.UUID
    reference: str
    status: EnquiryStatus
    created_at: datetime


class Ref(Out):
    id: int
    name: str


class UserRef(Out):
    id: int
    full_name: str


class EnquiryListItem(Out):
    id: uuid.UUID
    reference: str
    enquiry_type: EnquiryType
    status: EnquiryStatus
    full_name: str
    email: str
    clinic: Ref
    treatment: Ref | None
    preferred_date: date | None
    preferred_time_window: TimeWindow | None
    created_at: datetime


class HistoryOut(Out):
    from_status: EnquiryStatus | None
    to_status: EnquiryStatus
    close_reason: CloseReason | None
    changed_by: UserRef | None
    changed_at: datetime


class NoteOut(Out):
    id: int
    body: str
    author: UserRef
    created_at: datetime


class EnquiryDetail(EnquiryListItem):
    phone: str
    message: str | None
    consent_at: datetime
    close_reason: CloseReason | None
    updated_at: datetime
    history: list[HistoryOut]
    notes: list[NoteOut]
    allowed_transitions: list[EnquiryStatus] = []


class StatusChange(Strict):
    status: EnquiryStatus
    close_reason: CloseReason | None = None
    note: Annotated[str, StringConstraints(max_length=2000)] | None = None

    @model_validator(mode="after")
    def _reason(self) -> "StatusChange":
        if self.status == EnquiryStatus.CLOSED and self.close_reason is None:
            raise ValueError("close_reason: A reason is required when closing an enquiry.")
        if self.status != EnquiryStatus.CLOSED and self.close_reason is not None:
            raise ValueError("close_reason: Only closed enquiries can have a close reason.")
        return self


class NoteCreate(Strict):
    body: Annotated[str, StringConstraints(min_length=1, max_length=2000)]


# ---------------------------------------------------------------- analytics / shop
class Summary(BaseModel):
    total: int
    by_status: dict[EnquiryStatus, int]
    booked: int
    conversion_rate: float | None
    median_hours_to_contact: float | None


class GroupRow(BaseModel):
    id: int | None
    name: str
    total: int
    booked: int
    conversion_rate: float | None


class TimeseriesRow(BaseModel):
    period_start: date
    total: int
    booked: int


class ShopOrder(BaseModel):
    number: str
    status: str
    total_pence: int
    created_at: datetime
    item_count: int


class ShopSummary(BaseModel):
    orders_last_30_days: int
    revenue_last_30_days_pence: int
    currency: str = "GBP"
    recent_orders: list[ShopOrder]
