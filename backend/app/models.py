"""SQLAlchemy ORM models. Contract: docs/04-database-design.md."""

import enum
import uuid
from datetime import date, datetime

from sqlalchemy import (
    BigInteger,
    Boolean,
    CheckConstraint,
    Column,
    Date,
    DateTime,
    ForeignKey,
    Identity,
    Integer,
    MetaData,
    SmallInteger,
    String,
    Table,
    Text,
    func,
    text,
)
from sqlalchemy.dialects.postgresql import ENUM, JSONB, UUID
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, relationship

NAMING_CONVENTION = {
    "ix": "ix_%(column_0_label)s",
    "uq": "uq_%(table_name)s_%(column_0_name)s",
    "ck": "ck_%(table_name)s_%(constraint_name)s",
    "fk": "fk_%(table_name)s_%(column_0_name)s_%(referred_table_name)s",
    "pk": "pk_%(table_name)s",
}


class Base(DeclarativeBase):
    metadata = MetaData(naming_convention=NAMING_CONVENTION)


class Role(enum.StrEnum):
    ADMIN = "ADMIN"
    STAFF = "STAFF"


class EnquiryType(enum.StrEnum):
    GENERAL = "GENERAL"
    APPOINTMENT = "APPOINTMENT"


class EnquiryStatus(enum.StrEnum):
    NEW = "NEW"
    CONTACTED = "CONTACTED"
    QUALIFIED = "QUALIFIED"
    BOOKED = "BOOKED"
    CLOSED = "CLOSED"


class CloseReason(enum.StrEnum):
    TREATED = "TREATED"
    NOT_INTERESTED = "NOT_INTERESTED"
    UNREACHABLE = "UNREACHABLE"
    DUPLICATE = "DUPLICATE"
    OTHER = "OTHER"


class TimeWindow(enum.StrEnum):
    MORNING = "MORNING"
    AFTERNOON = "AFTERNOON"
    EVENING = "EVENING"


def _pg_enum(cls: type[enum.Enum], name: str) -> ENUM:
    # Types are created by migration 0001, never implicitly by SQLAlchemy.
    return ENUM(cls, name=name, create_type=False, values_callable=lambda e: [m.value for m in e])


user_role = _pg_enum(Role, "user_role")
enquiry_type = _pg_enum(EnquiryType, "enquiry_type")
enquiry_status = _pg_enum(EnquiryStatus, "enquiry_status")
close_reason = _pg_enum(CloseReason, "close_reason")
time_window = _pg_enum(TimeWindow, "time_window")

SLUG_CHECK = "slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'"


def _created() -> Mapped[datetime]:
    return mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)


def _updated() -> Mapped[datetime]:
    return mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )


class User(Base):
    __tablename__ = "users"
    __table_args__ = (CheckConstraint("email = lower(email)", name="email_lower"),)

    id: Mapped[int] = mapped_column(Integer, Identity(always=True), primary_key=True)
    email: Mapped[str] = mapped_column(String(254), unique=True, nullable=False)
    full_name: Mapped[str] = mapped_column(String(120), nullable=False)
    password_hash: Mapped[str] = mapped_column(String(255), nullable=False)
    role: Mapped[Role] = mapped_column(user_role, nullable=False, server_default="STAFF")
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, server_default=text("true"))
    last_login_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    created_at: Mapped[datetime] = _created()
    updated_at: Mapped[datetime] = _updated()


class RefreshToken(Base):
    __tablename__ = "refresh_tokens"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()")
    )
    user_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    token_hash: Mapped[str] = mapped_column(String(64), unique=True, nullable=False)
    expires_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    revoked_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    replaced_by_id: Mapped[uuid.UUID | None] = mapped_column(
        ForeignKey("refresh_tokens.id", ondelete="SET NULL")
    )
    created_at: Mapped[datetime] = _created()

    user: Mapped[User] = relationship()


clinic_treatments = Table(
    "clinic_treatments",
    Base.metadata,
    Column("clinic_id", ForeignKey("clinics.id", ondelete="CASCADE"), primary_key=True),
    Column("treatment_id", ForeignKey("treatments.id", ondelete="CASCADE"), primary_key=True),
)


class Clinic(Base):
    __tablename__ = "clinics"
    __table_args__ = (CheckConstraint(SLUG_CHECK, name="slug_format"),)

    id: Mapped[int] = mapped_column(Integer, Identity(always=True), primary_key=True)
    slug: Mapped[str] = mapped_column(String(80), unique=True, nullable=False)
    name: Mapped[str] = mapped_column(String(120), nullable=False)
    address_line1: Mapped[str] = mapped_column(String(120), nullable=False)
    address_line2: Mapped[str | None] = mapped_column(String(120))
    city: Mapped[str] = mapped_column(String(80), nullable=False)
    postcode: Mapped[str] = mapped_column(String(10), nullable=False)
    phone: Mapped[str] = mapped_column(String(32), nullable=False)
    email: Mapped[str | None] = mapped_column(String(254))
    opening_hours: Mapped[dict] = mapped_column(
        JSONB, nullable=False, server_default=text("'{}'::jsonb")
    )
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, server_default=text("true"))
    created_at: Mapped[datetime] = _created()
    updated_at: Mapped[datetime] = _updated()

    treatments: Mapped[list["Treatment"]] = relationship(
        secondary=clinic_treatments, back_populates="clinics", order_by="Treatment.name"
    )


class TreatmentCategory(Base):
    __tablename__ = "treatment_categories"
    __table_args__ = (CheckConstraint(SLUG_CHECK, name="slug_format"),)

    id: Mapped[int] = mapped_column(Integer, Identity(always=True), primary_key=True)
    slug: Mapped[str] = mapped_column(String(80), unique=True, nullable=False)
    name: Mapped[str] = mapped_column(String(80), unique=True, nullable=False)
    display_order: Mapped[int] = mapped_column(SmallInteger, nullable=False, server_default="0")


class Treatment(Base):
    __tablename__ = "treatments"
    __table_args__ = (
        CheckConstraint(SLUG_CHECK, name="slug_format"),
        CheckConstraint("price_from_pence >= 0", name="price_non_negative"),
        CheckConstraint("typical_duration_minutes > 0", name="duration_positive"),
    )

    id: Mapped[int] = mapped_column(Integer, Identity(always=True), primary_key=True)
    category_id: Mapped[int] = mapped_column(
        ForeignKey("treatment_categories.id", ondelete="RESTRICT"), nullable=False, index=True
    )
    slug: Mapped[str] = mapped_column(String(80), unique=True, nullable=False)
    name: Mapped[str] = mapped_column(String(120), nullable=False)
    summary: Mapped[str | None] = mapped_column(String(300))
    price_from_pence: Mapped[int | None] = mapped_column(Integer)
    typical_duration_minutes: Mapped[int | None] = mapped_column(SmallInteger)
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, server_default=text("true"))
    created_at: Mapped[datetime] = _created()
    updated_at: Mapped[datetime] = _updated()

    category: Mapped[TreatmentCategory] = relationship()
    clinics: Mapped[list[Clinic]] = relationship(
        secondary=clinic_treatments, back_populates="treatments"
    )


class Enquiry(Base):
    __tablename__ = "enquiries"
    __table_args__ = (
        CheckConstraint("reference ~ '^SD-[2-9A-HJKMNP-Z]{6}$'", name="reference_format"),
        CheckConstraint("length(trim(full_name)) >= 2", name="full_name_length"),
        CheckConstraint("length(message) <= 2000", name="message_length"),
        CheckConstraint("consent", name="consent_required"),
        CheckConstraint("(status = 'CLOSED') = (close_reason IS NOT NULL)", name="close_reason"),
        CheckConstraint(
            "enquiry_type <> 'APPOINTMENT' OR preferred_date IS NOT NULL",
            name="appointment_date",
        ),
    )

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()")
    )
    reference: Mapped[str] = mapped_column(String(9), unique=True, nullable=False)
    enquiry_type: Mapped[EnquiryType] = mapped_column(enquiry_type, nullable=False)
    status: Mapped[EnquiryStatus] = mapped_column(
        enquiry_status, nullable=False, server_default="NEW"
    )
    close_reason: Mapped[CloseReason | None] = mapped_column(close_reason)
    full_name: Mapped[str] = mapped_column(String(120), nullable=False)
    email: Mapped[str] = mapped_column(String(254), nullable=False)
    phone: Mapped[str] = mapped_column(String(32), nullable=False)
    clinic_id: Mapped[int] = mapped_column(
        ForeignKey("clinics.id", ondelete="RESTRICT"), nullable=False
    )
    treatment_id: Mapped[int | None] = mapped_column(
        ForeignKey("treatments.id", ondelete="RESTRICT")
    )
    preferred_date: Mapped[date | None] = mapped_column(Date)
    preferred_time_window: Mapped[TimeWindow | None] = mapped_column(time_window)
    message: Mapped[str | None] = mapped_column(Text)
    consent: Mapped[bool] = mapped_column(Boolean, nullable=False)
    consent_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    idempotency_key: Mapped[uuid.UUID | None] = mapped_column(UUID(as_uuid=True), unique=True)
    created_at: Mapped[datetime] = _created()
    updated_at: Mapped[datetime] = _updated()

    clinic: Mapped[Clinic] = relationship()
    treatment: Mapped[Treatment | None] = relationship()
    history: Mapped[list["EnquiryStatusHistory"]] = relationship(
        back_populates="enquiry",
        order_by="(EnquiryStatusHistory.changed_at, EnquiryStatusHistory.id)",
        cascade="all, delete-orphan",
        passive_deletes=True,
    )
    notes: Mapped[list["EnquiryNote"]] = relationship(
        back_populates="enquiry",
        order_by="(EnquiryNote.created_at, EnquiryNote.id)",
        cascade="all, delete-orphan",
        passive_deletes=True,
    )


class EnquiryStatusHistory(Base):
    __tablename__ = "enquiry_status_history"
    __table_args__ = (
        CheckConstraint("(to_status = 'CLOSED') = (close_reason IS NOT NULL)", name="close_reason"),
    )

    id: Mapped[int] = mapped_column(BigInteger, Identity(always=True), primary_key=True)
    enquiry_id: Mapped[uuid.UUID] = mapped_column(
        ForeignKey("enquiries.id", ondelete="CASCADE"), nullable=False
    )
    from_status: Mapped[EnquiryStatus | None] = mapped_column(enquiry_status)
    to_status: Mapped[EnquiryStatus] = mapped_column(enquiry_status, nullable=False)
    close_reason: Mapped[CloseReason | None] = mapped_column(close_reason)
    changed_by_id: Mapped[int | None] = mapped_column(ForeignKey("users.id", ondelete="RESTRICT"))
    changed_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    enquiry: Mapped[Enquiry] = relationship(back_populates="history")
    changed_by: Mapped[User | None] = relationship()


class EnquiryNote(Base):
    __tablename__ = "enquiry_notes"
    __table_args__ = (CheckConstraint("length(trim(body)) BETWEEN 1 AND 2000", name="body_length"),)

    id: Mapped[int] = mapped_column(BigInteger, Identity(always=True), primary_key=True)
    enquiry_id: Mapped[uuid.UUID] = mapped_column(
        ForeignKey("enquiries.id", ondelete="CASCADE"), nullable=False
    )
    author_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="RESTRICT"), nullable=False
    )
    body: Mapped[str] = mapped_column(Text, nullable=False)
    created_at: Mapped[datetime] = _created()

    enquiry: Mapped[Enquiry] = relationship(back_populates="notes")
    author: Mapped[User] = relationship()
