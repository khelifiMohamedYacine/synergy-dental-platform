import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, test, vi } from 'vitest'
import App from '../App.jsx'
import { api, setAccessToken, setSessionExpiredHandler } from '../api/client.js'
import { formatPence, formatPercent, timeAgo } from '../utils/format.js'
import { ADMIN, STAFF, json, mockApi, renderApp, session } from './utils.jsx'

const CLINICS = [{ id: 1, slug: 'riverside', name: 'Riverside Dental Studio', is_active: true }]
const TREATMENTS = [{ id: 7, slug: 'dental-implants', name: 'Dental implants', category_id: 1, is_active: true }]

function listItem(overrides = {}) {
  return {
    id: 'e1',
    reference: 'SD-7K3M9Q',
    enquiry_type: 'APPOINTMENT',
    status: 'NEW',
    full_name: 'Jamie Fictional',
    email: 'jamie@example.test',
    clinic: { id: 1, name: 'Riverside Dental Studio' },
    treatment: { id: 7, name: 'Dental implants' },
    preferred_date: '2026-11-24',
    preferred_time_window: 'MORNING',
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

function detail(overrides = {}) {
  return {
    ...listItem(),
    phone: '+447700900123',
    message: 'Interested in a consultation.',
    consent_at: '2026-11-13T10:15:00Z',
    close_reason: null,
    updated_at: '2026-11-13T10:15:00Z',
    history: [{ from_status: null, to_status: 'NEW', close_reason: null, changed_by: null, changed_at: '2026-11-13T10:15:00Z' }],
    notes: [],
    allowed_transitions: ['CONTACTED', 'CLOSED'],
    ...overrides,
  }
}

// ---------------------------------------------------------------- formatting
describe('format helpers', () => {
  test('percent, money and relative time', () => {
    expect(formatPercent(0.2838)).toBe('28.4%')
    expect(formatPercent(null)).toBe('—')
    expect(formatPence(29500)).toBe('£295.00')
    expect(timeAgo(new Date(Date.now() - 2 * 3600 * 1000).toISOString())).toBe('2 h ago')
  })
})

// ---------------------------------------------------------------- API client
describe('api client', () => {
  test('refreshes once on an expired token and retries the request', async () => {
    let calls = 0
    mockApi({
      'GET /api/v1/enquiries': () => {
        calls += 1
        return calls === 1 ? json(401, { error: { code: 'TOKEN_EXPIRED' } }) : json(200, { items: [] })
      },
      ...session(),
    })
    setAccessToken('expired-token')
    await expect(api('/enquiries')).resolves.toEqual({ items: [] })
    expect(calls).toBe(2)
  })

  test('reports the session as expired when the refresh also fails', async () => {
    const expired = vi.fn()
    setSessionExpiredHandler(expired)
    mockApi({
      'GET /api/v1/enquiries': json(401, { error: { code: 'TOKEN_EXPIRED' } }),
      'POST /api/v1/auth/refresh': json(401, { error: { code: 'NOT_AUTHENTICATED' } }),
    })
    setAccessToken('expired-token')
    await expect(api('/enquiries')).rejects.toMatchObject({ code: 'TOKEN_EXPIRED' })
    expect(expired).toHaveBeenCalledTimes(1)
  })

  test('surfaces structured errors with field details', async () => {
    mockApi({
      'POST /api/v1/users': json(422, {
        error: { code: 'VALIDATION_ERROR', message: 'Some fields are invalid.', details: [{ field: 'email', message: 'Bad' }] },
        request_id: 'abc',
      }),
    })
    const error = await api('/users', { method: 'POST', body: {} }).catch((e) => e)
    expect(error.code).toBe('VALIDATION_ERROR')
    expect(error.fieldErrors()).toEqual({ email: 'Bad' })
  })
})

// ---------------------------------------------------------------- auth & routing
describe('auth and routing', () => {
  test('unauthenticated users are sent to the login page', async () => {
    mockApi({ 'POST /api/v1/auth/refresh': json(401, { error: { code: 'NOT_AUTHENTICATED' } }) })
    renderApp(<App />, { route: '/enquiries' })
    expect(await screen.findByRole('heading', { name: 'Staff sign in' })).toBeInTheDocument()
  })

  test('wrong credentials show an error and keep the user on the form', async () => {
    mockApi({
      'POST /api/v1/auth/refresh': json(401, { error: { code: 'NOT_AUTHENTICATED' } }),
      'POST /api/v1/auth/login': json(401, { error: { code: 'INVALID_CREDENTIALS', message: 'x' } }),
    })
    const user = userEvent.setup()
    renderApp(<App />, { route: '/login' })
    await user.type(await screen.findByLabelText('Email'), 'staff@example.test')
    await user.type(screen.getByLabelText('Password'), 'wrong-password')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Email or password is incorrect.')
  })

  test('successful login sends the form data and opens the dashboard', async () => {
    const fetchMock = mockApi({
      'POST /api/v1/auth/refresh': json(401, { error: { code: 'NOT_AUTHENTICATED' } }),
      'POST /api/v1/auth/login': { access_token: 't', token_type: 'bearer', expires_in: 900, user: STAFF },
      'GET /api/v1/clinics': CLINICS,
      'GET /api/v1/treatments': TREATMENTS,
      'GET /api/v1/enquiries': { items: [], page: 1, page_size: 20, total: 0, pages: 1 },
    })
    const user = userEvent.setup()
    renderApp(<App />, { route: '/login' })
    await user.type(await screen.findByLabelText('Email'), 'staff@example.test')
    await user.type(screen.getByLabelText('Password'), 'correct-password')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))
    await screen.findByRole('navigation', { name: 'Main' })
    const loginCall = fetchMock.mock.calls.find(([url]) => String(url).includes('/auth/login'))
    expect(String(loginCall[1].body)).toBe('username=staff%40example.test&password=correct-password')
  })

  test('staff do not see admin navigation or the users page', async () => {
    mockApi({ ...session(STAFF), 'GET /api/v1/clinics': CLINICS })
    renderApp(<App />, { route: '/users' })
    const nav = await screen.findByRole('navigation', { name: 'Main' })
    expect(within(nav).queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Page not found' })).toBeInTheDocument()
  })

  test('admins see the users link', async () => {
    mockApi({ ...session(ADMIN), 'GET /api/v1/users': { items: [], page: 1, page_size: 20, total: 0, pages: 1 } })
    renderApp(<App />, { route: '/users' })
    const nav = await screen.findByRole('navigation', { name: 'Main' })
    expect(within(nav).getByRole('link', { name: 'Users' })).toBeInTheDocument()
  })
})

// ---------------------------------------------------------------- enquiry list
describe('enquiry list', () => {
  test('renders rows and sends URL filters to the API', async () => {
    const fetchMock = mockApi({
      ...session(),
      'GET /api/v1/clinics': CLINICS,
      'GET /api/v1/treatments': TREATMENTS,
      'GET /api/v1/enquiries': { items: [listItem()], page: 1, page_size: 20, total: 1, pages: 1 },
    })
    renderApp(<App />, { route: '/enquiries?status=NEW&sort=full_name' })
    const link = await screen.findByRole('link', { name: 'SD-7K3M9Q' })
    expect(link).toHaveAttribute('href', '/enquiries/e1')
    expect(screen.getByLabelText('New')).toBeChecked()
    const listCall = fetchMock.mock.calls.map(([u]) => String(u)).find((u) => u.startsWith('/api/v1/enquiries?'))
    expect(listCall).toContain('status=NEW')
    expect(listCall).toContain('sort=full_name')
    expect(screen.getByRole('columnheader', { name: /Name/ })).toHaveAttribute('aria-sort', 'ascending')
  })

  test('shows an empty state', async () => {
    mockApi({
      ...session(),
      'GET /api/v1/clinics': CLINICS,
      'GET /api/v1/treatments': TREATMENTS,
      'GET /api/v1/enquiries': { items: [], page: 1, page_size: 20, total: 0, pages: 1 },
    })
    renderApp(<App />, { route: '/enquiries' })
    expect(await screen.findByText('No enquiries match these filters.')).toBeInTheDocument()
  })
})

// ---------------------------------------------------------------- enquiry detail
describe('enquiry detail', () => {
  test('shows only the server-allowed actions and applies a transition', async () => {
    const patch = vi.fn((_url, init) => {
      expect(JSON.parse(init.body)).toEqual({ status: 'CONTACTED' })
      return json(200, detail({ status: 'CONTACTED', allowed_transitions: ['QUALIFIED', 'CLOSED'] }))
    })
    mockApi({ ...session(), 'GET /api/v1/enquiries/e1': detail(), 'PATCH /api/v1/enquiries/e1/status': patch })
    const user = userEvent.setup()
    renderApp(<App />, { route: '/enquiries/e1' })
    await screen.findByRole('heading', { name: 'SD-7K3M9Q' })
    expect(screen.queryByRole('button', { name: 'Mark as booked' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Mark as contacted' }))
    expect(await screen.findByText('Status changed to Contacted.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Mark as qualified' })).toBeInTheDocument()
    expect(patch).toHaveBeenCalledTimes(1)
  })

  test('closing requires a reason', async () => {
    const patch = vi.fn((_url, init) => {
      expect(JSON.parse(init.body)).toEqual({ status: 'CLOSED', close_reason: 'UNREACHABLE' })
      return json(200, detail({ status: 'CLOSED', close_reason: 'UNREACHABLE', allowed_transitions: [] }))
    })
    mockApi({ ...session(), 'GET /api/v1/enquiries/e1': detail(), 'PATCH /api/v1/enquiries/e1/status': patch })
    const user = userEvent.setup()
    renderApp(<App />, { route: '/enquiries/e1' })
    await user.click(await screen.findByRole('button', { name: 'Close…' }))
    await user.click(screen.getByRole('button', { name: 'Close enquiry' }))
    expect(screen.getByText('Choose a reason.')).toBeInTheDocument()
    expect(patch).not.toHaveBeenCalled()
    await user.selectOptions(screen.getByLabelText('Reason (required)'), 'UNREACHABLE')
    await user.click(screen.getByRole('button', { name: 'Close enquiry' }))
    await waitFor(() => expect(patch).toHaveBeenCalledTimes(1))
    expect(await screen.findByText(/This enquiry is closed/)).toBeInTheDocument()
  })

  test('delete is only offered to admins', async () => {
    mockApi({ ...session(STAFF), 'GET /api/v1/enquiries/e1': detail() })
    renderApp(<App />, { route: '/enquiries/e1' })
    await screen.findByRole('heading', { name: 'SD-7K3M9Q' })
    expect(screen.queryByRole('button', { name: 'Delete enquiry' })).not.toBeInTheDocument()
  })
})
