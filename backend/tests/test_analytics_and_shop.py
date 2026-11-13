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
    assert len(clinics) == 1
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
    assert summary.orders_last_30_days == 1
    assert summary.revenue_last_30_days_pence == 2499
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
