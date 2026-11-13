"""Password hashing (Argon2id), JWT access tokens and opaque refresh tokens (ADR-05)."""

import hashlib
import hmac
import secrets
import uuid
from datetime import UTC, datetime, timedelta

import jwt
from pwdlib import PasswordHash
from pwdlib.hashers.argon2 import Argon2Hasher

from app.core.config import get_settings

_hasher = PasswordHash((Argon2Hasher(),))
# Used when the email is unknown, so login takes the same time either way (doc 05 §5.2).
_DUMMY_HASH = _hasher.hash("not-a-real-password-just-for-timing")

JWT_ALGORITHM = "HS256"


def hash_password(password: str) -> str:
    return _hasher.hash(password)


def verify_password(password: str, password_hash: str | None) -> bool:
    if password_hash is None:
        _hasher.verify(password, _DUMMY_HASH)
        return False
    return _hasher.verify(password, password_hash)


def create_access_token(user_id: int, role: str) -> tuple[str, int]:
    settings = get_settings()
    ttl = settings.jwt_access_ttl_minutes * 60
    now = datetime.now(UTC)
    payload = {
        "sub": str(user_id),
        "role": role,
        "iat": now,
        "exp": now + timedelta(seconds=ttl),
        "jti": str(uuid.uuid4()),
    }
    return jwt.encode(payload, settings.jwt_secret, algorithm=JWT_ALGORITHM), ttl


def decode_access_token(token: str) -> dict:
    """Raises jwt.ExpiredSignatureError or jwt.InvalidTokenError."""
    return jwt.decode(
        token,
        get_settings().jwt_secret,
        algorithms=[JWT_ALGORITHM],
        options={"require": ["sub", "exp", "iat", "role"]},
    )


def new_refresh_token() -> tuple[str, str]:
    """Returns (raw token for the cookie, SHA-256 hex digest for the database)."""
    raw = secrets.token_urlsafe(48)
    return raw, hash_refresh_token(raw)


def hash_refresh_token(raw: str) -> str:
    return hashlib.sha256(raw.encode()).hexdigest()


def service_key_valid(presented: str | None) -> bool:
    if not presented:
        return False
    return hmac.compare_digest(presented.encode(), get_settings().service_api_key.encode())
