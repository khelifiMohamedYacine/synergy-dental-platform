import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext.jsx'
import './layout.css'

const NAV = [
  { to: '/', label: 'Overview', end: true },
  { to: '/enquiries', label: 'Enquiries' },
  { to: '/appointments', label: 'Appointments' },
  { to: '/clinics', label: 'Clinics' },
  { to: '/treatments', label: 'Treatments' },
  { to: '/users', label: 'Users', adminOnly: true },
]

export default function Layout() {
  const { user, isAdmin, logout } = useAuth()
  const [open, setOpen] = useState(false)

  return (
    <div className="shell">
      <a className="skip-link" href="#main">
        Skip to content
      </a>
      <header className="topbar">
        <span className="wordmark">
          Synergy Dental <span className="wordmark-tag">demo</span>
        </span>
        <button
          type="button"
          className="btn btn-secondary menu-btn"
          aria-expanded={open}
          aria-controls="sidebar"
          onClick={() => setOpen((o) => !o)}
        >
          Menu
        </button>
      </header>
      <nav id="sidebar" className={`sidebar ${open ? 'is-open' : ''}`} aria-label="Main">
        <span className="wordmark sidebar-wordmark">
          Synergy Dental <span className="wordmark-tag">demo</span>
        </span>
        <ul>
          {NAV.filter((item) => !item.adminOnly || isAdmin).map((item) => (
            <li key={item.to}>
              <NavLink to={item.to} end={item.end} onClick={() => setOpen(false)}>
                {item.label}
              </NavLink>
            </li>
          ))}
        </ul>
        <div className="sidebar-user">
          <div>{user?.full_name}</div>
          <div className="muted">{user?.role === 'ADMIN' ? 'Administrator' : 'Staff'}</div>
          <button type="button" className="btn-link" onClick={logout}>
            Log out
          </button>
        </div>
      </nav>
      <main id="main" className="main" tabIndex={-1}>
        <p className="banner banner-warning demo-note">
          Demonstration system with fictional data only. Not affiliated with Synergy Dental Group.
        </p>
        <Outlet />
      </main>
    </div>
  )
}
