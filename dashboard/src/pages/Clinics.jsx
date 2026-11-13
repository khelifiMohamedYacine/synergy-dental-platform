import { useState } from 'react'
import { api } from '../api/client.js'
import { useAuth } from '../auth/AuthContext.jsx'
import { LoadError, Loading } from '../components/ui.jsx'
import { useApi } from '../hooks/useApi.js'
import './admin.css'

const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']
const EMPTY = {
  slug: '',
  name: '',
  address_line1: '',
  address_line2: '',
  city: '',
  postcode: '',
  phone: '',
  email: '',
  hours: {},
  treatment_ids: [],
}

function toForm(clinic) {
  const hours = {}
  for (const d of DAYS) {
    const r = clinic.opening_hours?.[d]?.[0]
    hours[d] = r ? `${r[0]}-${r[1]}` : ''
  }
  return { ...EMPTY, ...clinic, address_line2: clinic.address_line2 || '', email: clinic.email || '', hours }
}

function toPayload(form) {
  const opening_hours = {}
  for (const d of DAYS) {
    const v = (form.hours[d] || '').trim()
    if (v) opening_hours[d] = [v.split('-').map((x) => x.trim())]
  }
  const { hours: _h, id: _i, is_active: _a, ...rest } = form
  return { ...rest, address_line2: rest.address_line2 || null, email: rest.email || null, opening_hours }
}

export default function Clinics() {
  const { isAdmin } = useAuth()
  const clinics = useApi('/clinics', isAdmin ? { include_inactive: true } : undefined)
  const treatments = useApi('/treatments')
  const [editing, setEditing] = useState(null) // null | 'new' | clinic
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [banner, setBanner] = useState(null)

  async function open(clinic) {
    setErrors({})
    setBanner(null)
    if (clinic === 'new') {
      setForm({ ...EMPTY, hours: Object.fromEntries(DAYS.map((d) => [d, ''])) })
    } else {
      const detail = await api(`/clinics/${clinic.id}`)
      setForm(toForm(detail))
    }
    setEditing(clinic)
  }

  async function save(event) {
    event.preventDefault()
    setErrors({})
    try {
      const body = toPayload(form)
      if (editing === 'new') await api('/clinics', { method: 'POST', body })
      else await api(`/clinics/${editing.id}`, { method: 'PATCH', body })
      setEditing(null)
      setBanner({ type: 'success', text: 'Clinic saved.' })
      clinics.reload()
    } catch (err) {
      setErrors(err.fieldErrors?.() || {})
      setBanner({ type: 'error', text: err.message })
    }
  }

  async function toggleActive(clinic) {
    try {
      await api(`/clinics/${clinic.id}`, { method: 'PATCH', body: { is_active: !clinic.is_active } })
      clinics.reload()
    } catch (err) {
      setBanner({ type: 'error', text: err.message })
    }
  }

  const set = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  return (
    <>
      <div className="page-head">
        <h1>Clinics</h1>
        {isAdmin && (
          <button type="button" className="btn" onClick={() => open('new')}>
            Add clinic
          </button>
        )}
      </div>
      {banner && (
        <p className={`banner banner-${banner.type}`} role={banner.type === 'error' ? 'alert' : 'status'}>
          {banner.text}
        </p>
      )}
      <LoadError error={clinics.error} onRetry={clinics.reload} />
      {clinics.loading && !clinics.data && <Loading lines={5} />}
      <div className={editing ? 'admin-split' : ''}>
        {clinics.data && (
          <div className="table-wrap">
            <table className="data responsive">
              <thead>
                <tr>
                  <th scope="col">Name</th>
                  <th scope="col">Address</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Status</th>
                  {isAdmin && <th scope="col">Actions</th>}
                </tr>
              </thead>
              <tbody>
                {clinics.data.map((c) => (
                  <tr key={c.id}>
                    <td data-label="Name">
                      {c.name}
                      <div className="muted small mono">{c.slug}</div>
                    </td>
                    <td data-label="Address">
                      {c.address_line1}
                      {c.address_line2 && `, ${c.address_line2}`}, {c.city} {c.postcode}
                    </td>
                    <td data-label="Phone">{c.phone}</td>
                    <td data-label="Status">{c.is_active ? 'Active' : <span className="muted">Inactive</span>}</td>
                    {isAdmin && (
                      <td data-label="Actions">
                        <div className="row">
                          <button type="button" className="btn-link" onClick={() => open(c)}>
                            Edit<span className="visually-hidden"> {c.name}</span>
                          </button>
                          <button type="button" className="btn-link" onClick={() => toggleActive(c)}>
                            {c.is_active ? 'Deactivate' : 'Reactivate'}
                            <span className="visually-hidden"> {c.name}</span>
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {editing && (
          <form className="card side-panel" onSubmit={save} aria-labelledby="clinic-form-h" noValidate>
            <h2 id="clinic-form-h">{editing === 'new' ? 'Add clinic' : `Edit ${editing.name}`}</h2>
            {[
              ['name', 'Name'],
              ['slug', 'Slug (shared with the website)'],
              ['address_line1', 'Address line 1'],
              ['address_line2', 'Address line 2 (optional)'],
              ['city', 'City'],
              ['postcode', 'Postcode'],
              ['phone', 'Phone'],
              ['email', 'Email (optional)'],
            ].map(([field, label]) => (
              <div className="field" key={field}>
                <label htmlFor={`c-${field}`}>{label}</label>
                <input
                  id={`c-${field}`}
                  value={form[field]}
                  onChange={set(field)}
                  aria-invalid={Boolean(errors[field])}
                  aria-describedby={errors[field] ? `c-${field}-err` : undefined}
                />
                {errors[field] && (
                  <div id={`c-${field}-err`} className="field-error">
                    {errors[field]}
                  </div>
                )}
              </div>
            ))}
            <fieldset className="field">
              <legend>Opening hours (HH:MM-HH:MM, blank = closed)</legend>
              <div className="hours-grid">
                {DAYS.map((d) => (
                  <label key={d}>
                    <span className="hours-day">{d}</span>
                    <input
                      value={form.hours[d] || ''}
                      placeholder="closed"
                      onChange={(e) => setForm((f) => ({ ...f, hours: { ...f.hours, [d]: e.target.value } }))}
                    />
                  </label>
                ))}
              </div>
              {errors.opening_hours && <div className="field-error">{errors.opening_hours}</div>}
            </fieldset>
            <fieldset className="field">
              <legend>Treatments offered</legend>
              <div className="check-list">
                {(treatments.data || []).map((t) => (
                  <label key={t.id}>
                    <input
                      type="checkbox"
                      checked={form.treatment_ids.includes(t.id)}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          treatment_ids: e.target.checked
                            ? [...f.treatment_ids, t.id]
                            : f.treatment_ids.filter((x) => x !== t.id),
                        }))
                      }
                    />{' '}
                    {t.name}
                  </label>
                ))}
              </div>
            </fieldset>
            <div className="row">
              <button type="submit" className="btn">
                Save
              </button>
              <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>
                Cancel
              </button>
            </div>
          </form>
        )}
      </div>
    </>
  )
}
