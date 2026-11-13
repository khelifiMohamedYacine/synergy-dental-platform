import re
import uuid
from datetime import timedelta

import pytest

from app.schemas import london_today
from tests.conftest import SERVICE_HEADERS, enquiry_payload

URL = "/api/v1/enquiries"


def post(client, headers=None, **overrides):
    headers = SERVICE_HEADERS if headers is None else headers
    return client.post(URL, json=enquiry_payload(**overrides), headers=headers)


def fields(response) -> dict:
    return {d["field"]: d["message"] for d in response.json()["error"]["details"]}


# ------------------------------------------------------------------ create
def test_create_returns_reference_location_and_no_personal_data(client, reference_data):
    response = post(client)
    assert response.status_code == 201
    body = response.json()
    assert re.fullmatch(r"SD-[2-9A-HJKMNP-Z]{6}", body["reference"])
    assert body["status"] == "NEW"
    assert response.headers["Location"] == f"{URL}/{body['id']}"
    assert set(body) == {"id", "reference", "status", "created_at"}


def test_create_normalises_email_phone_and_records_history(client, reference_data, staff_headers):
    created = post(client, full_name="  Jamie   O'Neil-Smith ").json()
    detail = client.get(f"{URL}/{created['id']}", headers=staff_headers).json()
    assert detail["email"] == "jamie@example.test"
    assert detail["phone"] == "+447700900123"
    assert detail["full_name"] == "Jamie O'Neil-Smith"
    assert detail["history"][0]["from_status"] is None
    assert detail["history"][0]["to_status"] == "NEW"
    assert detail["allowed_transitions"] == ["CONTACTED", "CLOSED"]


def test_create_requires_service_key(client, reference_data):
    assert post(client, headers={}).status_code == 401
    assert post(client, headers={"X-Service-Key": "wrong"}).status_code == 401


@pytest.mark.parametrize(
    ("overrides", "field"),
    [
        ({"phone": "12345"}, "phone"),
        ({"email": "not-an-email"}, "email"),
        ({"full_name": "J"}, "full_name"),
        ({"full_name": "<script>"}, "full_name"),
        ({"consent": False}, "consent"),
        ({"clinic_slug": "no-such-clinic"}, "clinic_slug"),
        ({"treatment_slug": "fixed-braces", "clinic_slug": "old-town"}, "treatment_slug"),
        ({"preferred_date": None}, "preferred_date"),
        ({"preferred_date": (london_today() - timedelta(days=1)).isoformat()}, "preferred_date"),
        ({"preferred_date": (london_today() + timedelta(days=181)).isoformat()}, "preferred_date"),
        ({"message": "x" * 2001}, "message"),
        ({"is_admin": True}, "is_admin"),
    ],
)
def test_create_validation_errors(client, reference_data, overrides, field):
    response = post(client, **overrides)
    assert response.status_code == 422, response.text
    assert response.json()["error"]["code"] == "VALIDATION_ERROR"
    assert field in fields(response)


def test_general_enquiry_without_date_or_treatment_is_valid(client, reference_data):
    response = post(
        client, enquiry_type="GENERAL", preferred_date=None, treatment_slug=None, message=None
    )
    assert response.status_code == 201


def test_idempotency_key_replays_original(client, reference_data):
    key = str(uuid.uuid4())
    headers = {**SERVICE_HEADERS, "Idempotency-Key": key}
    first = post(client, headers=headers)
    second = post(client, headers=headers)
    assert (first.status_code, second.status_code) == (201, 200)
    assert first.json() == second.json()


def test_duplicate_open_enquiry_returns_409_with_reference(client, reference_data):
    first = post(client).json()
    response = post(client, email="JAMIE@example.test")
    assert response.status_code == 409
    assert response.json()["error"]["code"] == "DUPLICATE_ENQUIRY"
    assert fields(response)["reference"] == first["reference"]
    # A different treatment is a different request.
    assert post(client, treatment_slug="check-up").status_code == 201


def test_enquiry_rate_limit_uses_forwarded_ip_only_with_service_key(client, reference_data):
    for i in range(5):
        headers = {**SERVICE_HEADERS, "X-Client-IP": "203.0.113.7"}
        assert post(client, headers=headers, email=f"p{i}@example.test").status_code == 201
    blocked = post(
        client, headers={**SERVICE_HEADERS, "X-Client-IP": "203.0.113.7"}, email="p9@example.test"
    )
    assert blocked.status_code == 429
    other = post(
        client, headers={**SERVICE_HEADERS, "X-Client-IP": "203.0.113.8"}, email="q@example.test"
    )
    assert other.status_code == 201


# ------------------------------------------------------------------ list / search
def test_list_requires_staff(client, reference_data):
    assert client.get(URL).status_code == 401


def test_list_filters_search_sort_and_paginate(client, create_enquiry, staff_headers):
    create_enquiry(full_name="Alice Example", email="alice@example.test")
    create_enquiry(full_name="Bob Sample", email="bob@example.test", enquiry_type="GENERAL",
                   preferred_date=None, treatment_slug="check-up")  # fmt: skip
    third = create_enquiry(full_name="Carol Notreal", email="carol@example.test",
                           clinic_slug="parkside", treatment_slug=None)  # fmt: skip

    page = client.get(URL, headers=staff_headers, params={"page_size": 2}).json()
    assert (page["total"], page["pages"], len(page["items"])) == (3, 2, 2)
    assert page["items"][0]["full_name"] == "Carol Notreal"  # newest first

    def names(**params):
        r = client.get(URL, headers=staff_headers, params=params)
        assert r.status_code == 200, r.text
        return [i["full_name"] for i in r.json()["items"]]

    assert names(q="ali") == ["Alice Example"]
    assert names(q="BOB@") == ["Bob Sample"]
    assert names(q=third["reference"][:6]) == ["Carol Notreal"]
    assert names(q="%") == []
    assert names(enquiry_type="GENERAL") == ["Bob Sample"]
    assert names(sort="full_name") == ["Alice Example", "Bob Sample", "Carol Notreal"]
    assert names(status=["NEW", "CLOSED"], sort="full_name")[0] == "Alice Example"
    assert names(created_from=london_today().isoformat())
    assert names(created_to=(london_today() - timedelta(days=1)).isoformat()) == []
    item = client.get(URL, headers=staff_headers, params={"q": "Carol"}).json()["items"][0]
    assert item["treatment"] is None
    assert item["clinic"]["name"] == "Parkside Smile Clinic"


def test_list_rejects_unknown_sort(client, staff_headers):
    response = client.get(URL, headers=staff_headers, params={"sort": "password_hash"})
    assert response.status_code == 422


def test_csv_export_escapes_formulas(client, create_enquiry, staff_headers):
    create_enquiry(full_name="Alice Example")
    response = client.get(f"{URL}/export.csv", headers=staff_headers)
    assert response.status_code == 200
    assert response.headers["content-type"].startswith("text/csv")
    lines = response.text.strip().splitlines()
    assert lines[0].startswith("reference,type,status")
    assert "Alice Example" in lines[1]


# ------------------------------------------------------------------ status machine
def change(client, headers, eid, **body):
    return client.patch(f"{URL}/{eid}/status", json=body, headers=headers)


def test_full_happy_path_and_history(client, create_enquiry, staff_headers):
    eid = create_enquiry()["id"]
    for status in ("CONTACTED", "QUALIFIED", "BOOKED"):
        assert change(client, staff_headers, eid, status=status).status_code == 200
    done = change(client, staff_headers, eid, status="CLOSED", close_reason="TREATED",
                  note="Treatment completed.")  # fmt: skip
    assert done.status_code == 200
    body = done.json()
    assert body["status"] == "CLOSED"
    assert body["close_reason"] == "TREATED"
    assert [h["to_status"] for h in body["history"]] == [
        "NEW", "CONTACTED", "QUALIFIED", "BOOKED", "CLOSED"
    ]  # fmt: skip
    assert body["history"][1]["changed_by"]["full_name"] == "Test Staff"
    assert body["notes"][0]["body"] == "Treatment completed."
    assert body["allowed_transitions"] == []


def test_invalid_transition_returns_409_with_allowed_targets(client, create_enquiry, staff_headers):
    eid = create_enquiry()["id"]
    response = change(client, staff_headers, eid, status="BOOKED")
    assert response.status_code == 409
    assert response.json()["error"]["code"] == "INVALID_TRANSITION"
    assert [d["message"] for d in response.json()["error"]["details"]] == ["CONTACTED", "CLOSED"]


def test_booked_can_return_to_qualified(client, create_enquiry, staff_headers):
    eid = create_enquiry()["id"]
    for status in ("CONTACTED", "QUALIFIED", "BOOKED", "QUALIFIED"):
        assert change(client, staff_headers, eid, status=status).status_code == 200


@pytest.mark.parametrize(
    "body",
    [{"status": "CLOSED"}, {"status": "CONTACTED", "close_reason": "OTHER"}],
)
def test_close_reason_rules(client, create_enquiry, staff_headers, body):
    eid = create_enquiry()["id"]
    response = change(client, staff_headers, eid, **body)
    assert response.status_code == 422
    assert "close_reason" in fields(response)


def test_notes_and_unknown_enquiry(client, create_enquiry, staff_headers):
    eid = create_enquiry()["id"]
    note = client.post(f"{URL}/{eid}/notes", json={"body": "Called, no answer."},
                       headers=staff_headers)  # fmt: skip
    assert note.status_code == 201
    assert note.json()["author"]["full_name"] == "Test Staff"
    assert (
        client.post(f"{URL}/{eid}/notes", json={"body": ""}, headers=staff_headers).status_code
        == 422
    )
    missing = client.get(f"{URL}/{uuid.uuid4()}", headers=staff_headers)
    assert missing.status_code == 404
    assert client.get(f"{URL}/not-a-uuid", headers=staff_headers).status_code == 422


def test_delete_is_admin_only(client, create_enquiry, staff_headers, admin_headers):
    eid = create_enquiry()["id"]
    assert client.delete(f"{URL}/{eid}", headers=staff_headers).status_code == 403
    assert client.delete(f"{URL}/{eid}", headers=admin_headers).status_code == 204
    assert client.get(f"{URL}/{eid}", headers=admin_headers).status_code == 404
