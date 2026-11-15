import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { useAuth } from '../auth/AuthContext.jsx'
import { useApi } from '../hooks/useApi.js'
import { LoadError, Loading } from '../components/ui.jsx'
import {
  formatDateTime,
  formatNumber,
  formatPence,
  formatPercent,
  formatShortDate,
  londonDateMinus,
  ORDER_STATUS_LABELS,
} from '../utils/format.js'
import './overview.css'

const PERIODS = [
  { days: 7, label: 'Last 7 days', interval: 'day', bucket: 'Day' },
  { days: 30, label: 'Last 30 days', interval: 'day', bucket: 'Day' },
  { days: 90, label: 'Last 90 days', interval: 'week', bucket: 'Week starting' },
  { days: 365, label: 'Last 12 months', interval: 'month', bucket: 'Month' },
]

function Kpi({ label, value, hint }) {
  return (
    <div className="card kpi">
      <div className="kpi-label">{label}</div>
      <div className="kpi-value">{value}</div>
      {hint && <div className="muted kpi-hint">{hint}</div>}
    </div>
  )
}

function Panel({ title, description, state, children, tableView, isEmpty, emptyText = 'No enquiries in this period.' }) {
  const [asTable, setAsTable] = useState(false)
  // ids cannot contain spaces: aria-labelledby treats a space as a list separator.
  const headingId = `panel-${title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`
  const empty = state.data && isEmpty?.(state.data)
  return (
    <section className="card panel" aria-labelledby={headingId}>
      <div className="panel-head">
        <div>
          <h2 id={headingId}>{title}</h2>
          {description && <p className="muted panel-desc">{description}</p>}
        </div>
        {tableView && state.data && !empty && (
          <button type="button" className="btn-link" aria-pressed={asTable} onClick={() => setAsTable((v) => !v)}>
            {asTable ? 'View as chart' : 'View as table'}
          </button>
        )}
      </div>
      {state.loading && !state.data && <Loading lines={4} />}
      <LoadError error={state.error} onRetry={state.reload} />
      {state.data &&
        (empty ? (
          <p className="panel-empty">{emptyText}</p>
        ) : asTable && tableView ? (
          tableView(state.data)
        ) : (
          children(state.data)
        ))}
    </section>
  )
}

const noRows = (rows) => !rows.length || rows.every((r) => !r.total)

export default function Overview() {
  const { isAdmin } = useAuth()
  const [periodIdx, setPeriodIdx] = useState(2)
  const [clinicId, setClinicId] = useState('')
  const period = PERIODS[periodIdx]
  const range = { from: londonDateMinus(period.days - 1), to: londonDateMinus(0) }
  const params = { ...range, clinic_id: clinicId || undefined }

  const clinics = useApi('/clinics')
  const summary = useApi('/analytics/summary', params)
  const series = useApi('/analytics/timeseries', { ...params, interval: period.interval })
  const byTreatment = useApi('/analytics/by-treatment', params)
  const byClinic = useApi('/analytics/by-clinic', params)
  const shop = useApi('/shop/summary', range)

  const s = summary.data
  const clinicName = clinicId ? clinics.data?.find((c) => String(c.id) === clinicId)?.name : null
  return (
    <>
      <div className="page-head">
        <div>
          <h1>Overview</h1>
          <p className="muted page-sub">
            Enquiries received in the {period.label.toLowerCase()}
            {clinicName ? ` at ${clinicName}` : ' across all clinics'}.
          </p>
        </div>
        <div className="row filters">
          <div>
            <label htmlFor="period">Period</label>
            <select id="period" value={periodIdx} onChange={(e) => setPeriodIdx(Number(e.target.value))}>
              {PERIODS.map((p, i) => (
                <option key={p.days} value={i}>
                  {p.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="clinic">Clinic</label>
            <select id="clinic" value={clinicId} onChange={(e) => setClinicId(e.target.value)}>
              <option value="">All clinics</option>
              {(clinics.data || []).map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      <LoadError error={summary.error} onRetry={summary.reload} />
      <div className="kpis" aria-busy={summary.loading}>
        <Kpi label="Total enquiries" value={s ? formatNumber(s.total) : '…'} hint="Received in this period" />
        <Kpi
          label="Awaiting first contact"
          value={s ? formatNumber(s.by_status.NEW) : '…'}
          hint={s?.by_status.NEW ? <Link to="/enquiries?status=NEW">Open the list</Link> : 'Nobody is waiting'}
        />
        <Kpi label="Booked" value={s ? formatNumber(s.booked) : '…'} hint="Reached “Booked” at any point" />
        <Kpi label="Conversion" value={s ? formatPercent(s.conversion_rate) : '…'} hint="Booked ÷ enquiries" />
        <Kpi
          label="Time to first contact"
          value={s ? (s.median_hours_to_contact === null ? '—' : `${s.median_hours_to_contact} h`) : '…'}
          hint="Median, for enquiries contacted so far"
        />
      </div>

      <div className="grid-2">
        <Panel
          title="Enquiries over time"
          description={`Per ${period.interval}: all enquiries, and those that went on to book.`}
          state={series}
          isEmpty={noRows}
          tableView={(rows) => (
            <div className="table-scroll" tabIndex={0}>
              <table className="data">
                <thead>
                  <tr>
                    <th scope="col">{period.bucket}</th>
                    <th scope="col" className="num">
                      Enquiries
                    </th>
                    <th scope="col" className="num">
                      Booked
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={r.period_start}>
                      <td>{formatShortDate(r.period_start)}</td>
                      <td className="num">{formatNumber(r.total)}</td>
                      <td className="num">{formatNumber(r.booked)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        >
          {(rows) => (
            <div className="chart" role="img" aria-label="Line chart of enquiries and bookings over time">
              <ResponsiveContainer width="100%" height={260}>
                <LineChart data={rows} margin={{ top: 8, right: 16, left: 0, bottom: 0 }}>
                  <CartesianGrid stroke="#e3e8e9" vertical={false} />
                  <XAxis dataKey="period_start" tickFormatter={formatShortDate} fontSize={12} minTickGap={24} />
                  <YAxis allowDecimals={false} fontSize={12} width={36} />
                  <Tooltip labelFormatter={formatShortDate} />
                  <Legend />
                  <Line type="monotone" dataKey="total" name="Enquiries" stroke="var(--chart-1)" strokeWidth={2} dot={false} />
                  <Line type="monotone" dataKey="booked" name="Booked" stroke="var(--chart-2)" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          )}
        </Panel>

        <Panel
          title="By treatment"
          description="Which treatments people ask about, and how many book."
          state={byTreatment}
          isEmpty={noRows}
          tableView={(rows) => <GroupTable rows={rows} nameKey="treatment_name" label="Treatment" />}
        >
          {(rows) => (
            <div className="chart" role="img" aria-label="Bar chart of enquiries and bookings by treatment">
              <ResponsiveContainer width="100%" height={Math.max(220, rows.length * 32)}>
                <BarChart data={rows} layout="vertical" margin={{ top: 0, right: 16, left: 8, bottom: 0 }}>
                  <CartesianGrid stroke="#e3e8e9" horizontal={false} />
                  <XAxis type="number" allowDecimals={false} fontSize={12} />
                  <YAxis type="category" dataKey="treatment_name" width={150} fontSize={12} />
                  <Tooltip />
                  <Legend />
                  <Bar dataKey="total" name="Enquiries" fill="var(--chart-1)" radius={[0, 4, 4, 0]} />
                  <Bar dataKey="booked" name="Booked" fill="var(--chart-2)" radius={[0, 4, 4, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          )}
        </Panel>
      </div>

      <Panel
        title="By clinic"
        description="Enquiries, bookings and conversion for each clinic, busiest first."
        state={byClinic}
        isEmpty={(rows) => !rows.length}
        emptyText="No clinics to show."
      >
        {(rows) => <GroupTable rows={rows} nameKey="clinic_name" label="Clinic" />}
      </Panel>

      <ShopPanel state={shop} periodLabel={period.label} isAdmin={isAdmin} />
    </>
  )
}

function GroupTable({ rows, nameKey, label }) {
  const total = rows.reduce((sum, r) => sum + r.total, 0)
  const booked = rows.reduce((sum, r) => sum + r.booked, 0)
  return (
    <div className="table-scroll" tabIndex={0}>
      <table className="data group-table">
        <thead>
          <tr>
            <th scope="col">{label}</th>
            <th scope="col" className="num">
              Enquiries
            </th>
            <th scope="col" className="share-col">
              Share of enquiries
            </th>
            <th scope="col" className="num">
              Booked
            </th>
            <th scope="col" className="num">
              Conversion
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => {
            const share = total ? r.total / total : 0
            return (
              <tr key={r[nameKey]}>
                <th scope="row">{r[nameKey]}</th>
                <td className="num">{formatNumber(r.total)}</td>
                <td className="share-col">
                  <span className="share-cell">
                    <span className="share" aria-hidden="true">
                      <span className="share-bar" style={{ width: `${(share * 100).toFixed(1)}%` }} />
                    </span>
                    <span className="share-value">{total ? formatPercent(share) : '—'}</span>
                  </span>
                </td>
                <td className="num">{formatNumber(r.booked)}</td>
                <td className="num">{formatPercent(r.conversion_rate)}</td>
              </tr>
            )
          })}
        </tbody>
        <tfoot>
          <tr>
            <th scope="row">All</th>
            <td className="num">{formatNumber(total)}</td>
            <td className="share-col">{total ? '100%' : '—'}</td>
            <td className="num">{formatNumber(booked)}</td>
            <td className="num">{formatPercent(total ? booked / total : null)}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  )
}

function ShopPanel({ state, periodLabel, isAdmin }) {
  const d = state.data
  return (
    <section className="card panel shop-panel" aria-labelledby="shop-h">
      <div className="panel-head">
        <div>
          <h2 id="shop-h">Shop</h2>
          <p className="muted panel-desc">Website shop orders in the {periodLabel.toLowerCase()} (all clinics).</p>
        </div>
        {isAdmin && (
          <Link className="btn btn-secondary btn-small" to="/shop?tab=orders">
            Manage orders
          </Link>
        )}
      </div>
      {state.loading && !d && <Loading lines={3} />}
      {state.error && (
        <p className="banner banner-warning" role="status">
          {state.error.code === 'SHOP_UNAVAILABLE'
            ? 'Shop data is unavailable right now. The rest of the dashboard is unaffected.'
            : state.error.message}
        </p>
      )}
      {d && (
        <>
          <div className="kpis shop-kpis">
            <Kpi label="Revenue" value={formatPence(d.revenue_pence)} hint="From paid orders" />
            <Kpi label="Paid orders" value={formatNumber(d.paid_orders)} />
            <Kpi label="Average order" value={formatPence(d.average_order_pence)} />
            <Kpi label="To dispatch" value={formatNumber(d.to_fulfil)} hint="Paid, not yet completed" />
            <Kpi label="Awaiting payment" value={formatNumber(d.awaiting_payment)} hint="For example bank transfer" />
          </div>
          {d.recent_orders.length ? (
            <div className="table-scroll" tabIndex={0}>
              <table className="data">
                <caption className="panel-caption">Latest orders</caption>
                <thead>
                  <tr>
                    <th scope="col">Order</th>
                    <th scope="col">Placed</th>
                    <th scope="col">Status</th>
                    <th scope="col" className="num">
                      Items
                    </th>
                    <th scope="col" className="num">
                      Total
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {d.recent_orders.map((o) => (
                    <tr key={o.number}>
                      <td>#{o.number}</td>
                      <td>{formatDateTime(o.created_at)}</td>
                      <td>
                        <span className={`badge order-${o.status}`}>{ORDER_STATUS_LABELS[o.status] ?? o.status}</span>
                      </td>
                      <td className="num">{o.item_count}</td>
                      <td className="num">{formatPence(o.total_pence)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="panel-empty">No shop orders in this period.</p>
          )}
        </>
      )}
    </section>
  )
}
