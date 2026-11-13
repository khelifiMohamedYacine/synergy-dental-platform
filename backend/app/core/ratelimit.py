"""Rate limiting (doc 05 §4) using the `limits` library (the engine behind SlowAPI).

`limits` is called directly rather than through SlowAPI decorators because two of the keys need
request data that SlowAPI's synchronous key functions cannot see: the submitted email on login,
and the authenticated user id. Storage is in-memory, which is correct for one API process; with
several replicas it would move to Redis.
"""

import time

from fastapi import Request
from limits import parse_many
from limits.storage import MemoryStorage
from limits.strategies import MovingWindowRateLimiter

from app.core.config import get_settings
from app.core.errors import ApiError
from app.core.security import service_key_valid

_storage = MemoryStorage()
_limiter = MovingWindowRateLimiter(_storage)

AUTHENTICATED_LIMIT = "120/minute"


def client_ip(request: Request) -> str:
    """X-Client-IP is trusted only when the caller is the WordPress plugin (valid service key)."""
    forwarded = request.headers.get("x-client-ip")
    if forwarded and service_key_valid(request.headers.get("x-service-key")):
        return forwarded.strip()
    return request.client.host if request.client else "unknown"


def enforce(limit: str, scope: str, key: str) -> None:
    """Raise 429 RATE_LIMITED if any of the limits in `limit` (e.g. "5/minute;30/day") is hit."""
    if not get_settings().rate_limit_enabled:
        return
    for item in parse_many(limit):
        if not _limiter.hit(item, scope, key):
            reset_at, _ = _limiter.get_window_stats(item, scope, key)
            retry_after = max(1, int(reset_at - time.time()))
            raise ApiError(
                429,
                "RATE_LIMITED",
                "Too many requests. Please wait and try again.",
                headers={"Retry-After": str(retry_after)},
            )


def reset() -> None:
    """Test helper."""
    _storage.reset()
