import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext.jsx'
import './layout.css'

// Simple 24px line icons (decorative: every link also has a text label).
const ICONS = {
  overview: 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z',
  enquiries: 'M4 5h16v11H8l-4 4V5Z',
  appointments: 'M5 6h14v14H5V6Zm0 4h14M9 4v4m6-4v4',
  clinics: 'M4 20V9l8-5 8 5v11h-5v-6H9v6H4Z',
  treatments:
    'M8.5 4C6 4 4.5 6 4.5 8.6c0 2.8 1.3 4.7 2 7.5.6 2.3 1.1 3.9 2.2 3.9 1.3 0 1.5-2.2 2-4.1.3-1.3.8-1.9 1.3-1.9s1 .6 1.3 1.9c.5 1.9.7 4.1 2 4.1 1.1 0 1.6-1.6 2.2-3.9.7-2.8 2-4.7 2-7.5C19.5 6 18 4 15.5 4c-1.8 0-2.7 1-3.5 1s-1.7-1-3.5-1Z',
  shop: 'M4 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.2a2 2 0 0 0 2-1.5L21 8H6.6M10 21h.01M17 21h.01',
  users:
    'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1m6.5-9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 19v-1a4 4 0 0 0-3-3.9M15.5 3.2a3.5 3.5 0 0 1 0 6.6',
}

const NAV = [
  { to: '/', label: 'Overview', icon: 'overview', end: true },
  { to: '/enquiries', label: 'Enquiries', icon: 'enquiries' },
  { to: '/appointments', label: 'Appointments', icon: 'appointments' },
  { to: '/clinics', label: 'Clinics', icon: 'clinics' },
  { to: '/treatments', label: 'Treatments', icon: 'treatments' },
  { to: '/shop', label: 'Shop', icon: 'shop', adminOnly: true },
  { to: '/users', label: 'Users', icon: 'users', adminOnly: true },
]

function Icon({ name }) {
  return (
    <svg className="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path
        d={ICONS[name]}
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function Brand({ className = '' }) {
  return (
    <span className={`wordmark ${className}`}>
      <svg className="logo-mark" viewBox="0 0 40 40" width="30" height="30" aria-hidden="true" focusable="false">
        <rect width="40" height="40" rx="12" fill="currentColor" />
        <path
          d="M13.5 11c-3 0-5 2.4-5 5.6 0 3.4 1.6 5.7 2.5 9.2.7 2.7 1.3 4.2 2.6 4.2 1.6 0 1.8-2.6 2.4-5 .4-1.6 1.1-2.4 2-2.4h4c.9 0 1.6.8 2 2.4.6 2.4.8 5 2.4 5 1.3 0 1.9-1.5 2.6-4.2.9-3.5 2.5-5.8 2.5-9.2 0-3.2-2-5.6-5-5.6-2.2 0-3.3 1.2-6.5 1.2S15.7 11 13.5 11Z"
          fill="#fff"
        />
      </svg>
      <span className="wordmark-text">
        Synergy Dental
        <span className="wordmark-sub">Practice dashboard</span>
      </span>
    </span>
  )
}

const initials = (name = '') =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('')

export default function Layout() {
  const { user, isAdmin, logout } = useAuth()
  const [open, setOpen] = useState(false)

  return (
    <div className="shell">
      <a className="skip-link" href="#main">
        Skip to content
      </a>
      <header className="topbar">
        <Brand />
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
        <Brand className="sidebar-wordmark" />
        <ul>
          {NAV.filter((item) => !item.adminOnly || isAdmin).map((item) => (
            <li key={item.to}>
              <NavLink to={item.to} end={item.end} onClick={() => setOpen(false)}>
                <Icon name={item.icon} />
                {item.label}
              </NavLink>
            </li>
          ))}
        </ul>
        <div className="sidebar-links">
          <a href="/" target="_blank" rel="noreferrer">
            View website<span className="visually-hidden"> (opens in a new tab)</span>
          </a>
          {isAdmin && (
            <a href="/wp-admin/" target="_blank" rel="noreferrer">
              Website admin<span className="visually-hidden"> (opens in a new tab)</span>
            </a>
          )}
        </div>
        <div className="sidebar-user">
          <span className="avatar" aria-hidden="true">
            {initials(user?.full_name)}
          </span>
          <div className="sidebar-user-text">
            <div className="strong">{user?.full_name}</div>
            <div className="small">{user?.role === 'ADMIN' ? 'Administrator' : 'Staff'}</div>
          </div>
          <button type="button" className="btn-link" onClick={logout}>
            Log out
          </button>
        </div>
      </nav>
      <main id="main" className="main" tabIndex={-1}>
        <Outlet />
      </main>
    </div>
  )
}
