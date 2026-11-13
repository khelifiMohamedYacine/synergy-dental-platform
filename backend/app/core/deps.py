"""Shared FastAPI dependencies: database session, current user, role checks, service key."""

from collections.abc import Callable
from typing import Annotated

import jwt
from fastapi import Depends, Header
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlalchemy.orm import Session

from app.core.db import get_db
from app.core.errors import ApiError
from app.core.ratelimit import AUTHENTICATED_LIMIT, enforce
from app.core.security import decode_access_token, service_key_valid
from app.models import Role, User

DbSession = Annotated[Session, Depends(get_db)]

_bearer = HTTPBearer(auto_error=False)


def _unauthenticated(code: str = "NOT_AUTHENTICATED", message: str = "Please sign in.") -> ApiError:
    return ApiError(401, code, message, headers={"WWW-Authenticate": "Bearer"})


def get_current_user(
    db: DbSession,
    credentials: Annotated[HTTPAuthorizationCredentials | None, Depends(_bearer)],
) -> User:
    if credentials is None:
        raise _unauthenticated()
    try:
        payload = decode_access_token(credentials.credentials)
    except jwt.ExpiredSignatureError as exc:
        raise _unauthenticated("TOKEN_EXPIRED", "Your session has expired.") from exc
    except jwt.InvalidTokenError as exc:
        raise _unauthenticated() from exc
    user = db.get(User, int(payload["sub"]))
    if user is None:
        raise _unauthenticated()
    if not user.is_active:
        raise ApiError(403, "ACCOUNT_DISABLED", "This account has been disabled.")
    return user


def get_optional_user(
    db: DbSession,
    credentials: Annotated[HTTPAuthorizationCredentials | None, Depends(_bearer)],
) -> User | None:
    if credentials is None:
        return None
    return get_current_user(db, credentials)


def require_role(role: Role) -> Callable[..., User]:
    """STAFF means staff or admin; ADMIN means admin only (doc 05 §2)."""

    def checker(user: Annotated[User, Depends(get_current_user)]) -> User:
        if role == Role.ADMIN and user.role != Role.ADMIN:
            raise ApiError(403, "FORBIDDEN", "You do not have permission to do this.")
        enforce(AUTHENTICATED_LIMIT, "user", str(user.id))
        return user

    checker.required_role = role  # type: ignore[attr-defined]  # read by the route-audit test
    return checker


def require_service_key(x_service_key: Annotated[str | None, Header()] = None) -> None:
    if not service_key_valid(x_service_key):
        raise ApiError(401, "NOT_AUTHENTICATED", "A valid service key is required.")


StaffUser = Annotated[User, Depends(require_role(Role.STAFF))]
AdminUser = Annotated[User, Depends(require_role(Role.ADMIN))]
OptionalUser = Annotated[User | None, Depends(get_optional_user)]
