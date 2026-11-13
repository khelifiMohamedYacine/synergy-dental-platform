import { useState } from 'react'
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
import { useApi } from '../hooks/useApi.js'
import { LoadError, Loading } from '../components/ui.jsx'
import {
  formatDateTime,
  formatNumber,
  formatPence,
  formatPercent,
  formatShortDate,
  londonDateMinus,
} from '../utils/format.js'
import './overview.css'

const PERIODS = [
  { days: 30, label: 'Last 30 days', interval: 'day' },
  { days: 90, label: 'Last 90 days', interval: 'week' },
  { days: 365, label: 'Last 12 months', interval: 'month' },
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

function Panel({ title, state, children, tableView }) {
  const [asTable, setAsTable] = useState(false)
  return (
    <section className="card panel" aria-labelledby={`${title}-h`}>
      <div className="panel-head">
        <h2 id={`${title}-h`}>{title}</h2>
        {tableView && state.data && (
          <button type="button" className="btn-link" aria-pressed={asTable} onClick={() => setAsTable((v) => !v)}>
            {asTable ? 'View as chart' : 'View as table'}
          </button>
        )}
      </div>
      {state.loading && !state.data && <Loading lines={4} />}
      <LoadError error={state.error} onRetry={state.reload} />
      {state.data && (asTable && tableView ? tableView(state.data) : children(state.data))}
    </section>
  )
}

export default function Overview() {
  const [periodIdx, setPeriodIdx] = useState(1)
  const [clinicId, setClinicId] = useState('')
  const period = PERIODS[periodIdx]
  const params = { from: londonDateMinus(period.days - 1), to: londonDateMinus(0), clinic_id: clinicId || undefined }

  const clinics = useApi('/clinics')
  const summary = useApi('/analytics/summary', params)
  const series = useApi('/analytics/timeseries', { ...params, interval: period.interval })
  const byTreatment = useApi('/analytics/by-treatment', params)
  const byClinic = useApi('/analytics/by-clinic', params)
  const shop = useApi('/shop/summary')

  const s = summary.data
  return (
    <>
      <div className="page-head">
        <h1>Overview</h1>
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
        <Kpi label="Total enquiries" value={s ? formatNumber(s.total) : '…'} />
        <Kpi label="New (awaiting contact)" value={s ? formatNumber(s.by_status.NEW) : '…'} />
        <Kpi label="Booked" value={s ? formatNumber(s.booked) : '…'} hint="Ever reached Booked" />
        <Kpi label="Conversion" value={s ? formatPercent(s.conversion_rate) : '…'} hint="Booked ÷ total" />
        <Kpi
          label="Median time to contact"
          value={s ? (s.median_hours_to_contact === null ? '—' : `${s.median_hours_to_contact} h`) : '…'}
        />
      </div>

      <div className="grid-2">
        <Panel
          title="Enquiries over time"
          state={series}
          tableView={(rows) => (
            <table className="data">
              <thead>
                <tr>
                  <th scope="col">Period starting</th>
                  <th scope="col" className="num">Total</th>
                  <th scope="col" className="num">Booked</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.period_start}>
                    <td>{formatShortDate(r.period_start)}</td>
                    <td className="num">{r.total}</td>
                    <td className="num">{r.booked}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        >
          {(rows) => (
            <div className="chart" role="img" aria-label="Line chart of total and booked enquiries over time">
              <ResponsiveContainer width="100%" height={260}>
                <LineChart data={rows} margin={{ top: 8, right: 16, left: 0, bottom: 0 }}>
                  <CartesianGrid stroke="#e3e8e9" vertical={false} />
                  <XAxis dataKey="period_start" tickFormatter={formatShortDate} fontSize={12} minTickGap={24} />
                  <YAxis allowDecimals={false} fontSize={12} width={36} />
                  <Tooltip labelFormatter={formatShortDate} />
                  <Legend />
                  <Line type="monotone" dataKey="total" name="Total" stroke="var(--chart-1)" strokeWidth={2} dot={false} />
                  <Line type="monotone" dataKey="booked" name="Booked" stroke="var(--chart-2)" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          )}
        </Panel>

        <Panel
          title="By treatment"
          state={byTreatment}
          tableView={(rows) => <GroupTable rows={rows} nameKey="treatment_name" label="Treatment" />}
        >
          {(rows) => (
            <div className="chart" role="img" aria-label="Bar chart of enquiries and bookings by treatment">
              <ResponsiveContainer width="100%" height={Math.max(220, rows.length * 28)}>
                <BarChart data={rows} layout="vertical" margin={{ top: 0, right: 16, left: 8, bottom: 0 }}>
                  <CartesianGrid stroke="#e3e8e9" horizontal={false} />
                  <XAxis type="number" allowDecimals={false} fontSize={12} />
                  <YAxis type="category" dataKey="treatment_name" width={150} fontSize={12} />
                  <Tooltip />
                  <Legend />
                  <Bar dataKey="total" name="Total" fill="var(--chart-1)" radius={[0, 4, 4, 0]} />
                  <Bar dataKey="booked" name="Booked" fill="var(--chart-2)" radius={[0, 4, 4, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          )}
        </Panel>
      </div>

      <div className="grid-2">
        <Panel title="By clinic" state={byClinic}>
          {(rows) => <GroupTable rows={rows} nameKey="clinic_name" label="Clinic" />}
        </Panel>
        <ShopPanel state={shop} />
      </div>
    </>
  )
}

function GroupTable({ rows, nameKey, label }) {
  if (!rows.length) return <p className="muted">No enquiries in this period.</p>
  return (
    <div className="table-scroll">
      <table className="data">
        <thead>
          <tr>
            <th scope="col">{label}</th>
            <th scope="col" className="num">Total</th>
            <th scope="col" className="num">Booked</th>
            <th scope="col" className="num">Conversion</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r[nameKey]}>
              <td>{r[nameKey]}</td>
              <td className="num">{formatNumber(r.total)}</td>
              <td className="num">{formatNumber(r.booked)}</td>
              <td className="num">{formatPercent(r.conversion_rate)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function ShopPanel({ state }) {
  return (
    <section className="card panel" aria-labelledby="shop-h">
      <h2 id="shop-h">Shop – last 30 days</h2>
      {state.loading && !state.data && <Loading lines={3} />}
      {state.error && (
        <p className="banner banner-warning" role="status">
          {state.error.code === 'SHOP_UNAVAILABLE'
            ? 'Shop data is unavailable right now. The rest of the dashboard is unaffected.'
            : state.error.message}
        </p>
      )}
      {state.data && (
        <>
          <div className="row shop-kpis">
            <Kpi label="Paid orders" value={formatNumber(state.data.orders_last_30_days)} />
            <Kpi label="Revenue" value={formatPence(state.data.revenue_last_30_days_pence)} />
          </div>
          {state.data.recent_orders.length ? (
            <table className="data">
              <caption className="visually-hidden">Recent orders</caption>
              <thead>
                <tr>
                  <th scope="col">Order</th>
                  <th scope="col">Status</th>
                  <th scope="col" className="num">Items</th>
                  <th scope="col" className="num">Total</th>
                  <th scope="col">Placed</th>
                </tr>
              </thead>
              <tbody>
                {state.data.recent_orders.map((o) => (
                  <tr key={o.number}>
                    <td>#{o.number}</td>
                    <td>{o.status}</td>
                    <td className="num">{o.item_count}</td>
                    <td className="num">{formatPence(o.total_pence)}</td>
                    <td>{formatDateTime(o.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="muted">No orders yet.</p>
          )}
        </>
      )}
    </section>
  )
}
