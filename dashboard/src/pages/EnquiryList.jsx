import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, downloadUrl } from '../api/client.js'
import { LoadError, Loading, Pagination, StatusBadge } from '../components/ui.jsx'
import { useApi, useDebounced } from '../hooks/useApi.js'
import { STATUS_LABELS, TIME_WINDOW_LABELS, formatDate, timeAgo } from '../utils/format.js'
import './enquiries.css'

const STATUSES = Object.keys(STATUS_LABELS)
const PAGE_SIZE = 20

function SortHeader({ field, label, sort, onSort }) {
  const active = sort === field || sort === `-${field}`
  const descending = sort === `-${field}`
  const ariaSort = active ? (descending ? 'descending' : 'ascending') : 'none'
  return (
    <th scope="col" aria-sort={ariaSort}>
      <button
        type="button"
        className="sort-btn"
        onClick={() => onSort(active && !descending ? `-${field}` : field)}
      >
        {label}
        <span aria-hidden="true">{active ? (descending ? ' ▼' : ' ▲') : ' ↕'}</span>
      </button>
    </th>
  )
}

/** Enquiry list. `appointmentsOnly` gives the Appointments view (same resource, type filter). */
export default function EnquiryList({ appointmentsOnly = false }) {
  const [searchParams, setSearchParams] = useSearchParams()
  const get = (k) => searchParams.get(k) || ''
  const defaultSort = appointmentsOnly ? 'preferred_date' : '-created_at'
  const sort = get('sort') || defaultSort
  const page = Number(get('page')) || 1
  const statuses = searchParams.getAll('status')

  const [query, setQuery] = useState(get('q'))
  const debouncedQuery = useDebounced(query, 300)

  function update(changes, { resetPage = true } = {}) {
    const next = new URLSearchParams(searchParams)
    for (const [k, v] of Object.entries(changes)) {
      next.delete(k)
      if (Array.isArray(v)) v.forEach((x) => next.append(k, x))
      else if (v !== '' && v !== null && v !== undefined) next.set(k, v)
    }
    if (resetPage && !('page' in changes)) next.delete('page')
    setSearchParams(next)
  }

  useEffect(() => {
    if (debouncedQuery !== (searchParams.get('q') || '')) update({ q: debouncedQuery })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedQuery])

  const params = {
    q: get('q'),
    status: statuses,
    enquiry_type: appointmentsOnly ? 'APPOINTMENT' : get('enquiry_type'),
    clinic_id: get('clinic_id'),
    treatment_id: get('treatment_id'),
    created_from: get('created_from'),
    created_to: get('created_to'),
    sort,
    page,
    page_size: PAGE_SIZE,
  }
  const list = useApi('/enquiries', params)
  const clinics = useApi('/clinics')
  const treatments = useApi('/treatments')

  const hasFilters = ['q', 'status', 'enquiry_type', 'clinic_id', 'treatment_id', 'created_from', 'created_to'].some(
    (k) => searchParams.has(k),
  )
  const title = appointmentsOnly ? 'Appointment requests' : 'Enquiries'
  const { page: _p, page_size: _s, ...exportParams } = params

  return (
    <>
      <div className="page-head">
        <h1>{title}</h1>
        <a className="btn btn-secondary" href={downloadUrl('/enquiries/export.csv', exportParams)} onClick={exportCsv}>
          Export CSV
        </a>
      </div>

      <form className="filters-bar" role="search" onSubmit={(e) => e.preventDefault()}>
        <div className="filter-search">
          <label htmlFor="q">Search</label>
          <input
            id="q"
            type="search"
            placeholder="Name, email or SD- reference"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
        <fieldset className="filter-statuses">
          <legend>Status</legend>
          <div className="row">
            {STATUSES.map((s) => (
              <label key={s} className="chip">
                <input
                  type="checkbox"
                  checked={statuses.includes(s)}
                  onChange={(e) =>
                    update({ status: e.target.checked ? [...statuses, s] : statuses.filter((x) => x !== s) })
                  }
                />
                {STATUS_LABELS[s]}
              </label>
            ))}
          </div>
        </fieldset>
        <div>
          <label htmlFor="clinic_id">Clinic</label>
          <select id="clinic_id" value={get('clinic_id')} onChange={(e) => update({ clinic_id: e.target.value })}>
            <option value="">All</option>
            {(clinics.data || []).map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="treatment_id">Treatment</label>
          <select
            id="treatment_id"
            value={get('treatment_id')}
            onChange={(e) => update({ treatment_id: e.target.value })}
          >
            <option value="">All</option>
            {(treatments.data || []).map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </select>
        </div>
        {!appointmentsOnly && (
          <div>
            <label htmlFor="enquiry_type">Type</label>
            <select
              id="enquiry_type"
              value={get('enquiry_type')}
              onChange={(e) => update({ enquiry_type: e.target.value })}
            >
              <option value="">All</option>
              <option value="GENERAL">General</option>
              <option value="APPOINTMENT">Appointment</option>
            </select>
          </div>
        )}
        <div>
          <label htmlFor="created_from">Received from</label>
          <input
            id="created_from"
            type="date"
            value={get('created_from')}
            onChange={(e) => update({ created_from: e.target.value })}
          />
        </div>
        <div>
          <label htmlFor="created_to">Received to</label>
          <input
            id="created_to"
            type="date"
            value={get('created_to')}
            onChange={(e) => update({ created_to: e.target.value })}
          />
        </div>
        {hasFilters && (
          <div className="filter-clear">
            <button
              type="button"
              className="btn btn-secondary"
              onClick={() => {
                setQuery('')
                setSearchParams(new URLSearchParams(searchParams.get('sort') ? { sort } : {}))
              }}
            >
              Clear filters
            </button>
          </div>
        )}
      </form>

      <LoadError error={list.error} onRetry={list.reload} />
      {list.loading && !list.data && <Loading lines={8} />}
      {list.data && (
        <div aria-busy={list.loading}>
          <p className="visually-hidden" role="status">
            {list.data.total} enquiries found
          </p>
          {list.data.items.length === 0 ? (
            <div className="card empty">
              <p>No enquiries match these filters.</p>
            </div>
          ) : (
            <div className="table-wrap">
              <table className="data responsive">
                <caption className="visually-hidden">{title}</caption>
                <thead>
                  <tr>
                    <th scope="col">Reference</th>
                    <SortHeader field="full_name" label="Name" sort={sort} onSort={(s) => update({ sort: s })} />
                    <th scope="col">Treatment</th>
                    <th scope="col">Clinic</th>
                    <SortHeader field="status" label="Status" sort={sort} onSort={(s) => update({ sort: s })} />
                    {appointmentsOnly ? (
                      <SortHeader
                        field="preferred_date"
                        label="Preferred date"
                        sort={sort}
                        onSort={(s) => update({ sort: s })}
                      />
                    ) : (
                      <SortHeader field="created_at" label="Received" sort={sort} onSort={(s) => update({ sort: s })} />
                    )}
                  </tr>
                </thead>
                <tbody>
                  {list.data.items.map((e) => (
                    <tr key={e.id}>
                      <td data-label="Reference">
                        <Link className="mono" to={`/enquiries/${e.id}`}>
                          {e.reference}
                        </Link>
                      </td>
                      <td data-label="Name">
                        {e.full_name}
                        <div className="muted small">{e.email}</div>
                      </td>
                      <td data-label="Treatment">{e.treatment?.name ?? <span className="muted">Not sure yet</span>}</td>
                      <td data-label="Clinic">{e.clinic.name}</td>
                      <td data-label="Status">
                        <StatusBadge status={e.status} />
                      </td>
                      {appointmentsOnly ? (
                        <td data-label="Preferred date">
                          {formatDate(e.preferred_date)}
                          {e.preferred_time_window && (
                            <div className="muted small">{TIME_WINDOW_LABELS[e.preferred_time_window]}</div>
                          )}
                        </td>
                      ) : (
                        <td data-label="Received">
                          <time dateTime={e.created_at} title={new Date(e.created_at).toLocaleString('en-GB')}>
                            {timeAgo(e.created_at)}
                          </time>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <Pagination
            page={list.data.page}
            pages={list.data.pages}
            total={list.data.total}
            pageSize={PAGE_SIZE}
            onChange={(p) => update({ page: p }, { resetPage: false })}
          />
        </div>
      )}
    </>
  )
}

// The CSV endpoint needs the bearer token, so fetch it and save the blob instead of navigating.
async function exportCsv(event) {
  event.preventDefault()
  const href = event.currentTarget.getAttribute('href')
  const text = await api(href.replace('/api/v1', ''))
  const blob = new Blob([text], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'enquiries.csv'
  a.click()
  URL.revokeObjectURL(url)
}
