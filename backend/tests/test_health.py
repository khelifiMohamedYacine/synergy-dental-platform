from fastapi.testclient import TestClient
from sqlalchemy.exc import OperationalError

from app.core.db import get_db
from app.main import app

client = TestClient(app)


def test_liveness_returns_ok_and_request_id():
    response = client.get("/api/v1/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
    assert response.headers["X-Request-ID"]


def test_readiness_returns_503_envelope_when_database_down():
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
