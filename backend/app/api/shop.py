"""Shop: summary for staff, product and order management for admins (doc 05 §5.7, ADR-06).

The business API talks to WooCommerce's REST API with a read/write consumer key over the
internal Docker network. Staff see totals only; admins manage products and orders and see the
customer details they need to fulfil an order.
"""

import re
import threading
import time
from datetime import UTC, date, datetime, timedelta
from datetime import time as dtime
from decimal import Decimal
from typing import Annotated, Any, Literal
from urllib.parse import urlsplit

import httpx2
from fastapi import APIRouter, Query, Response
from pydantic import BaseModel, Field

from app.core.config import get_settings
from app.core.deps import AdminUser, StaffUser
from app.core.errors import ApiError
from app.schemas import ShopOrder, ShopSummary

router = APIRouter(prefix="/shop", tags=["shop"])

CACHE_SECONDS = 60
TIMEOUT_SECONDS = 5
PAID_STATUSES = {"processing", "completed"}
AWAITING_PAYMENT = {"pending", "on-hold"}
MAX_PAGES = 10  # the summary reads at most 1,000 orders per period

# What an admin may change an order to, from each status (WooCommerce itself allows anything).
ORDER_TRANSITIONS: dict[str, set[str]] = {
    "pending": {"processing", "cancelled"},
    "on-hold": {"processing", "cancelled"},
    "processing": {"completed", "cancelled"},
    "failed": {"cancelled"},
}

_cache: dict[tuple, tuple[float, ShopSummary]] = {}
_lock = threading.Lock()


def _pence(amount: str | None) -> int:
    return int((Decimal(amount or "0") * 100).to_integral_value())


def _pounds(pence: int | None) -> str:
    return "" if pence is None else f"{Decimal(pence) / 100:.2f}"


def _strip_tags(html: str) -> str:
    return re.sub(r"<[^>]+>", "", html or "").strip()


def _path_only(url: str) -> str:
    """Site-relative link: WordPress builds absolute URLs from the internal request's host."""
    parts = urlsplit(url)
    return parts.path + (f"?{parts.query}" if parts.query else "")


def _wc(method: str, path: str, **kwargs: Any) -> httpx2.Response:
    settings = get_settings()
    if not (settings.wc_consumer_key and settings.wc_consumer_secret):
        raise ApiError(503, "SHOP_UNAVAILABLE", "The shop is not connected yet.")
    try:
        response = httpx2.request(
            method,
            f"{settings.wc_base_url.rstrip('/')}/wp-json/wc/v3/{path}",
            auth=(settings.wc_consumer_key, settings.wc_consumer_secret),
            # Internal Docker network call. WooCommerce only accepts Basic auth when is_ssl()
            # is true; WordPress trusts X-Forwarded-Proto (see WORDPRESS_CONFIG_EXTRA).
            headers={"X-Forwarded-Proto": "https"},
            timeout=TIMEOUT_SECONDS,
            **kwargs,
        )
    except Exception as exc:  # network error, timeout
        raise ApiError(503, "SHOP_UNAVAILABLE", "The shop is temporarily unavailable.") from exc
    if response.status_code == 404:
        raise ApiError(404, "NOT_FOUND", "Not found in the shop.")
    if response.status_code == 400:
        try:
            message = response.json().get("message") or "The shop rejected the change."
        except ValueError:
            message = "The shop rejected the change."
        raise ApiError(422, "VALIDATION_ERROR", _strip_tags(message))
    if response.status_code >= 300:
        raise ApiError(503, "SHOP_UNAVAILABLE", "The shop is temporarily unavailable.")
    return response


def _clear_cache() -> None:
    with _lock:
        _cache.clear()


# ------------------------------------------------------------------ summary (staff)
def build_summary(orders: list[dict]) -> ShopSummary:
    paid = [o for o in orders if o.get("status") in PAID_STATUSES]
    revenue = sum(_pence(o.get("total")) for o in paid)
    return ShopSummary(
        paid_orders=len(paid),
        revenue_pence=revenue,
        average_order_pence=round(revenue / len(paid)) if paid else None,
        awaiting_payment=sum(1 for o in orders if o.get("status") in AWAITING_PAYMENT),
        to_fulfil=sum(1 for o in orders if o.get("status") == "processing"),
        recent_orders=[
            ShopOrder(
                number=str(o.get("number") or o.get("id")),
                status=o.get("status", "unknown"),
                total_pence=_pence(o.get("total")),
                created_at=datetime.fromisoformat(o["date_created_gmt"]).replace(tzinfo=UTC),
                item_count=sum(int(i.get("quantity", 0)) for i in o.get("line_items", [])),
            )
            for o in orders[:5]
        ],
    )


def _orders_between(start: date, end: date) -> list[dict]:
    params = {
        "after": datetime.combine(start, dtime.min).isoformat(),
        "before": datetime.combine(end + timedelta(days=1), dtime.min).isoformat(),
        "per_page": 100,
        "orderby": "date",
        "order": "desc",
    }
    orders: list[dict] = []
    for page in range(1, MAX_PAGES + 1):
        response = _wc("GET", "orders", params={**params, "page": page})
        orders += response.json()
        if page >= int(response.headers.get("X-WP-TotalPages", "1")):
            break
    return orders


@router.get("/summary", response_model=ShopSummary)
def shop_summary(
    _user: StaffUser,
    from_: Annotated[date | None, Query(alias="from")] = None,
    to: date | None = None,
) -> ShopSummary:
    end = to or datetime.now(UTC).date()
    start = from_ or end - timedelta(days=29)
    if start > end:
        raise ApiError(422, "VALIDATION_ERROR", "'from' must be on or before 'to'.")
    with _lock:
        hit = _cache.get((start, end))
        if hit and time.monotonic() - hit[0] < CACHE_SECONDS:
            return hit[1]
    value = build_summary(_orders_between(start, end))
    with _lock:
        _cache[(start, end)] = (time.monotonic(), value)
    return value


# ------------------------------------------------------------------ products (admin)
Pence = Annotated[int, Field(ge=0, le=10_000_000)]
Slug = Annotated[str, Field(max_length=100, pattern=r"^[a-z0-9-]*$")]


class ProductOut(BaseModel):
    id: int
    name: str
    status: str
    price_pence: int | None
    sale_price_pence: int | None
    short_description: str
    category_ids: list[int]
    stock_status: str
    stock_quantity: int | None
    recommended_after: str
    image_url: str | None
    permalink: str
    edit_url: str


class ProductIn(BaseModel):
    name: str = Field(min_length=2, max_length=200)
    status: Literal["publish", "draft"] = "publish"
    price_pence: Pence
    sale_price_pence: Pence | None = None
    short_description: str = Field(default="", max_length=2000)
    category_ids: list[int] = Field(default_factory=list, max_length=10)
    stock_quantity: int | None = Field(default=None, ge=0, le=1_000_000)
    in_stock: bool = True
    recommended_after: Slug = ""


class ProductPatch(BaseModel):
    name: str | None = Field(default=None, min_length=2, max_length=200)
    status: Literal["publish", "draft"] | None = None
    price_pence: Pence | None = None
    sale_price_pence: Pence | None = None
    short_description: str | None = Field(default=None, max_length=2000)
    category_ids: list[int] | None = Field(default=None, max_length=10)
    stock_quantity: int | None = Field(default=None, ge=0, le=1_000_000)
    in_stock: bool | None = None
    recommended_after: Slug | None = None


class Category(BaseModel):
    id: int
    name: str
    count: int


def _product_out(p: dict) -> ProductOut:
    meta = {m.get("key"): m.get("value") for m in p.get("meta_data", [])}
    return ProductOut(
        id=p["id"],
        name=p.get("name", ""),
        status=p.get("status", "draft"),
        price_pence=_pence(p["regular_price"]) if p.get("regular_price") else None,
        sale_price_pence=_pence(p["sale_price"]) if p.get("sale_price") else None,
        short_description=_strip_tags(p.get("short_description", "")),
        category_ids=[c["id"] for c in p.get("categories", [])],
        stock_status=p.get("stock_status", "instock"),
        stock_quantity=p.get("stock_quantity") if p.get("manage_stock") else None,
        recommended_after=str(meta.get("_sd_recommended_after") or ""),
        image_url=(p.get("images") or [{}])[0].get("src"),
        permalink=_path_only(p.get("permalink", "")),
        # Same origin as the dashboard (Caddy routes /wp-admin to WordPress).
        edit_url=f"/wp-admin/post.php?post={p['id']}&action=edit",
    )


def _product_payload(fields: dict) -> dict:
    payload: dict[str, Any] = {}
    for key in ("name", "status", "short_description"):
        if fields.get(key) is not None:
            payload[key] = fields[key]
    if fields.get("price_pence") is not None:
        payload["regular_price"] = _pounds(fields["price_pence"])
    if "sale_price_pence" in fields:
        payload["sale_price"] = _pounds(fields["sale_price_pence"])  # None clears the sale
    if fields.get("category_ids") is not None:
        payload["categories"] = [{"id": i} for i in fields["category_ids"]]
    if "stock_quantity" in fields:
        qty = fields["stock_quantity"]
        payload["manage_stock"] = qty is not None
        if qty is not None:
            payload["stock_quantity"] = qty
    if fields.get("in_stock") is not None and fields.get("stock_quantity") is None:
        payload["stock_status"] = "instock" if fields["in_stock"] else "outofstock"
    if fields.get("recommended_after") is not None:
        payload["meta_data"] = [
            {"key": "_sd_recommended_after", "value": fields["recommended_after"]}
        ]
    return payload


@router.get("/products", response_model=list[ProductOut])
def list_products(
    _admin: AdminUser, q: Annotated[str | None, Query(max_length=100)] = None
) -> list[ProductOut]:
    params: dict[str, Any] = {"per_page": 100, "status": "any", "orderby": "title", "order": "asc"}
    if q:
        params["search"] = q
    return [_product_out(p) for p in _wc("GET", "products", params=params).json()]


@router.post("/products", response_model=ProductOut, status_code=201)
def create_product(body: ProductIn, _admin: AdminUser) -> ProductOut:
    payload = {"type": "simple", **_product_payload(body.model_dump())}
    return _product_out(_wc("POST", "products", json=payload).json())


@router.patch("/products/{product_id}", response_model=ProductOut)
def update_product(product_id: int, body: ProductPatch, _admin: AdminUser) -> ProductOut:
    payload = _product_payload(body.model_dump(exclude_unset=True))
    return _product_out(_wc("PUT", f"products/{product_id}", json=payload).json())


@router.delete("/products/{product_id}", status_code=204)
def delete_product(product_id: int, _admin: AdminUser) -> Response:
    _wc("DELETE", f"products/{product_id}")  # moves it to the bin; restorable in WordPress
    return Response(status_code=204)


@router.get("/categories", response_model=list[Category])
def list_categories(_admin: AdminUser) -> list[Category]:
    rows = _wc("GET", "products/categories", params={"per_page": 100, "hide_empty": False})
    return [
        Category(id=c["id"], name=_strip_tags(c["name"]), count=c.get("count", 0))
        for c in rows.json()
        if c.get("slug") != "uncategorized"
    ]


# ------------------------------------------------------------------ orders (admin)
class OrderLine(BaseModel):
    name: str
    quantity: int
    total_pence: int


class OrderOut(BaseModel):
    id: int
    number: str
    status: str
    allowed_statuses: list[str]
    created_at: datetime
    total_pence: int
    customer_name: str
    customer_email: str
    customer_phone: str
    delivery_method: str
    delivery_address: str
    collection_clinic: str
    payment_method: str
    customer_note: str
    items: list[OrderLine]


class OrderPage(BaseModel):
    items: list[OrderOut]
    page: int
    pages: int
    total: int


class OrderPatch(BaseModel):
    status: Literal["processing", "completed", "cancelled"]


def _order_out(o: dict) -> OrderOut:
    billing = o.get("billing") or {}
    shipping = o.get("shipping") or {}
    meta = {m.get("key"): m.get("value") for m in o.get("meta_data", [])}
    address = shipping if shipping.get("address_1") else billing
    parts = [address.get(k, "") for k in ("address_1", "address_2", "city", "postcode")]
    status = o.get("status", "pending")
    return OrderOut(
        id=o["id"],
        number=str(o.get("number") or o["id"]),
        status=status,
        allowed_statuses=sorted(ORDER_TRANSITIONS.get(status, set())),
        created_at=datetime.fromisoformat(o["date_created_gmt"]).replace(tzinfo=UTC),
        total_pence=_pence(o.get("total")),
        customer_name=f"{billing.get('first_name', '')} {billing.get('last_name', '')}".strip(),
        customer_email=billing.get("email", ""),
        customer_phone=billing.get("phone", ""),
        delivery_method=", ".join(s.get("method_title", "") for s in o.get("shipping_lines", [])),
        delivery_address=", ".join(p for p in parts if p),
        collection_clinic=str(meta.get("_sd_collection_clinic") or ""),
        payment_method=o.get("payment_method_title", ""),
        customer_note=o.get("customer_note", ""),
        items=[
            OrderLine(
                name=i.get("name", ""),
                quantity=int(i.get("quantity", 0)),
                total_pence=_pence(i.get("total")),
            )
            for i in o.get("line_items", [])
        ],
    )


@router.get("/orders", response_model=OrderPage)
def list_orders(
    _admin: AdminUser,
    status: Annotated[str | None, Query(pattern=r"^[a-z-]+$")] = None,
    q: Annotated[str | None, Query(max_length=100)] = None,
    page: Annotated[int, Query(ge=1)] = 1,
) -> OrderPage:
    params: dict[str, Any] = {"per_page": 20, "page": page, "orderby": "date", "order": "desc"}
    if status:
        params["status"] = status
    if q:
        params["search"] = q
    response = _wc("GET", "orders", params=params)
    return OrderPage(
        items=[_order_out(o) for o in response.json()],
        page=page,
        pages=max(1, int(response.headers.get("X-WP-TotalPages", "1"))),
        total=int(response.headers.get("X-WP-Total", "0")),
    )


@router.patch("/orders/{order_id}", response_model=OrderOut)
def update_order(order_id: int, body: OrderPatch, _admin: AdminUser) -> OrderOut:
    current = _wc("GET", f"orders/{order_id}").json().get("status", "")
    if body.status not in ORDER_TRANSITIONS.get(current, set()):
        message = f"An order that is {current} cannot be changed to {body.status}."
        raise ApiError(409, "INVALID_TRANSITION", message)
    updated = _wc("PUT", f"orders/{order_id}", json={"status": body.status}).json()
    _clear_cache()
    return _order_out(updated)
