"""Extensions and enum types (doc 04 §3, §9).

Revision ID: 0001
Revises:
"""

from alembic import op

revision = "0001"
down_revision = None
branch_labels = None
depends_on = None

ENUMS = {
    "user_role": ("ADMIN", "STAFF"),
    "enquiry_type": ("GENERAL", "APPOINTMENT"),
    "enquiry_status": ("NEW", "CONTACTED", "QUALIFIED", "BOOKED", "CLOSED"),
    "close_reason": ("TREATED", "NOT_INTERESTED", "UNREACHABLE", "DUPLICATE", "OTHER"),
    "time_window": ("MORNING", "AFTERNOON", "EVENING"),
}


def upgrade() -> None:
    op.execute("CREATE EXTENSION IF NOT EXISTS pgcrypto")
    op.execute("CREATE EXTENSION IF NOT EXISTS pg_trgm")
    for name, values in ENUMS.items():
        labels = ", ".join(f"'{v}'" for v in values)
        op.execute(f"CREATE TYPE {name} AS ENUM ({labels})")


def downgrade() -> None:
    for name in reversed(ENUMS):
        op.execute(f"DROP TYPE IF EXISTS {name}")
    # Extensions are left installed: other databases objects may rely on them.
