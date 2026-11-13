import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import { AuthProvider } from '../auth/AuthContext.jsx'

export const STAFF = { id: 1, email: 'staff@example.test', full_name: 'Sam Staff', role: 'STAFF' }
export const ADMIN = { id: 2, email: 'admin@example.test', full_name: 'Alex Admin', role: 'ADMIN' }

export function json(status, body) {
  return new Response(body === null ? null : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

/**
 * Stub fetch with a route table: { 'GET /api/v1/clinics': body | (url, init) => Response }.
 * Unknown routes return 404. Returns the vi.fn so tests can inspect calls.
 */
export function mockApi(routes) {
  const fn = vi.fn(async (input, init = {}) => {
    const url = new URL(input, 'http://localhost')
    const method = (init.method || 'GET').toUpperCase()
    const handler = routes[`${method} ${url.pathname}`]
    if (handler === undefined) return json(404, { error: { code: 'NOT_FOUND', message: 'Not found' } })
    if (typeof handler === 'function') return handler(url, init)
    if (handler instanceof Response) return handler
    return json(200, handler)
  })
  vi.stubGlobal('fetch', fn)
  return fn
}

/** Routes for a signed-in session (the refresh on page load succeeds). */
export function session(user = STAFF) {
  return { 'POST /api/v1/auth/refresh': { access_token: 'token', token_type: 'bearer', expires_in: 900, user } }
}

export function renderApp(ui, { route = '/' } = {}) {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <AuthProvider>{ui}</AuthProvider>
    </MemoryRouter>,
  )
}
