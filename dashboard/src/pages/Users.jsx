import { useState } from 'react'
import { api } from '../api/client.js'
import { useAuth } from '../auth/AuthContext.jsx'
import { LoadError, Loading, Pagination } from '../components/ui.jsx'
import { useApi } from '../hooks/useApi.js'
import { formatDateTime } from '../utils/format.js'
import './admin.css'

const EMPTY = { email: '', full_name: '', role: 'STAFF', password: '' }

export default function Users() {
  const { user: me } = useAuth()
  const [page, setPage] = useState(1)
  const users = useApi('/users', { page, page_size: 20 })
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const [banner, setBanner] = useState(null)

  async function create(event) {
    event.preventDefault()
    setErrors({})
    try {
      await api('/users', { method: 'POST', body: form })
      setForm(null)
      setBanner({ type: 'success', text: 'User created.' })
      users.reload()
    } catch (err) {
      setErrors(err.fieldErrors?.() || {})
      setBanner({ type: 'error', text: err.message })
    }
  }

  async function update(user, body, message) {
    try {
      await api(`/users/${user.id}`, { method: 'PATCH', body })
      setBanner({ type: 'success', text: message })
      users.reload()
    } catch (err) {
      setBanner({ type: 'error', text: err.message })
    }
  }

  return (
    <>
      <div className="page-head">
        <h1>Users</h1>
        <button type="button" className="btn" onClick={() => setForm(EMPTY)}>
          Add user
        </button>
      </div>
      {banner && (
        <p className={`banner banner-${banner.type}`} role={banner.type === 'error' ? 'alert' : 'status'}>
          {banner.text}
        </p>
      )}
      <LoadError error={users.error} onRetry={users.reload} />
      {users.loading && !users.data && <Loading lines={4} />}
      <div className={form ? 'admin-split' : ''}>
        {users.data && (
          <div>
            <div className="table-wrap">
              <table className="data responsive">
                <thead>
                  <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Role</th>
                    <th scope="col">Last sign-in</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {users.data.items.map((u) => (
                    <tr key={u.id}>
                      <td data-label="Name">
                        {u.full_name}
                        <div className="muted small">{u.email}</div>
                      </td>
                      <td data-label="Role">{u.role === 'ADMIN' ? 'Administrator' : 'Staff'}</td>
                      <td data-label="Last sign-in">{formatDateTime(u.last_login_at)}</td>
                      <td data-label="Status">{u.is_active ? 'Active' : <span className="muted">Disabled</span>}</td>
                      <td data-label="Actions">
                        {u.id === me.id ? (
                          <span className="muted">You</span>
                        ) : (
                          <div className="row">
                            <button
                              type="button"
                              className="btn-link"
                              onClick={() =>
                                update(u, { role: u.role === 'ADMIN' ? 'STAFF' : 'ADMIN' }, 'Role updated.')
                              }
                            >
                              {u.role === 'ADMIN' ? 'Make staff' : 'Make admin'}
                              <span className="visually-hidden"> {u.full_name}</span>
                            </button>
                            <button
                              type="button"
                              className="btn-link"
                              onClick={() =>
                                update(u, { is_active: !u.is_active }, u.is_active ? 'User disabled.' : 'User enabled.')
                              }
                            >
                              {u.is_active ? 'Disable' : 'Enable'}
                              <span className="visually-hidden"> {u.full_name}</span>
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination
              page={users.data.page}
              pages={users.data.pages}
              total={users.data.total}
              pageSize={20}
              onChange={setPage}
            />
          </div>
        )}
        {form && (
          <form className="card side-panel" onSubmit={create} noValidate aria-labelledby="u-form-h">
            <h2 id="u-form-h">Add user</h2>
            {[
              ['full_name', 'Full name', 'text', 'name'],
              ['email', 'Email', 'email', 'off'],
              ['password', 'Temporary password (12+ characters)', 'password', 'new-password'],
            ].map(([name, label, type, autoComplete]) => (
              <div className="field" key={name}>
                <label htmlFor={`u-${name}`}>{label}</label>
                <input
                  id={`u-${name}`}
                  type={type}
                  autoComplete={autoComplete}
                  value={form[name]}
                  aria-invalid={Boolean(errors[name])}
                  onChange={(e) => setForm((f) => ({ ...f, [name]: e.target.value }))}
                />
                {errors[name] && <div className="field-error">{errors[name]}</div>}
              </div>
            ))}
            <div className="field">
              <label htmlFor="u-role">Role</label>
              <select id="u-role" value={form.role} onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))}>
                <option value="STAFF">Staff</option>
                <option value="ADMIN">Administrator</option>
              </select>
            </div>
            <div className="row">
              <button type="submit" className="btn">
                Create user
              </button>
              <button type="button" className="btn btn-secondary" onClick={() => setForm(null)}>
                Cancel
              </button>
            </div>
          </form>
        )}
      </div>
    </>
  )
}
