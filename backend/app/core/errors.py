"""Error envelope (doc 05 section 3.1) and request-ID middleware."""

import uuid

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse


class ApiError(Exception):
    def __init__(self, status_code: int, code: str, message: str, details: list | None = None):
        self.status_code = status_code
        self.code = code
        self.message = message
        self.details = details or []


def _envelope(request: Request, code: str, message: str, details: list) -> dict:
    return {
        "error": {"code": code, "message": message, "details": details},
        "request_id": request.state.request_id,
    }


def install(app: FastAPI) -> None:
    @app.middleware("http")
    async def request_id(request: Request, call_next):
        request.state.request_id = str(uuid.uuid4())
        response = await call_next(request)
        response.headers["X-Request-ID"] = request.state.request_id
        return response

    @app.exception_handler(ApiError)
    async def api_error(request: Request, exc: ApiError):
        return JSONResponse(
            status_code=exc.status_code,
            content=_envelope(request, exc.code, exc.message, exc.details),
        )
