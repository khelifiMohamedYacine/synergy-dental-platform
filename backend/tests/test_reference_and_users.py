from tests.conftest import login


# ------------------------------------------------------------------ public reference data
def test_public_lists_are_cacheable_and_active_only(client, reference_data, admin_headers):
    clinics = client.get("/api/v1/clinics")
    assert clinics.status_code == 200
    assert clinics.headers["Cache-Control"] == "public, max-age=300"
    assert len(clinics.json()) == 4
    cid = clinics.json()[0]["id"]
    client.patch(f"/api/v1/clinics/{cid}", json={"is_active": False}, headers=admin_headers)
    assert len(client.get("/api/v1/clinics").json()) == 3
    assert client.get(f"/api/v1/clinics/{cid}").status_code == 404
    everything = client.get("/api/v1/clinics?include_inactive=true", headers=admin_headers)
    assert len(everything.json()) == 4


def test_include_inactive_requires_admin(client, reference_data, staff_headers):
    assert client.get("/api/v1/clinics?include_inactive=true").status_code == 403
    r = client.get("/api/v1/treatments?include_inactive=true", headers=staff_headers)
    assert r.status_code == 403


def test_treatments_filter_by_clinic(client, reference_data):
    old_town = next(c for c in client.get("/api/v1/clinics").json() if c["slug"] == "old-town")
    slugs = {t["slug"] for t in client.get(f"/api/v1/treatments?clinic_id={old_town['id']}").json()}
    assert "fixed-braces" not in slugs
    assert "dental-implants" in slugs
    detail = client.get(f"/api/v1/clinics/{old_town['id']}").json()
    assert len(detail["treatment_ids"]) == len(slugs)


def test_admin_crud_and_conflicts(client, reference_data, admin_headers, staff_headers):
    body = {
        "slug": "seaside",
        "name": "Seaside Dental (demo)",
        "address_line1": "1 Promenade",
        "city": "Brighton",
        "postcode": "BN1 1AA",
        "phone": "020 7946 0505",
        "opening_hours": {"mon": [["09:00", "17:00"]]},
        "treatment_ids": [1, 2],
    }
    assert client.post("/api/v1/clinics", json=body, headers=staff_headers).status_code == 403
    created = client.post("/api/v1/clinics", json=body, headers=admin_headers)
    assert created.status_code == 201, created.text
    assert created.json()["treatment_ids"] == [1, 2]
    again = client.post("/api/v1/clinics", json=body, headers=admin_headers)
    assert again.status_code == 409
    bad_hours = {**body, "slug": "x", "opening_hours": {"mon": [["17:00", "09:00"]]}}
    assert client.post("/api/v1/clinics", json=bad_hours, headers=admin_headers).status_code == 422
    bad_slug = {**body, "slug": "Not A Slug"}
    assert client.post("/api/v1/clinics", json=bad_slug, headers=admin_headers).status_code == 422


def test_category_in_use_cannot_be_deleted(client, reference_data, admin_headers):
    response = client.delete("/api/v1/treatment-categories/1", headers=admin_headers)
    assert response.status_code == 409
    assert response.json()["error"]["code"] == "CATEGORY_IN_USE"
    new = client.post(
        "/api/v1/treatment-categories",
        json={"slug": "kids", "name": "Children's dentistry"},
        headers=admin_headers,
    ).json()
    assert client.delete(f"/api/v1/treatment-categories/{new['id']}",
                         headers=admin_headers).status_code == 204  # fmt: skip


def test_treatment_create_and_update(client, reference_data, admin_headers):
    created = client.post(
        "/api/v1/treatments",
        json={
            "category_id": 1,
            "slug": "fluoride",
            "name": "Fluoride varnish",
            "price_from_pence": 2500,
        },  # fmt: skip
        headers=admin_headers,
    )
    assert created.status_code == 201
    tid = created.json()["id"]
    updated = client.patch(f"/api/v1/treatments/{tid}", json={"is_active": False},
                           headers=admin_headers)  # fmt: skip
    assert updated.json()["is_active"] is False
    bad = client.post("/api/v1/treatments", json={"category_id": 999, "slug": "x", "name": "Xx"},
                      headers=admin_headers)  # fmt: skip
    assert bad.status_code == 422


# ------------------------------------------------------------------ users
def test_user_management_is_admin_only(client, staff_headers, admin_headers):
    assert client.get("/api/v1/users", headers=staff_headers).status_code == 403
    page = client.get("/api/v1/users", headers=admin_headers).json()
    assert page["total"] == 2


def test_create_user_validates_password_and_email(client, admin_headers):
    weak = {"email": "new@example.test", "full_name": "New Person", "password": "short"}
    assert client.post("/api/v1/users", json=weak, headers=admin_headers).status_code == 422
    common = {**weak, "password": "password1234"}
    assert client.post("/api/v1/users", json=common, headers=admin_headers).status_code == 422
    good = {**weak, "email": "New@Example.test", "password": "a-long-unique-passphrase"}
    created = client.post("/api/v1/users", json=good, headers=admin_headers)
    assert created.status_code == 201
    assert created.json()["email"] == "new@example.test"
    assert client.post("/api/v1/users", json=good, headers=admin_headers).status_code == 409
    assert login(client, "new@example.test", "a-long-unique-passphrase").status_code == 200


def test_admin_cannot_demote_or_disable_self(client, admin_headers):
    me = client.get("/api/v1/auth/me", headers=admin_headers).json()
    for change in ({"role": "STAFF"}, {"is_active": False}):
        r = client.patch(f"/api/v1/users/{me['id']}", json=change, headers=admin_headers)
        assert r.status_code == 409


def test_disabling_user_blocks_existing_token(client, staff_headers, admin_headers):
    staff = client.get("/api/v1/auth/me", headers=staff_headers).json()
    r = client.patch(f"/api/v1/users/{staff['id']}", json={"is_active": False},
                     headers=admin_headers)  # fmt: skip
    assert r.status_code == 200
    blocked = client.get("/api/v1/auth/me", headers=staff_headers)
    assert blocked.status_code == 403
    assert blocked.json()["error"]["code"] == "ACCOUNT_DISABLED"
