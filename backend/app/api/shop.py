"""Shop summary from WooCommerce (doc 05 §5.7, ADR-06).

Pulls recent orders with read-only consumer keys and caches the result for 60 seconds.
Customer names and addresses are never passed on to the dashboard.
"""

import threading
import time
from datetime import UTC, datetime, timedelta

import httpx2
from fastapi import APIRouter

from app.core.config import get_settings
from app.core.deps import StaffUser
from app.core.errors import ApiError
from app.schemas import ShopOrder, ShopSummary

router = APIRouter(prefix="/shop", tags=["shop"])

CACHE_SECONDS = 60
TIMEOUT_SECONDS = 3
PAID_STATUSES = {"processing", "completed", "on-hold"}

_cache: dict = {"at": 0.0, "value": None}
_lock = threading.Lock()


def _pence(amount: str) -> int:
    return round(float(amount or 0) * 100)


def _fetch_orders() -> list[dict]:
    settings = get_settings()
    if not (settings.wc_consumer_key and settings.wc_consumer_secret):
        raise ApiError(503, "SHOP_UNAVAILABLE", "The shop is not connected yet.")
    since = (datetime.now(UTC) - timedelta(days=30)).strftime("%Y-%m-%dT%H:%M:%S")
    try:
        response = httpx2.get(
            f"{settings.wc_base_url.rstrip('/')}/wp-json/wc/v3/orders",
            params={"after": since, "per_page": 100, "orderby": "date", "order": "desc"},
            auth=(settings.wc_consumer_key, settings.wc_consumer_secret),
            # Internal Docker network call. WooCommerce only accepts Basic auth when is_ssl()
            # is true; WordPress trusts X-Forwarded-Proto (see WORDPRESS_CONFIG_EXTRA).
            headers={"X-Forwarded-Proto": "https"},
            timeout=TIMEOUT_SECONDS,
        )
        response.raise_for_status()
        return response.json()
    except Exception as exc:  # network error, timeout, non-2xx, bad JSON
        raise ApiError(503, "SHOP_UNAVAILABLE", "The shop is temporarily unavailable.") from exc


def build_summary(orders: list[dict]) -> ShopSummary:
    paid = [o for o in orders if o.get("status") in PAID_STATUSES]
    recent = [
        ShopOrder(
            number=str(o.get("number") or o.get("id")),
            status=o.get("status", "unknown"),
            total_pence=_pence(o.get("total", "0")),
            created_at=datetime.fromisoformat(o["date_created_gmt"]).replace(tzinfo=UTC),
            item_count=sum(int(i.get("quantity", 0)) for i in o.get("line_items", [])),
        )
        for o in orders[:5]
    ]
    return ShopSummary(
        orders_last_30_days=len(paid),
        revenue_last_30_days_pence=sum(_pence(o.get("total", "0")) for o in paid),
        recent_orders=recent,
    )


@router.get("/summary", response_model=ShopSummary)
def shop_summary(_user: StaffUser) -> ShopSummary:
    with _lock:
        if _cache["value"] is not None and time.monotonic() - _cache["at"] < CACHE_SECONDS:
            return _cache["value"]
    value = build_summary(_fetch_orders())
    with _lock:
        _cache.update(at=time.monotonic(), value=value)
    return value
