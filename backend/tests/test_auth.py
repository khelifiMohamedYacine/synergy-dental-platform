from datetime import UTC, datetime, timedelta

import jwt

from app.models import Role
from tests.conftest import PASSWORD, login

FETCH = {"X-Requested-With": "fetch"}


def use_cookie(client, value):
    client.cookies.clear()
    client.cookies.set("sd_refresh", value)


def test_login_returns_token_user_and_refresh_cookie(client, make_user):
    make_user("staff@example.test")
    response = login(client, "Staff@Example.test")
    assert response.status_code == 200
    body = response.json()
    assert body["token_type"] == "bearer"
    assert body["expires_in"] == 900
    assert body["user"]["email"] == "staff@example.test"
    assert "password_hash" not in body["user"]
    cookie = response.headers["set-cookie"].lower()
    assert "sd_refresh=" in cookie
    for attr in ("httponly", "secure", "samesite=strict", "path=/api/v1/auth"):
        assert attr in cookie


def test_wrong_password_and_unknown_email_look_identical(client, make_user):
    make_user("staff@example.test")
    wrong = login(client, "staff@example.test", "wrong-password-123")
    unknown = login(client, "nobody@example.test", "wrong-password-123")
    assert wrong.status_code == unknown.status_code == 401
    assert wrong.json()["error"] == unknown.json()["error"]
    assert wrong.json()["error"]["code"] == "INVALID_CREDENTIALS"


def test_disabled_account_cannot_log_in(client, make_user):
    make_user("gone@example.test", active=False)
    response = login(client, "gone@example.test")
    assert response.status_code == 403
    assert response.json()["error"]["code"] == "ACCOUNT_DISABLED"


def test_me_requires_token(client, staff_headers):
    assert client.get("/api/v1/auth/me").status_code == 401
    response = client.get("/api/v1/auth/me", headers=staff_headers)
    assert response.status_code == 200
    assert response.json()["role"] == "STAFF"


def test_expired_and_tampered_tokens_rejected(client, make_user):
    user = make_user()
    secret = "test-jwt-secret-that-is-long-enough-for-hs256-0123456789"
    past = datetime.now(UTC) - timedelta(hours=1)
    expired = jwt.encode(
        {"sub": str(user.id), "role": "STAFF", "iat": past, "exp": past}, secret, "HS256"
    )
    r = client.get("/api/v1/auth/me", headers={"Authorization": f"Bearer {expired}"})
    assert r.json()["error"]["code"] == "TOKEN_EXPIRED"
    forged = jwt.encode(
        {"sub": str(user.id), "role": "ADMIN", "iat": past, "exp": past + timedelta(days=1)},
        "some-other-secret-that-is-also-long-enough-0123456789",
        "HS256",
    )
    r = client.get("/api/v1/auth/me", headers={"Authorization": f"Bearer {forged}"})
    assert r.status_code == 401


def test_refresh_rotates_and_reuse_revokes_everything(client, make_user):
    make_user()
    login(client, "staff@example.test")
    first_cookie = client.cookies.get("sd_refresh")

    rotated = client.post("/api/v1/auth/refresh", headers=FETCH)
    assert rotated.status_code == 200
    assert rotated.json()["access_token"]
    second_cookie = client.cookies.get("sd_refresh")
    assert second_cookie != first_cookie

    # Replaying the old (revoked) token looks like theft: every session is revoked.
    use_cookie(client, first_cookie)
    replay = client.post("/api/v1/auth/refresh", headers=FETCH)
    assert replay.status_code == 401
    use_cookie(client, second_cookie)
    after = client.post("/api/v1/auth/refresh", headers=FETCH)
    assert after.status_code == 401


def test_refresh_requires_fetch_header(client, make_user):
    make_user()
    login(client, "staff@example.test")
    response = client.post("/api/v1/auth/refresh")
    assert response.status_code == 400


def test_logout_revokes_refresh_token(client, make_user):
    make_user()
    login(client, "staff@example.test")
    cookie = client.cookies.get("sd_refresh")
    assert client.post("/api/v1/auth/logout", headers=FETCH).status_code == 204
    use_cookie(client, cookie)
    response = client.post("/api/v1/auth/refresh", headers=FETCH)
    assert response.status_code == 401


def test_login_rate_limited_per_ip_and_email(client, make_user):
    make_user()
    for _ in range(10):
        login(client, "staff@example.test", "wrong-password-123")
    response = login(client, "staff@example.test", PASSWORD)
    assert response.status_code == 429
    assert response.json()["error"]["code"] == "RATE_LIMITED"
    assert int(response.headers["Retry-After"]) >= 1
    # A different account from the same IP is not blocked.
    make_user("other@example.test", Role.STAFF)
    assert login(client, "other@example.test").status_code == 200
