"""Enquiries, status history, notes and the indexes in doc 04 §7.

Revision ID: 0003
Revises: 0002
"""

from alembic import op

revision = "0003"
down_revision = "0002"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.execute("""
        CREATE TABLE enquiries (
            id uuid NOT NULL DEFAULT gen_random_uuid(),
            reference varchar(9) NOT NULL,
            enquiry_type enquiry_type NOT NULL,
            status enquiry_status NOT NULL DEFAULT 'NEW',
            close_reason close_reason,
            full_name varchar(120) NOT NULL,
            email varchar(254) NOT NULL,
            phone varchar(32) NOT NULL,
            clinic_id integer NOT NULL,
            treatment_id integer,
            preferred_date date,
            preferred_time_window time_window,
            message text,
            consent boolean NOT NULL,
            consent_at timestamptz NOT NULL DEFAULT now(),
            idempotency_key uuid,
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT pk_enquiries PRIMARY KEY (id),
            CONSTRAINT uq_enquiries_reference UNIQUE (reference),
            CONSTRAINT uq_enquiries_idempotency_key UNIQUE (idempotency_key),
            CONSTRAINT ck_enquiries_reference_format
                CHECK (reference ~ '^SD-[2-9A-HJKMNP-Z]{6}$'),
            CONSTRAINT ck_enquiries_full_name_length CHECK (length(trim(full_name)) >= 2),
            CONSTRAINT ck_enquiries_message_length CHECK (length(message) <= 2000),
            CONSTRAINT ck_enquiries_consent_required CHECK (consent),
            -- A closed enquiry must say why; an open one must not have a reason.
            CONSTRAINT ck_enquiries_close_reason
                CHECK ((status = 'CLOSED') = (close_reason IS NOT NULL)),
            -- Appointment requests must have a preferred date.
            CONSTRAINT ck_enquiries_appointment_date
                CHECK (enquiry_type <> 'APPOINTMENT' OR preferred_date IS NOT NULL),
            CONSTRAINT fk_enquiries_clinic_id_clinics
                FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE RESTRICT,
            CONSTRAINT fk_enquiries_treatment_id_treatments
                FOREIGN KEY (treatment_id) REFERENCES treatments (id) ON DELETE RESTRICT
        )
    """)
    op.execute("""
        CREATE TABLE enquiry_status_history (
            id bigint GENERATED ALWAYS AS IDENTITY,
            enquiry_id uuid NOT NULL,
            from_status enquiry_status,
            to_status enquiry_status NOT NULL,
            close_reason close_reason,
            changed_by_id integer,
            changed_at timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT pk_enquiry_status_history PRIMARY KEY (id),
            CONSTRAINT ck_enquiry_status_history_close_reason
                CHECK ((to_status = 'CLOSED') = (close_reason IS NOT NULL)),
            CONSTRAINT fk_enquiry_status_history_enquiry_id_enquiries
                FOREIGN KEY (enquiry_id) REFERENCES enquiries (id) ON DELETE CASCADE,
            CONSTRAINT fk_enquiry_status_history_changed_by_id_users
                FOREIGN KEY (changed_by_id) REFERENCES users (id) ON DELETE RESTRICT
        )
    """)
    op.execute("""
        CREATE TABLE enquiry_notes (
            id bigint GENERATED ALWAYS AS IDENTITY,
            enquiry_id uuid NOT NULL,
            author_id integer NOT NULL,
            body text NOT NULL,
            created_at timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT pk_enquiry_notes PRIMARY KEY (id),
            CONSTRAINT ck_enquiry_notes_body_length
                CHECK (length(trim(body)) BETWEEN 1 AND 2000),
            CONSTRAINT fk_enquiry_notes_enquiry_id_enquiries
                FOREIGN KEY (enquiry_id) REFERENCES enquiries (id) ON DELETE CASCADE,
            CONSTRAINT fk_enquiry_notes_author_id_users
                FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE RESTRICT
        )
    """)
    for stmt in (
        "CREATE INDEX ix_enquiries_created_at ON enquiries (created_at DESC)",
        "CREATE INDEX ix_enquiries_status_created_at ON enquiries (status, created_at DESC)",
        "CREATE INDEX ix_enquiries_clinic_id ON enquiries (clinic_id)",
        "CREATE INDEX ix_enquiries_treatment_id ON enquiries (treatment_id)",
        "CREATE INDEX ix_enquiries_email_lower ON enquiries (lower(email))",
        "CREATE INDEX ix_enquiries_open_dupe ON enquiries"
        " (lower(email), clinic_id, treatment_id, created_at) WHERE status <> 'CLOSED'",
        "CREATE INDEX ix_enquiries_search_trgm ON enquiries"
        " USING gin (full_name gin_trgm_ops, email gin_trgm_ops)",
        "CREATE INDEX ix_status_history_enquiry ON enquiry_status_history (enquiry_id, changed_at)",
        "CREATE INDEX ix_status_history_to_status ON enquiry_status_history"
        " (to_status, enquiry_id)",
        "CREATE INDEX ix_enquiry_notes_enquiry ON enquiry_notes (enquiry_id, created_at)",
    ):
        op.execute(stmt)


def downgrade() -> None:
    op.execute("DROP TABLE enquiry_notes")
    op.execute("DROP TABLE enquiry_status_history")
    op.execute("DROP TABLE enquiries")
