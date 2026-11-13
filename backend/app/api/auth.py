"""Auth endpoints (doc 05 §5.2)."""

from typing import Annotated

from fastapi import APIRouter, Cookie, Depends, Header, Request, Response
from fastapi.security import OAuth2PasswordRequestForm

from app.core.config import get_settings
from app.core.deps import DbSession, StaffUser
from app.core.errors import ApiError
from app.core.ratelimit import client_ip, enforce
from app.schemas import TokenOut, UserOut
from app.services import auth as service

router = APIRouter(prefix="/auth", tags=["auth"])

COOKIE = "sd_refresh"
COOKIE_PATH = "/api/v1/auth"


def _set_cookie(response: Response, raw: str) -> None:
    response.set_cookie(
        COOKIE,
        raw,
        max_age=get_settings().jwt_refresh_ttl_days * 86400,
        path=COOKIE_PATH,
        httponly=True,
        secure=True,
        samesite="strict",
    )


def _require_fetch_header(x_requested_with: str | None) -> None:
    # Second CSRF guard on cookie-authenticated endpoints (doc 05 §2).
    if x_requested_with != "fetch":
        raise ApiError(400, "BAD_REQUEST", "Missing X-Requested-With: fetch header.")


@router.post("/login", response_model=TokenOut)
def login(
    request: Request,
    response: Response,
    db: DbSession,
    form: Annotated[OAuth2PasswordRequestForm, Depends()],
) -> TokenOut:
    enforce(
        get_settings().login_rate_limit, "login", f"{client_ip(request)}:{form.username.lower()}"
    )
    user = service.authenticate(db, form.username, form.password)
    access, ttl, raw, _ = service.issue_tokens(db, user)
    db.commit()
    _set_cookie(response, raw)
    return TokenOut(access_token=access, expires_in=ttl, user=UserOut.model_validate(user))


@router.post("/refresh", response_model=TokenOut)
def refresh(
    response: Response,
    db: DbSession,
    sd_refresh: Annotated[str | None, Cookie()] = None,
    x_requested_with: Annotated[str | None, Header()] = None,
) -> TokenOut:
    _require_fetch_header(x_requested_with)
    user, access, ttl, raw = service.rotate(db, sd_refresh)
    _set_cookie(response, raw)
    return TokenOut(access_token=access, expires_in=ttl, user=UserOut.model_validate(user))


@router.post("/logout", status_code=204)
def logout(
    db: DbSession,
    sd_refresh: Annotated[str | None, Cookie()] = None,
    x_requested_with: Annotated[str | None, Header()] = None,
) -> Response:
    _require_fetch_header(x_requested_with)
    service.revoke(db, sd_refresh)
    response = Response(status_code=204)
    response.delete_cookie(COOKIE, path=COOKIE_PATH, secure=True, httponly=True, samesite="strict")
    return response


@router.get("/me", response_model=UserOut)
def me(user: StaffUser) -> UserOut:
    return UserOut.model_validate(user)
