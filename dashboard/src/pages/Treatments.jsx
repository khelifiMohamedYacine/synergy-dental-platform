import { useState } from 'react'
import { api } from '../api/client.js'
import { useAuth } from '../auth/AuthContext.jsx'
import { LoadError, Loading } from '../components/ui.jsx'
import { useApi } from '../hooks/useApi.js'
import { formatPence } from '../utils/format.js'
import './admin.css'

const EMPTY = { category_id: '', slug: '', name: '', summary: '', price: '', typical_duration_minutes: '' }

export default function Treatments() {
  const { isAdmin } = useAuth()
  const treatments = useApi('/treatments', isAdmin ? { include_inactive: true } : undefined)
  const categories = useApi('/treatment-categories')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [banner, setBanner] = useState(null)
  const categoryName = Object.fromEntries((categories.data || []).map((c) => [c.id, c.name]))

  function open(t) {
    setErrors({})
    setBanner(null)
    setForm(
      t === 'new'
        ? EMPTY
        : {
            category_id: t.category_id,
            slug: t.slug,
            name: t.name,
            summary: t.summary || '',
            price: t.price_from_pence === null ? '' : (t.price_from_pence / 100).toFixed(2),
            typical_duration_minutes: t.typical_duration_minutes ?? '',
          },
    )
    setEditing(t)
  }

  async function save(event) {
    event.preventDefault()
    const price = form.price === '' ? null : Math.round(Number(form.price) * 100)
    if (form.price !== '' && (Number.isNaN(price) || price < 0)) {
      setErrors({ price_from_pence: 'Enter a price in pounds, e.g. 295.00' })
      return
    }
    const body = {
      category_id: Number(form.category_id) || null,
      slug: form.slug,
      name: form.name,
      summary: form.summary || null,
      price_from_pence: price,
      typical_duration_minutes: form.typical_duration_minutes === '' ? null : Number(form.typical_duration_minutes),
    }
    try {
      if (editing === 'new') await api('/treatments', { method: 'POST', body })
      else await api(`/treatments/${editing.id}`, { method: 'PATCH', body })
      setEditing(null)
      setBanner({ type: 'success', text: 'Treatment saved.' })
      treatments.reload()
    } catch (err) {
      setErrors(err.fieldErrors?.() || {})
      setBanner({ type: 'error', text: err.message })
    }
  }

  async function toggleActive(t) {
    try {
      await api(`/treatments/${t.id}`, { method: 'PATCH', body: { is_active: !t.is_active } })
      treatments.reload()
    } catch (err) {
      setBanner({ type: 'error', text: err.message })
    }
  }

  const field = (name, label, props = {}, errorKey = name) => (
    <div className="field">
      <label htmlFor={`t-${name}`}>{label}</label>
      <input
        id={`t-${name}`}
        value={form[name]}
        onChange={(e) => setForm((f) => ({ ...f, [name]: e.target.value }))}
        aria-invalid={Boolean(errors[errorKey])}
        {...props}
      />
      {errors[errorKey] && <div className="field-error">{errors[errorKey]}</div>}
    </div>
  )

  return (
    <>
      <div className="page-head">
        <h1>Treatments</h1>
        {isAdmin && (
          <button type="button" className="btn" onClick={() => open('new')}>
            Add treatment
          </button>
        )}
      </div>
      {banner && (
        <p className={`banner banner-${banner.type}`} role={banner.type === 'error' ? 'alert' : 'status'}>
          {banner.text}
        </p>
      )}
      <LoadError error={treatments.error} onRetry={treatments.reload} />
      {treatments.loading && !treatments.data && <Loading lines={6} />}
      <div className={editing ? 'admin-split' : ''}>
        {treatments.data && (
          <div className="table-wrap">
            <table className="data responsive">
              <thead>
                <tr>
                  <th scope="col">Treatment</th>
                  <th scope="col">Category</th>
                  <th scope="col" className="num">From</th>
                  <th scope="col" className="num">Duration</th>
                  <th scope="col">Status</th>
                  {isAdmin && <th scope="col">Actions</th>}
                </tr>
              </thead>
              <tbody>
                {treatments.data.map((t) => (
                  <tr key={t.id}>
                    <td data-label="Treatment">
                      {t.name}
                      <div className="muted small">{t.summary}</div>
                    </td>
                    <td data-label="Category">{categoryName[t.category_id] ?? '—'}</td>
                    <td data-label="From" className="num">
                      {formatPence(t.price_from_pence)}
                    </td>
                    <td data-label="Duration" className="num">
                      {t.typical_duration_minutes ? `${t.typical_duration_minutes} min` : '—'}
                    </td>
                    <td data-label="Status">{t.is_active ? 'Active' : <span className="muted">Inactive</span>}</td>
                    {isAdmin && (
                      <td data-label="Actions">
                        <div className="row">
                          <button type="button" className="btn-link" onClick={() => open(t)}>
                            Edit<span className="visually-hidden"> {t.name}</span>
                          </button>
                          <button type="button" className="btn-link" onClick={() => toggleActive(t)}>
                            {t.is_active ? 'Deactivate' : 'Reactivate'}
                            <span className="visually-hidden"> {t.name}</span>
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
          <form className="card side-panel" onSubmit={save} noValidate aria-labelledby="t-form-h">
            <h2 id="t-form-h">{editing === 'new' ? 'Add treatment' : `Edit ${editing.name}`}</h2>
            <div className="field">
              <label htmlFor="t-category">Category</label>
              <select
                id="t-category"
                value={form.category_id}
                onChange={(e) => setForm((f) => ({ ...f, category_id: e.target.value }))}
                aria-invalid={Boolean(errors.category_id)}
              >
                <option value="">Choose…</option>
                {(categories.data || []).map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
              {errors.category_id && <div className="field-error">{errors.category_id}</div>}
            </div>
            {field('name', 'Name')}
            {field('slug', 'Slug (shared with the website)')}
            {field('summary', 'Summary (optional)', { maxLength: 300 })}
            {field('price', 'Price from (£)', { inputMode: 'decimal' }, 'price_from_pence')}
            {field('typical_duration_minutes', 'Typical duration (minutes)', { inputMode: 'numeric' })}
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
