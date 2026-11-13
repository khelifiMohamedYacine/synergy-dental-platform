"""FastAPI application entry point."""

from fastapi import FastAPI

from app.api import health
from app.core import errors

app = FastAPI(
    title="Synergy Dental Business API (demo)",
    version="0.1.0",
    docs_url="/api/v1/docs",
    openapi_url="/api/v1/openapi.json",
)
errors.install(app)
app.include_router(health.router, prefix="/api/v1")
