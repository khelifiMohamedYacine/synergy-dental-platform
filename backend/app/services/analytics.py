"""Analytics (doc 04 §6.3, doc 05 §5.5). Each function is one aggregate SQL query.

"Booked" means the enquiry has ANY history row with to_status = BOOKED, so an enquiry that was
booked and later closed as TREATED still counts. Periods are Europe/London calendar days.
"""

from dataclasses import dataclass
from datetime import date, timedelta

from sqlalchemy import text
from sqlalchemy.orm import Session

from app.schemas import london_today

DEFAULT_DAYS = 90


@dataclass
class Period:
    start: date
    end: date  # inclusive
    clinic_id: int | None = None

    @classmethod
    def build(cls, start: date | None, end: date | None, clinic_id: int | None) -> "Period":
        end = end or london_today()
        start = start or end - timedelta(days=DEFAULT_DAYS - 1)
        return cls(start, end, clinic_id)

    def params(self) -> dict:
        return {"start": self.start, "end": self.end, "clinic_id": self.clinic_id}


# Enquiries created in the period, with an is_booked flag. Shared by every query below.
# Queries are built only by concatenating this constant with other constant SQL; every value
# (dates, clinic, interval) is a bound parameter. Hence the S608 suppressions below.
_SCOPE = """
    WITH scoped AS (
        SELECT e.*,
               EXISTS (SELECT 1 FROM enquiry_status_history h
                       WHERE h.enquiry_id = e.id AND h.to_status = 'BOOKED') AS is_booked
        FROM enquiries e
        WHERE e.created_at >= (CAST(:start AS date)::timestamp AT TIME ZONE 'Europe/London')
          AND e.created_at <  ((CAST(:end AS date) + 1)::timestamp AT TIME ZONE 'Europe/London')
          AND (CAST(:clinic_id AS integer) IS NULL OR e.clinic_id = CAST(:clinic_id AS integer))
    )
"""


def _rate(booked: int, total: int) -> float | None:
    return None if total == 0 else round(booked / total, 4)


def summary(db: Session, period: Period) -> dict:
    row = (
        db.execute(
            text(
                _SCOPE  # noqa: S608
                + """
        SELECT count(*) AS total,
               count(*) FILTER (WHERE status = 'NEW') AS new,
               count(*) FILTER (WHERE status = 'CONTACTED') AS contacted,
               count(*) FILTER (WHERE status = 'QUALIFIED') AS qualified,
               count(*) FILTER (WHERE status = 'BOOKED') AS booked_now,
               count(*) FILTER (WHERE status = 'CLOSED') AS closed,
               count(*) FILTER (WHERE is_booked) AS booked,
               (SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY hours)
                FROM (SELECT EXTRACT(EPOCH FROM (min(h.changed_at) - s.created_at)) / 3600
                             AS hours
                      FROM scoped s
                      JOIN enquiry_status_history h
                        ON h.enquiry_id = s.id AND h.to_status = 'CONTACTED'
                      GROUP BY s.id, s.created_at) firsts) AS median_hours
        FROM scoped
    """
            ),
            period.params(),
        )
        .mappings()
        .one()
    )
    median = row["median_hours"]
    return {
        "total": row["total"],
        "by_status": {
            "NEW": row["new"],
            "CONTACTED": row["contacted"],
            "QUALIFIED": row["qualified"],
            "BOOKED": row["booked_now"],
            "CLOSED": row["closed"],
        },
        "booked": row["booked"],
        "conversion_rate": _rate(row["booked"], row["total"]),
        "median_hours_to_contact": None if median is None else round(float(median), 1),
    }


def by_treatment(db: Session, period: Period) -> list[dict]:
    rows = db.execute(
        text(
            _SCOPE  # noqa: S608
            + """
        SELECT s.treatment_id AS id,
               COALESCE(t.name, 'Not sure yet') AS name,
               count(*) AS total,
               count(*) FILTER (WHERE s.is_booked) AS booked
        FROM scoped s LEFT JOIN treatments t ON t.id = s.treatment_id
        GROUP BY s.treatment_id, t.name
        ORDER BY total DESC, name
    """
        ),
        period.params(),
    ).mappings()
    return [
        {
            "treatment_id": r["id"],
            "treatment_name": r["name"],
            "total": r["total"],
            "booked": r["booked"],
            "conversion_rate": _rate(r["booked"], r["total"]),
        }
        for r in rows
    ]


def by_clinic(db: Session, period: Period) -> list[dict]:
    rows = db.execute(
        text(
            _SCOPE  # noqa: S608
            + """
        SELECT c.id AS id, c.name AS name,
               count(s.id) AS total,
               count(s.id) FILTER (WHERE s.is_booked) AS booked
        FROM clinics c LEFT JOIN scoped s ON s.clinic_id = c.id
        WHERE CAST(:clinic_id AS integer) IS NULL OR c.id = CAST(:clinic_id AS integer)
        GROUP BY c.id, c.name, c.is_active
        -- Every active clinic is listed (a clinic with no enquiries is worth seeing); inactive
        -- ones only when they had enquiries in the period.
        HAVING c.is_active OR count(s.id) > 0
        ORDER BY total DESC, name
    """
        ),
        period.params(),
    ).mappings()
    return [
        {
            "clinic_id": r["id"],
            "clinic_name": r["name"],
            "total": r["total"],
            "booked": r["booked"],
            "conversion_rate": _rate(r["booked"], r["total"]),
        }
        for r in rows
    ]


INTERVALS = {"day": "1 day", "week": "1 week", "month": "1 month"}


def timeseries(db: Session, period: Period, interval: str) -> list[dict]:
    step = INTERVALS[interval]  # allow-listed; still passed as bound parameters below
    sql = (
        _SCOPE  # noqa: S608
        + """
        , buckets AS (
            SELECT generate_series(
                date_trunc(:unit, CAST(:start AS date)::timestamp),
                date_trunc(:unit, CAST(:end AS date)::timestamp),
                CAST(:step AS interval))::date AS period_start
        )
        SELECT b.period_start,
               count(s.id) AS total,
               count(s.id) FILTER (WHERE s.is_booked) AS booked
        FROM buckets b
        LEFT JOIN scoped s
          ON date_trunc(:unit, (s.created_at AT TIME ZONE 'Europe/London'))::date
             = b.period_start
        GROUP BY b.period_start
        ORDER BY b.period_start
    """
    )
    params = {**period.params(), "unit": interval, "step": step}
    rows = db.execute(text(sql), params).mappings()
    return [dict(r) for r in rows]
