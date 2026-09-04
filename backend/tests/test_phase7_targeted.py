"""Phase 7 targeted backend tests (non-overlapping with smoke_test.sh).

Contracts derived from /app/project/backend/controllers/*.
"""
import pytest
import requests
import uuid as _uuid
from datetime import datetime, timedelta

BASE_URL = "http://127.0.0.1:8899"


@pytest.fixture(scope="session")
def admin_token():
    r = requests.post(f"{BASE_URL}/api/auth/login",
                      json={"username": "admin", "password": "admin123"})
    assert r.status_code == 200, r.text
    return r.json()["data"]["token"]


@pytest.fixture()
def admin_headers(admin_token):
    return {"Authorization": f"Bearer {admin_token}",
            "Content-Type": "application/json"}


def _create_ib(headers, minutes_from_now_start=1, duration_minutes=60):
    """Create IB session; returns session id.
    Add +12h offset so it's safely future regardless of server timezone (WIB=UTC+7)."""
    start = datetime.now() + timedelta(hours=12, minutes=minutes_from_now_start)
    end = start + timedelta(minutes=duration_minutes)
    payload = {
        "name": f"TEST_IB_{_uuid.uuid4().hex[:6]}",
        "start_at": start.strftime("%Y-%m-%d %H:%M:%S"),
        "end_at": end.strftime("%Y-%m-%d %H:%M:%S"),
        "target_type": "SEMUA",
    }
    r = requests.post(f"{BASE_URL}/api/monitoring/ib", json=payload, headers=headers)
    assert r.status_code in (200, 201), r.text
    return r.json()["data"]["id"]


# ---------- Cancel monitoring ----------
class TestCancelMonitoring:
    def test_cancel_sets_cancelled(self, admin_headers):
        sid = _create_ib(admin_headers, minutes_from_now_start=5, duration_minutes=60)
        c = requests.post(f"{BASE_URL}/api/monitoring/{sid}/cancel", headers=admin_headers)
        assert c.status_code == 200, c.text
        g = requests.get(f"{BASE_URL}/api/monitoring/{sid}", headers=admin_headers)
        assert g.status_code == 200
        assert g.json()["data"]["session"]["status"] == "CANCELLED"

    def test_double_cancel_rejected(self, admin_headers):
        """Per Phase 7 spec: cancelling an already-CANCELLED session should error.
        Currently backend silently no-ops (UPDATE ... WHERE status IN ('SCHEDULED','ACTIVE'))
        and returns success — this is expected to FAIL and be reported as a bug."""
        sid = _create_ib(admin_headers, minutes_from_now_start=5, duration_minutes=60)
        requests.post(f"{BASE_URL}/api/monitoring/{sid}/cancel", headers=admin_headers)
        c2 = requests.post(f"{BASE_URL}/api/monitoring/{sid}/cancel", headers=admin_headers)
        # spec expects 4xx; bug: returns 200
        assert c2.status_code in (400, 409, 422), \
            f"Double-cancel should be rejected, got {c2.status_code}: {c2.text}"


# ---------- Personnel PUT ----------
class TestPersonnelEdit:
    def test_edit_success(self, admin_headers):
        r = requests.get(f"{BASE_URL}/api/personnel?per_page=5", headers=admin_headers)
        assert r.status_code == 200
        items = r.json()["data"]["items"]
        target = items[0]
        new_name = f"TEST Edited {_uuid.uuid4().hex[:4]}"
        upd = requests.put(f"{BASE_URL}/api/personnel/{target['id']}",
                           json={"name": new_name, "nrp": target["nrp"],
                                 "rank": target.get("rank") or "PRATU",
                                 "unit_id": target.get("unit_id")},
                           headers=admin_headers)
        assert upd.status_code == 200, upd.text
        g = requests.get(f"{BASE_URL}/api/personnel/{target['id']}", headers=admin_headers)
        assert g.json()["data"]["personnel"]["name"] == new_name

    def test_duplicate_nrp_rejected(self, admin_headers):
        r = requests.get(f"{BASE_URL}/api/personnel?per_page=5", headers=admin_headers)
        items = r.json()["data"]["items"]
        target, other = items[0], items[1]
        dup = requests.put(f"{BASE_URL}/api/personnel/{target['id']}",
                           json={"name": target["name"], "nrp": other["nrp"],
                                 "rank": target.get("rank") or "PRATU",
                                 "unit_id": target.get("unit_id")},
                           headers=admin_headers)
        assert dup.status_code == 422, dup.text


# ---------- Alerts PUT status ----------
class TestAlertStatus:
    def test_transitions_and_invalid(self, admin_headers):
        r = requests.get(f"{BASE_URL}/api/alerts?per_page=20", headers=admin_headers)
        assert r.status_code == 200
        alerts = r.json()["data"]["items"]
        open_alert = next((a for a in alerts if a["status"] == "OPEN"), None)
        if not open_alert:
            pytest.skip("No OPEN alerts")
        aid = open_alert["id"]
        ack = requests.put(f"{BASE_URL}/api/alerts/{aid}/status",
                           json={"status": "ACKNOWLEDGED"}, headers=admin_headers)
        assert ack.status_code == 200, ack.text
        res = requests.put(f"{BASE_URL}/api/alerts/{aid}/status",
                           json={"status": "RESOLVED"}, headers=admin_headers)
        assert res.status_code == 200, res.text
        # Verify by listing
        r2 = requests.get(f"{BASE_URL}/api/alerts?per_page=100", headers=admin_headers)
        matched = [a for a in r2.json()["data"]["items"] if a["id"] == aid]
        if matched:
            assert matched[0]["status"] == "RESOLVED"
        bad = requests.put(f"{BASE_URL}/api/alerts/{aid}/status",
                           json={"status": "BOGUS"}, headers=admin_headers)
        assert bad.status_code == 422, bad.text


# ---------- Password change ----------
class TestPasswordChange:
    def test_change_and_restore(self):
        r = requests.post(f"{BASE_URL}/api/auth/login",
                          json={"username": "admin", "password": "admin123"})
        assert r.status_code == 200
        tok = r.json()["data"]["token"]
        h = {"Authorization": f"Bearer {tok}", "Content-Type": "application/json"}
        newpw = "admin456X"
        c = requests.put(f"{BASE_URL}/api/auth/password",
                         json={"current_password": "admin123", "new_password": newpw},
                         headers=h)
        assert c.status_code == 200, c.text
        fail = requests.post(f"{BASE_URL}/api/auth/login",
                             json={"username": "admin", "password": "admin123"})
        assert fail.status_code in (401, 422), fail.text
        ok = requests.post(f"{BASE_URL}/api/auth/login",
                           json={"username": "admin", "password": newpw})
        assert ok.status_code == 200, ok.text
        tok2 = ok.json()["data"]["token"]
        h2 = {"Authorization": f"Bearer {tok2}", "Content-Type": "application/json"}
        # RESTORE (critical — must happen even on assertion fail)
        restore = requests.put(f"{BASE_URL}/api/auth/password",
                               json={"current_password": newpw, "new_password": "admin123"},
                               headers=h2)
        assert restore.status_code == 200, restore.text
        v = requests.post(f"{BASE_URL}/api/auth/login",
                          json={"username": "admin", "password": "admin123"})
        assert v.status_code == 200


# ---------- Users CRUD ----------
class TestUsers:
    def test_create_edit_dup_login(self, admin_headers):
        uname = f"testuser_{_uuid.uuid4().hex[:6]}"
        payload = {"username": uname, "password": "testpass123",
                   "name": "TEST User", "role": "KOMANDAN"}
        c = requests.post(f"{BASE_URL}/api/users", json=payload, headers=admin_headers)
        assert c.status_code in (200, 201), c.text
        uid = c.json()["data"]["id"]
        # Duplicate
        dup = requests.post(f"{BASE_URL}/api/users", json=payload, headers=admin_headers)
        assert dup.status_code == 422, dup.text
        # Edit
        upd = requests.put(f"{BASE_URL}/api/users/{uid}",
                           json={"name": "TEST User Edited", "role": "KOMANDAN"},
                           headers=admin_headers)
        assert upd.status_code == 200, upd.text
        # Verify via list
        lst = requests.get(f"{BASE_URL}/api/users", headers=admin_headers)
        items = lst.json()["data"]["items"]
        me = next((u for u in items if u["id"] == uid), None)
        assert me and me["name"] == "TEST User Edited", me
        # Login as new user
        l = requests.post(f"{BASE_URL}/api/auth/login",
                          json={"username": uname, "password": "testpass123"})
        assert l.status_code == 200, l.text
        # Deactivate as cleanup
        requests.put(f"{BASE_URL}/api/users/{uid}",
                     json={"name": "TEST User Edited", "role": "KOMANDAN",
                           "is_active": False},
                     headers=admin_headers)


# ---------- Geofences (circle: latitude, longitude, radius) ----------
class TestGeofences:
    def test_crud(self, admin_headers):
        name = f"TEST_GF_{_uuid.uuid4().hex[:6]}"
        payload = {"name": name, "latitude": -6.2, "longitude": 106.8, "radius": 100}
        c = requests.post(f"{BASE_URL}/api/geofences", json=payload, headers=admin_headers)
        assert c.status_code in (200, 201), c.text
        # Find id via list
        lst = requests.get(f"{BASE_URL}/api/geofences", headers=admin_headers)
        assert lst.status_code == 200
        items = lst.json()["data"].get("items", lst.json()["data"])
        if isinstance(items, dict):
            items = items.get("items", [])
        mine = next((g for g in items if g.get("name") == name), None)
        assert mine, f"created geofence not listed: {items}"
        gid = mine["id"]
        upd = requests.put(f"{BASE_URL}/api/geofences/{gid}",
                           json={**payload, "name": name + "_v2"}, headers=admin_headers)
        assert upd.status_code == 200, upd.text
        d = requests.delete(f"{BASE_URL}/api/geofences/{gid}", headers=admin_headers)
        assert d.status_code in (200, 204), d.text

    def test_invalid_coords_rejected(self, admin_headers):
        bad = requests.post(f"{BASE_URL}/api/geofences",
                            json={"name": "TEST_BAD", "latitude": 999,
                                  "longitude": 106.8, "radius": 100},
                            headers=admin_headers)
        assert bad.status_code == 422, bad.text


# ---------- Device public validation ----------
class TestDevicePublicValidation:
    def test_status_missing(self):
        r = requests.get(f"{BASE_URL}/api/device/status")
        assert r.status_code == 422, r.text

    def test_location_sync_empty(self):
        r = requests.post(f"{BASE_URL}/api/location/sync",
                          json={"points": []},
                          headers={"Authorization": "Bearer invalid",
                                   "Content-Type": "application/json"})
        assert r.status_code in (401, 422), r.text

    def test_device_event_unknown_type(self):
        r = requests.post(f"{BASE_URL}/api/device/event",
                          json={"event_type": "UNKNOWN_XYZ",
                                "timestamp": "2025-01-01T00:00:00Z"},
                          headers={"Authorization": "Bearer invalid",
                                   "Content-Type": "application/json"})
        assert r.status_code in (401, 422), r.text


# ---------- Quick check durations ----------
class TestQuickCheck:
    @pytest.mark.parametrize("dur", [30, 60, 120, 45])
    def test_duration_variants(self, admin_headers, dur):
        payload = {"duration_minutes": dur, "target_type": "SEMUA",
                   "name": f"TEST_QC_{dur}_{_uuid.uuid4().hex[:4]}"}
        r = requests.post(f"{BASE_URL}/api/monitoring/quick-check",
                          json=payload, headers=admin_headers)
        assert r.status_code in (200, 201, 409), r.text
        if r.status_code in (200, 201):
            data = r.json()["data"]
            start = datetime.strptime(data["start_at"], "%Y-%m-%d %H:%M:%S")
            end = datetime.strptime(data["end_at"], "%Y-%m-%d %H:%M:%S")
            delta = (end - start).total_seconds() / 60
            assert abs(delta - dur) < 2, f"duration mismatch: {delta} vs {dur}"
            # cleanup
            requests.post(f"{BASE_URL}/api/monitoring/{data['id']}/cancel",
                          headers=admin_headers)

    def test_duration_zero_rejected(self, admin_headers):
        r = requests.post(f"{BASE_URL}/api/monitoring/quick-check",
                          json={"duration_minutes": 0, "target_type": "SEMUA"},
                          headers=admin_headers)
        assert r.status_code == 422, r.text
