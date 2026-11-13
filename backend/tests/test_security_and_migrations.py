"""Cross-cutting checks: every route is protected unless allow-listed; migrations reverse cleanly;
the seed script produces consistent data."""

from fastapi.routing import APIRoute
from sqlalchemy import func, select, text

from alembic import command
from app.core.deps import get_current_user, get_optional_user, require_service_key
from app.main import app
from app.models import Enquiry, EnquiryStatusHistory
from tests.conftest import TEST_URL, alembic_config

PUBLIC = {
    ("GET", "/api/v1/health"),
    ("GET", "/api/v1/health/ready"),
    ("POST", "/api/v1/auth/login"),
    ("POST", "/api/v1/auth/refresh"),  # refresh cookie
    ("POST", "/api/v1/auth/logout"),  # refresh cookie
    ("GET", "/api/v1/treatment-categories"),
}


def _dependency_calls(dependant) -> set:
    calls = {dependant.call}
    for sub in dependant.dependencies:
        calls |= _dependency_calls(sub)
    return calls


def test_every_route_declares_auth_unless_public():
    unprotected = []
    for route in app.routes:
        if not isinstance(route, APIRoute) or not route.path.startswith("/api/v1"):
            continue
        calls = _dependency_calls(route.dependant)
        protected = (
            get_current_user in calls
            or require_service_key in calls
            or get_optional_user in calls  # public read; inactive data needs ADMIN
        )
        for method in route.methods:
            if not protected and (method, route.path) not in PUBLIC:
                unprotected.append(f"{method} {route.path}")
    assert unprotected == []


def test_security_headers(client):
    r = client.get("/api/v1/health")
    assert r.headers["X-Content-Type-Options"] == "nosniff"
    assert r.headers["Cache-Control"] == "no-store"


def test_migrations_downgrade_and_upgrade_cleanly(db):
    db.close()
    cfg = alembic_config(TEST_URL)
    command.downgrade(cfg, "base")
    command.upgrade(cfg, "head")


def test_seed_is_consistent_and_respects_constraints(db, monkeypatch):
    from app import seed

    monkeypatch.setenv("SEED_STAFF_PASSWORD", "demo-staff-password-123")
    seed.seed_reference(db)
    seed.seed_users(db, "demo-staff-password-123")
    seed.seed_enquiries(db, 400)
    total = db.scalar(select(func.count(Enquiry.id)))
    assert total == 400
    # Every enquiry's current status equals its latest history row.
    mismatches = db.scalar(
        text("""
        SELECT count(*) FROM enquiries e
        JOIN LATERAL (SELECT to_status FROM enquiry_status_history h
                      WHERE h.enquiry_id = e.id ORDER BY changed_at DESC, id DESC LIMIT 1) last
          ON true
        WHERE last.to_status <> e.status
        """)
    )
    assert mismatches == 0
    assert db.scalar(select(func.count(EnquiryStatusHistory.id))) >= total
    seed.seed_enquiries(db, 400)  # idempotent
    assert db.scalar(select(func.count(Enquiry.id))) == 400
