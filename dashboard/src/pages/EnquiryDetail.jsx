import { useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client.js'
import { useAuth } from '../auth/AuthContext.jsx'
import { LoadError, Loading, StatusBadge } from '../components/ui.jsx'
import { useApi } from '../hooks/useApi.js'
import {
  CLOSE_REASON_LABELS,
  STATUS_LABELS,
  TIME_WINDOW_LABELS,
  formatDate,
  formatDateTime,
} from '../utils/format.js'
import './enquiries.css'

const ACTION_LABELS = {
  CONTACTED: 'Mark as contacted',
  QUALIFIED: 'Mark as qualified',
  BOOKED: 'Mark as booked',
  CLOSED: 'Close…',
}

export default function EnquiryDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { isAdmin } = useAuth()
  const enquiry = useApi(`/enquiries/${id}`)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState(null)
  const [flash, setFlash] = useState('')
  const closeDialog = useRef(null)
  const deleteDialog = useRef(null)

  async function changeStatus(body) {
    setBusy(true)
    setActionError(null)
    try {
      // No optimistic update: the server's response is the source of truth (doc ui-design §4.4).
      const updated = await api(`/enquiries/${id}/status`, { method: 'PATCH', body })
      enquiry.setData(updated)
      setFlash(`Status changed to ${STATUS_LABELS[updated.status]}.`)
      return true
    } catch (err) {
      setActionError(err)
      if (err.code === 'INVALID_TRANSITION') enquiry.reload()
      return false
    } finally {
      setBusy(false)
    }
  }

  async function onDelete() {
    setBusy(true)
    try {
      await api(`/enquiries/${id}`, { method: 'DELETE' })
      navigate('/enquiries', { replace: true })
    } catch (err) {
      setActionError(err)
      setBusy(false)
    }
  }

  if (enquiry.loading && !enquiry.data) return <Loading lines={10} />
  if (enquiry.error && !enquiry.data) {
    return (
      <>
        <Link to="/enquiries">‹ Back to enquiries</Link>
        <LoadError
          error={enquiry.error.status === 404 ? { message: 'This enquiry does not exist or was deleted.' } : enquiry.error}
        />
      </>
    )
  }
  const e = enquiry.data
  if (!e) return null

  return (
    <>
      <Link to="/enquiries">‹ Back to enquiries</Link>
      <div className="page-head" style={{ marginTop: 12 }}>
        <div className="row">
          <h1 className="mono">{e.reference}</h1>
          <StatusBadge status={e.status} />
          {e.close_reason && <span className="muted">({CLOSE_REASON_LABELS[e.close_reason]})</span>}
        </div>
        <span className="muted">{e.enquiry_type === 'APPOINTMENT' ? 'Appointment request' : 'General enquiry'}</span>
      </div>

      <p role="status" className={flash ? 'banner banner-success' : 'visually-hidden'}>
        {flash}
      </p>
      <LoadError error={actionError} />

      <div className="detail-grid">
        <section className="card" aria-labelledby="contact-h">
          <h2 id="contact-h">Contact</h2>
          <dl className="kv">
            <dt>Name</dt>
            <dd>{e.full_name}</dd>
            <dt>Email</dt>
            <dd>
              <a href={`mailto:${e.email}`}>{e.email}</a>
            </dd>
            <dt>Phone</dt>
            <dd>
              <a href={`tel:${e.phone}`}>{e.phone}</a>
            </dd>
            <dt>Clinic</dt>
            <dd>{e.clinic.name}</dd>
            <dt>Treatment</dt>
            <dd>{e.treatment?.name ?? 'Not sure yet'}</dd>
            {e.preferred_date && (
              <>
                <dt>Preferred</dt>
                <dd>
                  {formatDate(e.preferred_date)}
                  {e.preferred_time_window && `, ${TIME_WINDOW_LABELS[e.preferred_time_window]}`}
                </dd>
              </>
            )}
            <dt>Message</dt>
            <dd>{e.message ? <q>{e.message}</q> : <span className="muted">None</span>}</dd>
            <dt>Received</dt>
            <dd>{formatDateTime(e.created_at)}</dd>
            <dt>Consent</dt>
            <dd>Given {formatDateTime(e.consent_at)}</dd>
          </dl>
          {isAdmin && (
            <div style={{ marginTop: 24 }}>
              <button type="button" className="btn btn-danger" onClick={() => deleteDialog.current.showModal()}>
                Delete enquiry
              </button>
            </div>
          )}
        </section>

        <div className="stack">
          <section className="card" aria-labelledby="status-h">
            <h2 id="status-h">Status</h2>
            {e.allowed_transitions.length === 0 ? (
              <p className="muted">This enquiry is closed. A returning patient creates a new enquiry.</p>
            ) : (
              <div className="row">
                {e.allowed_transitions.map((s) =>
                  s === 'CLOSED' ? (
                    <button
                      key={s}
                      type="button"
                      className="btn btn-secondary"
                      disabled={busy}
                      onClick={() => closeDialog.current.showModal()}
                    >
                      {ACTION_LABELS[s]}
                    </button>
                  ) : (
                    <button
                      key={s}
                      type="button"
                      className="btn"
                      disabled={busy}
                      onClick={() => changeStatus({ status: s })}
                    >
                      {e.status === 'BOOKED' && s === 'QUALIFIED' ? 'Appointment cancelled (back to qualified)' : ACTION_LABELS[s]}
                    </button>
                  ),
                )}
              </div>
            )}
            <h3 className="visually-hidden">Timeline</h3>
            <ol className="timeline" style={{ marginTop: 20 }} aria-label="Status history">
              {e.history.map((h, i) => (
                <li key={i}>
                  <StatusBadge status={h.to_status} />{' '}
                  {h.close_reason && <span className="muted">({CLOSE_REASON_LABELS[h.close_reason]}) </span>}
                  <span className="muted">
                    {formatDateTime(h.changed_at)} · {h.changed_by?.full_name ?? 'Website form'}
                  </span>
                </li>
              ))}
            </ol>
          </section>

          <Notes enquiryId={id} notes={e.notes} onAdded={(note) => enquiry.setData({ ...e, notes: [...e.notes, note] })} />
        </div>
      </div>

      <CloseDialog dialogRef={closeDialog} busy={busy} onConfirm={changeStatus} />

      <dialog ref={deleteDialog} aria-labelledby="delete-h">
        <h2 id="delete-h">Delete this enquiry?</h2>
        <p>
          This permanently removes {e.reference}, its history and notes. Use it only for a data-erasure request. It
          cannot be undone.
        </p>
        <div className="row" style={{ justifyContent: 'flex-end' }}>
          <button type="button" className="btn btn-secondary" onClick={() => deleteDialog.current.close()}>
            Cancel
          </button>
          <button type="button" className="btn btn-danger" disabled={busy} onClick={onDelete}>
            Delete permanently
          </button>
        </div>
      </dialog>
    </>
  )
}

function CloseDialog({ dialogRef, busy, onConfirm }) {
  const [reason, setReason] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState('')

  async function submit(event) {
    event.preventDefault()
    if (!reason) {
      setError('Choose a reason.')
      return
    }
    const ok = await onConfirm({ status: 'CLOSED', close_reason: reason, note: note.trim() || undefined })
    if (ok) {
      setReason('')
      setNote('')
      setError('')
      dialogRef.current.close()
    }
  }

  return (
    <dialog ref={dialogRef} aria-labelledby="close-h">
      <form onSubmit={submit} noValidate>
        <h2 id="close-h">Close enquiry</h2>
        <div className="field" style={{ marginTop: 16 }}>
          <label htmlFor="close-reason">Reason (required)</label>
          <select
            id="close-reason"
            value={reason}
            aria-invalid={Boolean(error)}
            aria-describedby={error ? 'close-reason-error' : undefined}
            onChange={(ev) => {
              setReason(ev.target.value)
              setError('')
            }}
          >
            <option value="">Choose…</option>
            {Object.entries(CLOSE_REASON_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          {error && (
            <div id="close-reason-error" className="field-error">
              {error}
            </div>
          )}
        </div>
        <div className="field">
          <label htmlFor="close-note">Note (optional)</label>
          <textarea id="close-note" maxLength={2000} value={note} onChange={(ev) => setNote(ev.target.value)} />
        </div>
        <div className="row" style={{ justifyContent: 'flex-end' }}>
          <button type="button" className="btn btn-secondary" onClick={() => dialogRef.current.close()}>
            Cancel
          </button>
          <button type="submit" className="btn" disabled={busy}>
            Close enquiry
          </button>
        </div>
      </form>
    </dialog>
  )
}

function Notes({ enquiryId, notes, onAdded }) {
  const [body, setBody] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  async function add(event) {
    event.preventDefault()
    if (!body.trim()) return
    setSaving(true)
    setError(null)
    try {
      const note = await api(`/enquiries/${enquiryId}/notes`, { method: 'POST', body: { body } })
      onAdded(note)
      setBody('')
    } catch (err) {
      setError(err)
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="card" aria-labelledby="notes-h">
      <h2 id="notes-h">Internal notes</h2>
      <form onSubmit={add}>
        <label htmlFor="note-body">Add a note</label>
        <textarea
          id="note-body"
          maxLength={2000}
          value={body}
          aria-describedby="note-hint"
          onChange={(e) => setBody(e.target.value)}
        />
        <div id="note-hint" className="field-hint">
          Do not record clinical information here. {body.length}/2000
        </div>
        <LoadError error={error} />
        <button type="submit" className="btn" style={{ marginTop: 8 }} disabled={saving || !body.trim()}>
          {saving ? 'Saving…' : 'Add note'}
        </button>
      </form>
      {notes.length > 0 && (
        <ul className="notes">
          {notes.map((n) => (
            <li key={n.id}>
              <div className="muted small">
                {n.author.full_name} · {formatDateTime(n.created_at)}
              </div>
              {n.body}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
