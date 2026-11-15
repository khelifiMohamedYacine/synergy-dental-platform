from datetime import timedelta

import pytest
from sqlalchemy import text

from app.api import shop
from app.schemas import london_today


@pytest.fixture
def funnel(client, create_enquiry, staff_headers, db):
    """4 enquiries: one booked then treated, one still booked, one contacted, one new."""

    def move(eid, *steps):
        for step in steps:
            body = (
                {"status": step}
                if step != "CLOSED"
                else {"status": step, "close_reason": "TREATED"}
            )
            r = client.patch(f"/api/v1/enquiries/{eid}/status", json=body, headers=staff_headers)
            assert r.status_code == 200, r.text

    a = create_enquiry(email="a@example.test")["id"]
    b = create_enquiry(email="b@example.test")["id"]
    c = create_enquiry(email="c@example.test", treatment_slug="check-up")["id"]
    create_enquiry(email="d@example.test", treatment_slug=None)
    move(a, "CONTACTED", "QUALIFIED", "BOOKED", "CLOSED")
    move(b, "CONTACTED", "QUALIFIED", "BOOKED")
    move(c, "CONTACTED")
    # Make time-to-contact deterministic: a, b, c were contacted 2, 4 and 6 hours after creation.
    for eid, hours in ((a, 2), (b, 4), (c, 6)):
        db.execute(
            text("""
                UPDATE enquiry_status_history h
                SET changed_at = e.created_at + make_interval(hours => :h)
                FROM enquiries e
                WHERE e.id = h.enquiry_id AND e.id = :id AND h.to_status = 'CONTACTED'
            """),
            {"h": hours, "id": eid},
        )
    db.commit()


def get(client, headers, path, **params):
    response = client.get(f"/api/v1/analytics/{path}", headers=headers, params=params)
    assert response.status_code == 200, response.text
    return response.json()


def test_analytics_requires_staff(client):
    assert client.get("/api/v1/analytics/summary").status_code == 401


def test_summary_counts_booked_even_after_closing(client, funnel, staff_headers):
    s = get(client, staff_headers, "summary")
    assert s["total"] == 4
    assert s["booked"] == 2  # the TREATED one still counts as booked
    assert s["conversion_rate"] == 0.5
    assert s["by_status"] == {"NEW": 1, "CONTACTED": 1, "QUALIFIED": 0, "BOOKED": 1, "CLOSED": 1}
    assert s["median_hours_to_contact"] == 4.0


def test_by_treatment_and_clinic(client, funnel, staff_headers):
    rows = get(client, staff_headers, "by-treatment")
    implants = next(r for r in rows if r["treatment_name"] == "Dental implants")
    assert (implants["total"], implants["booked"], implants["conversion_rate"]) == (2, 2, 1.0)
    assert any(r["treatment_name"] == "Not sure yet" and r["treatment_id"] is None for r in rows)
    clinics = get(client, staff_headers, "by-clinic")
    assert len(clinics) == 4  # every active clinic is listed, busiest first
    assert [c["total"] for c in clinics[1:]] == [0, 0, 0]
    assert clinics[1]["conversion_rate"] is None
    assert clinics[0]["clinic_name"] == "Riverside Dental Studio"
    assert (clinics[0]["total"], clinics[0]["booked"], clinics[0]["conversion_rate"]) == (4, 2, 0.5)


def test_timeseries_fills_empty_periods(client, funnel, staff_headers):
    today = london_today()
    week_ago = (today - timedelta(days=6)).isoformat()
    params = {"interval": "day", "from": week_ago, "to": today.isoformat()}
    rows = get(client, staff_headers, "timeseries", **params)
    assert len(rows) == 7
    assert [r["total"] for r in rows] == [0, 0, 0, 0, 0, 0, 4]


def test_empty_period_has_null_rates(client, staff_headers):
    s = get(client, staff_headers, "summary", **{"from": "2020-01-01", "to": "2020-01-31"})
    assert s["total"] == 0
    assert s["conversion_rate"] is None
    assert s["median_hours_to_contact"] is None


def test_bad_period_rejected(client, staff_headers):
    r = client.get("/api/v1/analytics/summary", headers=staff_headers,
                   params={"from": "2026-02-01", "to": "2026-01-01"})  # fmt: skip
    assert r.status_code == 422


# ------------------------------------------------------------------ shop
def test_shop_summary_hides_customer_data():
    orders = [
        {"id": 7, "number": "1007", "status": "processing", "total": "24.99",
         "date_created_gmt": "2026-11-10T10:00:00", "billing": {"first_name": "Secret"},
         "line_items": [{"quantity": 2}, {"quantity": 1}]},
        {"id": 8, "number": "1008", "status": "cancelled", "total": "10.00",
         "date_created_gmt": "2026-11-11T10:00:00", "line_items": []},
    ]  # fmt: skip
    summary = shop.build_summary(orders)
    assert summary.paid_orders == 1
    assert summary.revenue_pence == 2499
    assert summary.average_order_pence == 2499
    assert summary.to_fulfil == 1
    assert summary.recent_orders[0].item_count == 3
    assert "Secret" not in summary.model_dump_json()


@pytest.mark.parametrize(
    "overrides",
    [
        {"wc_consumer_key": "", "wc_consumer_secret": ""},  # not connected yet
        {
            "wc_base_url": "http://127.0.0.1:9",
            "wc_consumer_key": "ck_x",
            "wc_consumer_secret": "cs_x",
        },
    ],
)
def test_shop_unavailable_is_503(client, staff_headers, monkeypatch, overrides):
    settings = shop.get_settings().model_copy(update=overrides)
    monkeypatch.setattr(shop, "get_settings", lambda: settings)
    r = client.get("/api/v1/shop/summary", headers=staff_headers)
    assert r.status_code == 503
    assert r.json()["error"]["code"] == "SHOP_UNAVAILABLE"


def test_order_out_and_transitions():
    order = {
        "id": 9, "number": "1009", "status": "on-hold", "total": "12.40",
        "date_created_gmt": "2026-11-12T09:30:00", "payment_method_title": "Bank transfer",
        "billing": {"first_name": "Ada", "last_name": "Shopper", "email": "ada@example.test",
                    "address_1": "1 High St", "city": "Leeds", "postcode": "LS1 1AA"},
        "shipping": {}, "shipping_lines": [{"method_title": "Standard delivery"}],
        "meta_data": [{"key": "_sd_collection_clinic", "value": "Riverside, Leeds"}],
        "line_items": [{"name": "Floss", "quantity": 2, "total": "8.45"}],
    }  # fmt: skip
    out = shop._order_out(order)
    assert out.customer_name == "Ada Shopper"
    assert out.delivery_address == "1 High St, Leeds, LS1 1AA"
    assert out.allowed_statuses == ["cancelled", "processing"]
    assert out.items[0].total_pence == 845
    assert out.collection_clinic == "Riverside, Leeds"


def test_product_payload_maps_pence_and_stock():
    payload = shop._product_payload(
        {"name": "Floss", "price_pence": 499, "sale_price_pence": None, "stock_quantity": None,
         "in_stock": False, "category_ids": [3], "recommended_after": "hygienist"}
    )  # fmt: skip
    assert payload["regular_price"] == "4.99"
    assert payload["sale_price"] == ""
    assert payload["manage_stock"] is False
    assert payload["stock_status"] == "outofstock"
    assert payload["categories"] == [{"id": 3}]


def test_shop_management_is_admin_only(client, staff_headers):
    assert client.get("/api/v1/shop/products", headers=staff_headers).status_code == 403
    assert client.get("/api/v1/shop/orders", headers=staff_headers).status_code == 403
    r = client.patch("/api/v1/shop/orders/1", json={"status": "completed"}, headers=staff_headers)
    assert r.status_code == 403
