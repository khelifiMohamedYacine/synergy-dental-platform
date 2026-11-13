"""User management (doc 05 §5.6). ADMIN only."""

import math
from typing import Annotated

from fastapi import APIRouter, Query
from sqlalchemy import func, select
from sqlalchemy.exc import IntegrityError

from app.core.deps import AdminUser, DbSession
from app.core.errors import ApiError, not_found
from app.core.security import hash_password
from app.models import RefreshToken, Role, User
from app.schemas import Page, UserAdminOut, UserCreate, UserUpdate

router = APIRouter(prefix="/users", tags=["users"])


@router.get("", response_model=Page[UserAdminOut])
def list_users(
    db: DbSession,
    _admin: AdminUser,
    page: Annotated[int, Query(ge=1)] = 1,
    page_size: Annotated[int, Query(ge=1, le=100)] = 20,
) -> Page[UserAdminOut]:
    total = db.scalar(select(func.count(User.id))) or 0
    users = db.scalars(
        select(User)
        .order_by(User.full_name, User.id)
        .offset((page - 1) * page_size)
        .limit(page_size)
    )
    return Page[UserAdminOut](
        items=[UserAdminOut.model_validate(u) for u in users],
        page=page,
        page_size=page_size,
        total=total,
        pages=max(1, math.ceil(total / page_size)),
    )


@router.post("", response_model=UserAdminOut, status_code=201)
def create_user(body: UserCreate, db: DbSession, _admin: AdminUser) -> User:
    user = User(
        email=body.email,
        full_name=body.full_name,
        role=body.role,
        password_hash=hash_password(body.password),
    )
    db.add(user)
    try:
        db.commit()
    except IntegrityError as exc:
        db.rollback()
        raise ApiError(409, "CONFLICT", "A user with this email already exists.") from exc
    db.refresh(user)
    return user


@router.patch("/{user_id}", response_model=UserAdminOut)
def update_user(user_id: int, body: UserUpdate, db: DbSession, admin: AdminUser) -> User:
    user = db.get(User, user_id)
    if user is None:
        raise not_found("User")
    changes = body.model_dump(exclude_unset=True)
    if user.id == admin.id and (
        changes.get("is_active") is False or changes.get("role", Role.ADMIN) != Role.ADMIN
    ):
        # Guarantees at least one active admin always remains.
        raise ApiError(409, "CONFLICT", "You cannot deactivate or demote your own account.")
    password = changes.pop("password", None)
    for field, value in changes.items():
        setattr(user, field, value)
    if password:
        user.password_hash = hash_password(password)
    if password or changes.get("is_active") is False:
        # End existing sessions when the password changes or the account is disabled.
        db.query(RefreshToken).filter(
            RefreshToken.user_id == user.id, RefreshToken.revoked_at.is_(None)
        ).update({RefreshToken.revoked_at: func.now()})
    db.commit()
    db.refresh(user)
    return user
