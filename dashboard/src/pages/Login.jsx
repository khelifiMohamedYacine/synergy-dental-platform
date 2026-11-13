import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext.jsx'
import './login.css'

const MESSAGES = {
  INVALID_CREDENTIALS: 'Email or password is incorrect.',
  ACCOUNT_DISABLED: 'This account has been disabled. Contact an administrator.',
  RATE_LIMITED: 'Too many attempts. Please wait a minute and try again.',
}

export default function Login() {
  const { status, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  if (status === 'signed-in') return <Navigate to={location.state?.from || '/'} replace />

  async function onSubmit(event) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await login(email, password)
      navigate(location.state?.from || '/', { replace: true })
    } catch (err) {
      setError(MESSAGES[err.code] || 'Sign-in failed. Please try again.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="login-page">
      <form className="card login-card" onSubmit={onSubmit} noValidate>
        <p className="wordmark">
          Synergy Dental <span className="wordmark-tag">demo</span>
        </p>
        <h1>Staff sign in</h1>
        {error && (
          <div className="banner banner-error" role="alert">
            {error}
          </div>
        )}
        <div className="field">
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            autoComplete="username"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </div>
        <div className="field">
          <label htmlFor="password">Password</label>
          <div className="password-row">
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
            <button
              type="button"
              className="btn btn-secondary"
              aria-pressed={showPassword}
              onClick={() => setShowPassword((s) => !s)}
            >
              {showPassword ? 'Hide' : 'Show'}
            </button>
          </div>
        </div>
        <button type="submit" className="btn" disabled={submitting || !email || !password}>
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>
        <p className="muted login-note">
          Demo system with fictional data. Demo credentials are listed in the project README.
        </p>
      </form>
    </main>
  )
}
