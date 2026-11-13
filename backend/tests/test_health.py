from sqlalchemy.exc import OperationalError

from app.core.db import get_db
from app.main import app


def test_liveness_returns_ok_and_request_id(client):
    response = client.get("/api/v1/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
    assert response.headers["X-Request-ID"]


def test_readiness_ok_against_real_database(client):
    response = client.get("/api/v1/health/ready")
    assert response.status_code == 200


def test_readiness_returns_503_envelope_when_database_down(client):
    class BrokenSession:
        def execute(self, *_):
            raise OperationalError("SELECT 1", {}, Exception("connection refused"))

    app.dependency_overrides[get_db] = lambda: BrokenSession()
    try:
        response = client.get("/api/v1/health/ready")
    finally:
        app.dependency_overrides.clear()

    assert response.status_code == 503
    body = response.json()
    assert body["error"]["code"] == "DATABASE_UNAVAILABLE"
    assert body["request_id"] == response.headers["X-Request-ID"]


def test_unknown_route_uses_error_envelope(client):
    response = client.get("/api/v1/nope")
    assert response.status_code == 404
    assert response.json()["error"]["code"] == "NOT_FOUND"
