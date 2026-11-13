"""Error envelope (doc 05 §3.1), request-ID middleware and exception handlers."""

import logging
import uuid

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException as StarletteHTTPException

logger = logging.getLogger("synergy.api")


class ApiError(Exception):
    def __init__(
        self,
        status_code: int,
        code: str,
        message: str,
        details: list | None = None,
        headers: dict | None = None,
    ):
        self.status_code = status_code
        self.code = code
        self.message = message
        self.details = details or []
        self.headers = headers


def not_found(what: str = "Resource") -> ApiError:
    return ApiError(404, "NOT_FOUND", f"{what} not found.")


def _request_id(request: Request) -> str:
    return getattr(request.state, "request_id", None) or str(uuid.uuid4())


def error_response(
    request: Request,
    status_code: int,
    code: str,
    message: str,
    details: list | None = None,
    headers: dict | None = None,
) -> JSONResponse:
    rid = _request_id(request)
    body = {
        "error": {"code": code, "message": message, "details": details or []},
        "request_id": rid,
    }
    response = JSONResponse(status_code=status_code, content=body, headers=headers)
    response.headers["X-Request-ID"] = rid
    return response


_HTTP_CODES = {
    400: "BAD_REQUEST",
    401: "NOT_AUTHENTICATED",
    403: "FORBIDDEN",
    404: "NOT_FOUND",
    405: "BAD_REQUEST",
    409: "CONFLICT",
}


def _field_name(loc: tuple) -> str:
    parts = [str(p) for p in loc if p not in ("body", "query", "path", "header")]
    return ".".join(parts) or "request"


def install(app: FastAPI) -> None:
    @app.middleware("http")
    async def request_id(request: Request, call_next):
        request.state.request_id = str(uuid.uuid4())
        response = await call_next(request)
        response.headers["X-Request-ID"] = request.state.request_id
        return response

    @app.exception_handler(ApiError)
    async def api_error(request: Request, exc: ApiError):
        return error_response(
            request, exc.status_code, exc.code, exc.message, exc.details, exc.headers
        )

    @app.exception_handler(RequestValidationError)
    async def validation_error(request: Request, exc: RequestValidationError):
        details = []
        for err in exc.errors():
            msg = str(err.get("msg", "Invalid value."))
            msg = msg.removeprefix("Value error, ")
            field = _field_name(tuple(err.get("loc", ())))
            # Cross-field validators report "field: message" so the client can place the error.
            if field == "request" and ": " in msg:
                field, msg = msg.split(": ", 1)
            details.append({"field": field, "message": msg})
        return error_response(request, 422, "VALIDATION_ERROR", "Some fields are invalid.", details)

    @app.exception_handler(StarletteHTTPException)
    async def http_error(request: Request, exc: StarletteHTTPException):
        code = _HTTP_CODES.get(exc.status_code, "BAD_REQUEST")
        message = exc.detail if isinstance(exc.detail, str) else "Request failed."
        return error_response(request, exc.status_code, code, message, headers=exc.headers)

    @app.exception_handler(Exception)
    async def unhandled(request: Request, exc: Exception):
        logger.exception("Unhandled error (request_id=%s)", _request_id(request))
        return error_response(request, 500, "INTERNAL_ERROR", "Something went wrong.")
