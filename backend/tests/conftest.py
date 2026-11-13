"""Test fixtures. Tests run against a real PostgreSQL database built by the Alembic migrations.

The test database is `<DATABASE_URL database>_test` unless TEST_DATABASE_URL is set. It is
created if missing, migrated once per session, and truncated before every test.
"""

import os

from sqlalchemy.engine import make_url

_base = make_url(
    os.environ.get("DATABASE_URL", "postgresql+psycopg://synergy:synergy@localhost/synergy")
)
TEST_URL = os.environ.get("TEST_DATABASE_URL") or _base.set(
    database=f"{_base.database}_test"
).render_as_string(hide_password=False)
os.environ["DATABASE_URL"] = TEST_URL
os.environ["SERVICE_API_KEY"] = "test-service-key"
os.environ["JWT_SECRET"] = "test-jwt-secret-that-is-long-enough-for-hs256-0123456789"
os.environ["API_ENV"] = "test"

import pytest  # noqa: E402
from alembic.config import Config  # noqa: E402
from fastapi.testclient import TestClient  # noqa: E402
from sqlalchemy import create_engine, text  # noqa: E402
from sqlalchemy.orm import Session  # noqa: E402

from alembic import command  # noqa: E402
from app.core import ratelimit  # noqa: E402
from app.core.config import get_settings  # noqa: E402
from app.core.db import get_engine  # noqa: E402
from app.core.security import hash_password  # noqa: E402
from app.models import Role, User  # noqa: E402

get_settings.cache_clear()
get_engine.cache_clear()

SERVICE_HEADERS = {"X-Service-Key": "test-service-key"}
PASSWORD = "correct-horse-battery-staple"
TABLES = (
    "enquiry_notes, enquiry_status_history, enquiries, clinic_treatments, treatments, "
    "treatment_categories, clinics, refresh_tokens, users"
)


def alembic_config(url: str) -> Config:
    cfg = Config(os.path.join(os.path.dirname(__file__), "..", "alembic.ini"))
    cfg.attributes["database_url"] = url
    return cfg


def _ensure_database(url: str) -> None:
    target = make_url(url)
    admin = create_engine(target.set(database="postgres"), isolation_level="AUTOCOMMIT")
    with admin.connect() as conn:
        exists = conn.scalar(
            text("SELECT 1 FROM pg_database WHERE datname = :n"), {"n": target.database}
        )
        if not exists:
            conn.execute(text(f'CREATE DATABASE "{target.database}"'))
    admin.dispose()


@pytest.fixture(scope="session", autouse=True)
def _database():
    _ensure_database(TEST_URL)
    cfg = alembic_config(TEST_URL)
    command.downgrade(cfg, "base")
    command.upgrade(cfg, "head")
    yield
    get_engine().dispose()


@pytest.fixture(autouse=True)
def _clean(_database):
    with get_engine().begin() as conn:
        conn.execute(text(f"TRUNCATE {TABLES} RESTART IDENTITY CASCADE"))
    ratelimit.reset()
    from app.api import shop

    shop._cache.update(at=0.0, value=None)
    yield


@pytest.fixture
def db():
    with Session(get_engine()) as session:
        yield session


@pytest.fixture
def client():
    # https base URL so the Secure refresh cookie is sent back by the test client.
    with TestClient(
        __import__("app.main", fromlist=["app"]).app, base_url="https://testserver"
    ) as c:
        yield c


@pytest.fixture
def make_user(db):
    def _make(email="staff@example.test", role=Role.STAFF, active=True, name="Test Staff"):
        user = User(
            email=email,
            full_name=name,
            role=role,
            is_active=active,
            password_hash=hash_password(PASSWORD),
        )
        db.add(user)
        db.commit()
        db.refresh(user)
        return user

    return _make


def login(client, email: str, password: str = PASSWORD):
    return client.post("/api/v1/auth/login", data={"username": email, "password": password})


@pytest.fixture
def staff_headers(client, make_user):
    make_user("staff@example.test", Role.STAFF)
    token = login(client, "staff@example.test").json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


@pytest.fixture
def admin_headers(client, make_user):
    make_user("admin@example.test", Role.ADMIN, name="Test Admin")
    token = login(client, "admin@example.test").json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


@pytest.fixture
def reference_data(db):
    from app.seed import seed_reference

    seed_reference(db)


def enquiry_payload(**overrides) -> dict:
    from datetime import timedelta

    from app.schemas import london_today

    data = {
        "enquiry_type": "APPOINTMENT",
        "full_name": "Jamie Fictional",
        "email": "Jamie@Example.test",
        "phone": "07700 900123",
        "clinic_slug": "riverside",
        "treatment_slug": "dental-implants",
        "preferred_date": (london_today() + timedelta(days=7)).isoformat(),
        "preferred_time_window": "MORNING",
        "message": "Interested in a consultation.",
        "consent": True,
    }
    data.update(overrides)
    return data


@pytest.fixture
def create_enquiry(client, reference_data):
    def _create(headers=None, **overrides):
        response = client.post(
            "/api/v1/enquiries",
            json=enquiry_payload(**overrides),
            headers={**SERVICE_HEADERS, **(headers or {})},
        )
        assert response.status_code in (200, 201), response.text
        return response.json()

    return _create
