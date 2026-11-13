import { STATUS_LABELS } from '../utils/format.js'

export function StatusBadge({ status }) {
  return <span className={`badge badge-${status}`}>{STATUS_LABELS[status] ?? status}</span>
}

export function LoadError({ error, onRetry }) {
  if (!error) return null
  const message =
    error.code === 'FORBIDDEN'
      ? 'You do not have permission to view this.'
      : error.message || 'Something went wrong.'
  return (
    <div className="banner banner-error" role="alert">
      {message}
      {error.requestId && <span className="muted"> (ref {error.requestId.slice(0, 8)})</span>}
      {onRetry && (
        <>
          {' '}
          <button type="button" className="btn-link" onClick={onRetry}>
            Try again
          </button>
        </>
      )}
    </div>
  )
}

export function Skeleton({ lines = 3, height = 16 }) {
  return (
    <div className="stack" aria-hidden="true">
      {Array.from({ length: lines }, (_, i) => (
        <div key={i} className="skeleton" style={{ height }} />
      ))}
    </div>
  )
}

export function Loading({ label = 'Loading…', lines = 3 }) {
  return (
    <div role="status">
      <span className="visually-hidden">{label}</span>
      <Skeleton lines={lines} />
    </div>
  )
}

function pageList(page, pages) {
  const wanted = new Set([1, pages, page - 1, page, page + 1])
  const list = [...wanted].filter((p) => p >= 1 && p <= pages).sort((a, b) => a - b)
  const out = []
  list.forEach((p, i) => {
    if (i > 0 && p - list[i - 1] > 1) out.push('…')
    out.push(p)
  })
  return out
}

export function Pagination({ page, pages, total, pageSize, onChange }) {
  if (!total) return null
  const from = (page - 1) * pageSize + 1
  const to = Math.min(page * pageSize, total)
  return (
    <nav className="row pagination" aria-label="Pagination" style={{ justifyContent: 'space-between', marginTop: 16 }}>
      <span className="muted">
        Showing {from}–{to} of {total.toLocaleString('en-GB')}
      </span>
      <span className="row">
        <button type="button" className="btn btn-secondary" disabled={page <= 1} onClick={() => onChange(page - 1)}>
          ‹ Prev
        </button>
        {pageList(page, pages).map((p, i) =>
          p === '…' ? (
            <span key={`gap-${i}`} aria-hidden="true">
              …
            </span>
          ) : (
            <button
              key={p}
              type="button"
              className={`btn ${p === page ? '' : 'btn-secondary'}`}
              aria-current={p === page ? 'page' : undefined}
              aria-label={`Page ${p}`}
              onClick={() => onChange(p)}
            >
              {p}
            </button>
          ),
        )}
        <button
          type="button"
          className="btn btn-secondary"
          disabled={page >= pages}
          onClick={() => onChange(page + 1)}
        >
          Next ›
        </button>
      </span>
    </nav>
  )
}
