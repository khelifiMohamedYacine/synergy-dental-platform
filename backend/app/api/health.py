"""Liveness and readiness probes (doc 05 section 5.1)."""

from typing import Annotated

from fastapi import APIRouter, Depends
from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

from app.core.db import get_db
from app.core.errors import ApiError

router = APIRouter(tags=["health"])


@router.get("/health")
def liveness() -> dict:
    return {"status": "ok"}


@router.get("/health/ready")
def readiness(db: Annotated[Session, Depends(get_db)]) -> dict:
    try:
        db.execute(text("SELECT 1"))
    except SQLAlchemyError as exc:
        raise ApiError(503, "DATABASE_UNAVAILABLE", "The database is not reachable.") from exc
    return {"status": "ok"}
