"""Login and rotating refresh tokens (ADR-05, doc 04 §4.2)."""

from datetime import UTC, datetime, timedelta

from sqlalchemy import func, select, update
from sqlalchemy.orm import Session

from app.core.config import get_settings
from app.core.errors import ApiError
from app.core.security import (
    create_access_token,
    hash_refresh_token,
    new_refresh_token,
    verify_password,
)
from app.models import RefreshToken, User


def _invalid_credentials() -> ApiError:
    return ApiError(401, "INVALID_CREDENTIALS", "Email or password is incorrect.")


def authenticate(db: Session, email: str, password: str) -> User:
    user = db.scalar(select(User).where(User.email == email.strip().lower()))
    # verify_password runs a dummy hash when user is None, so timing does not reveal emails.
    if not verify_password(password, user.password_hash if user else None) or user is None:
        raise _invalid_credentials()
    if not user.is_active:
        raise ApiError(403, "ACCOUNT_DISABLED", "This account has been disabled.")
    user.last_login_at = func.now()
    return user


def issue_tokens(db: Session, user: User) -> tuple[str, int, str, RefreshToken]:
    """Creates an access token and a new refresh token row. Caller commits."""
    access, ttl = create_access_token(user.id, user.role.value)
    raw, digest = new_refresh_token()
    row = RefreshToken(
        user_id=user.id,
        token_hash=digest,
        expires_at=datetime.now(UTC) + timedelta(days=get_settings().jwt_refresh_ttl_days),
    )
    db.add(row)
    db.flush()
    return access, ttl, raw, row


def _revoke_all(db: Session, user_id: int) -> None:
    db.execute(
        update(RefreshToken)
        .where(RefreshToken.user_id == user_id, RefreshToken.revoked_at.is_(None))
        .values(revoked_at=func.now())
    )


def rotate(db: Session, raw: str | None) -> tuple[User, str, int, str]:
    if not raw:
        raise ApiError(401, "NOT_AUTHENTICATED", "Please sign in.")
    token = db.scalar(
        select(RefreshToken)
        .where(RefreshToken.token_hash == hash_refresh_token(raw))
        .with_for_update()
    )
    if token is None:
        raise ApiError(401, "NOT_AUTHENTICATED", "Please sign in.")
    if token.revoked_at is not None:
        # A revoked token was presented again: assume theft and end every session for the user.
        _revoke_all(db, token.user_id)
        db.commit()
        raise ApiError(401, "NOT_AUTHENTICATED", "Please sign in again.")
    if token.expires_at <= datetime.now(UTC):
        raise ApiError(401, "TOKEN_EXPIRED", "Your session has expired.")
    user = db.get(User, token.user_id)
    if user is None or not user.is_active:
        _revoke_all(db, token.user_id)
        db.commit()
        raise ApiError(401, "NOT_AUTHENTICATED", "Please sign in.")
    access, ttl, new_raw, new_row = issue_tokens(db, user)
    token.revoked_at = func.now()
    token.replaced_by_id = new_row.id
    db.commit()
    return user, access, ttl, new_raw


def revoke(db: Session, raw: str | None) -> None:
    if not raw:
        return
    db.execute(
        update(RefreshToken)
        .where(
            RefreshToken.token_hash == hash_refresh_token(raw),
            RefreshToken.revoked_at.is_(None),
        )
        .values(revoked_at=func.now())
    )
    db.commit()
