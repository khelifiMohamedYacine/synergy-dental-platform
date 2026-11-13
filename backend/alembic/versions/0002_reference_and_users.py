"""Users, refresh tokens, clinics, treatment categories, treatments (doc 04 §4.1-4.6).

Revision ID: 0002
Revises: 0001
"""

from alembic import op

revision = "0002"
down_revision = "0001"
branch_labels = None
depends_on = None

SLUG = "CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')"


def upgrade() -> None:
    op.execute("""
        CREATE TABLE users (
            id integer GENERATED ALWAYS AS IDENTITY,
            email varchar(254) NOT NULL,
            full_name varchar(120) NOT NULL,
            password_hash varchar(255) NOT NULL,
            role user_role NOT NULL DEFAULT 'STAFF',
            is_active boolean NOT NULL DEFAULT true,
            last_login_at timestamptz,
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT pk_users PRIMARY KEY (id),
            CONSTRAINT uq_users_email UNIQUE (email),
            CONSTRAINT ck_users_email_lower CHECK (email = lower(email))
        )
    """)
    op.execute("""
        CREATE TABLE refresh_tokens (
            id uuid NOT NULL DEFAULT gen_random_uuid(),
            user_id integer NOT NULL,
            token_hash varchar(64) NOT NULL,
            expires_at timestamptz NOT NULL,
            revoked_at timestamptz,
            replaced_by_id uuid,
            created_at timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT pk_refresh_tokens PRIMARY KEY (id),
            CONSTRAINT uq_refresh_tokens_token_hash UNIQUE (token_hash),
            CONSTRAINT fk_refresh_tokens_user_id_users
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_refresh_tokens_replaced_by_id_refresh_tokens
                FOREIGN KEY (replaced_by_id) REFERENCES refresh_tokens (id) ON DELETE SET NULL
        )
    """)
    op.execute("CREATE INDEX ix_refresh_tokens_user_id ON refresh_tokens (user_id)")
    op.execute(f"""
        CREATE TABLE clinics (
            id integer GENERATED ALWAYS AS IDENTITY,
            slug varchar(80) NOT NULL,
            name varchar(120) NOT NULL,
            address_line1 varchar(120) NOT NULL,
            address_line2 varchar(120),
            city varchar(80) NOT NULL,
            postcode varchar(10) NOT NULL,
            phone varchar(32) NOT NULL,
            email varchar(254),
            opening_hours jsonb NOT NULL DEFAULT '{{}}'::jsonb,
            is_active boolean NOT NULL DEFAULT true,
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT pk_clinics PRIMARY KEY (id),
            CONSTRAINT uq_clinics_slug UNIQUE (slug),
            CONSTRAINT ck_clinics_slug_format {SLUG}
        )
    """)
    op.execute(f"""
        CREATE TABLE treatment_categories (
            id integer GENERATED ALWAYS AS IDENTITY,
            slug varchar(80) NOT NULL,
            name varchar(80) NOT NULL,
            display_order smallint NOT NULL DEFAULT 0,
            CONSTRAINT pk_treatment_categories PRIMARY KEY (id),
            CONSTRAINT uq_treatment_categories_slug UNIQUE (slug),
            CONSTRAINT uq_treatment_categories_name UNIQUE (name),
            CONSTRAINT ck_treatment_categories_slug_format {SLUG}
        )
    """)
    op.execute(f"""
        CREATE TABLE treatments (
            id integer GENERATED ALWAYS AS IDENTITY,
            category_id integer NOT NULL,
            slug varchar(80) NOT NULL,
            name varchar(120) NOT NULL,
            summary varchar(300),
            price_from_pence integer,
            typical_duration_minutes smallint,
            is_active boolean NOT NULL DEFAULT true,
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT pk_treatments PRIMARY KEY (id),
            CONSTRAINT uq_treatments_slug UNIQUE (slug),
            CONSTRAINT ck_treatments_slug_format {SLUG},
            CONSTRAINT ck_treatments_price_non_negative CHECK (price_from_pence >= 0),
            CONSTRAINT ck_treatments_duration_positive CHECK (typical_duration_minutes > 0),
            CONSTRAINT fk_treatments_category_id_treatment_categories
                FOREIGN KEY (category_id) REFERENCES treatment_categories (id) ON DELETE RESTRICT
        )
    """)
    op.execute("CREATE INDEX ix_treatments_category_id ON treatments (category_id)")
    op.execute("""
        CREATE TABLE clinic_treatments (
            clinic_id integer NOT NULL,
            treatment_id integer NOT NULL,
            CONSTRAINT pk_clinic_treatments PRIMARY KEY (clinic_id, treatment_id),
            CONSTRAINT fk_clinic_treatments_clinic_id_clinics
                FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE CASCADE,
            CONSTRAINT fk_clinic_treatments_treatment_id_treatments
                FOREIGN KEY (treatment_id) REFERENCES treatments (id) ON DELETE CASCADE
        )
    """)
    op.execute("CREATE INDEX ix_clinic_treatments_treatment_id ON clinic_treatments (treatment_id)")


def downgrade() -> None:
    for table in (
        "clinic_treatments",
        "treatments",
        "treatment_categories",
        "clinics",
        "refresh_tokens",
        "users",
    ):
        op.execute(f"DROP TABLE {table}")
