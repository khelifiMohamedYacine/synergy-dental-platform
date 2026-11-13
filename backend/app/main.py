"""FastAPI application entry point."""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api import analytics, auth, enquiries, health, reference, shop, users
from app.core import errors
from app.core.config import get_settings

API_PREFIX = "/api/v1"

app = FastAPI(
    title="Synergy Dental Business API (demo)",
    version="0.2.0",
    description="Fictional demonstration data only. Not affiliated with Synergy Dental Group.",
    docs_url=f"{API_PREFIX}/docs",
    redoc_url=None,
    openapi_url=f"{API_PREFIX}/openapi.json",
)
errors.install(app)

origins = get_settings().cors_origins
if origins:
    # Explicit allow-list only (ADR-05). In production the dashboard is same-origin.
    app.add_middleware(
        CORSMiddleware,
        allow_origins=origins,
        allow_credentials=True,
        allow_methods=["GET", "POST", "PATCH", "DELETE"],
        allow_headers=["Authorization", "Content-Type", "X-Requested-With"],
    )


@app.middleware("http")
async def security_headers(request, call_next):
    response = await call_next(request)
    response.headers.setdefault("X-Content-Type-Options", "nosniff")
    response.headers.setdefault("Referrer-Policy", "strict-origin-when-cross-origin")
    if "Cache-Control" not in response.headers:
        response.headers["Cache-Control"] = "no-store"
    return response


for module in (health, auth, enquiries, reference, analytics, users, shop):
    app.include_router(module.router, prefix=API_PREFIX)
