// A clean install has a single administrator and no staff accounts, so the tests create their
// own staff account through the API (and global-teardown.js deactivates it again).
import { randomBytes } from 'node:crypto'

export const E2E_STAFF_EMAIL = 'e2e.staff@example.test'

let token = null // one sign-in per process: the login endpoint is rate limited

async function adminToken(base) {
  if (token) return token
  if (!process.env.E2E_ADMIN_PASSWORD) throw new Error('Set E2E_ADMIN_PASSWORD')
  const login = await fetch(`${base}/api/v1/auth/login`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'fetch' },
    body: new URLSearchParams({
      username: process.env.E2E_ADMIN_EMAIL || 'admin@example.test',
      password: process.env.E2E_ADMIN_PASSWORD,
    }),
  })
  if (!login.ok) throw new Error(`Admin sign-in failed (${login.status})`)
  token = (await login.json()).access_token
  return token
}

export async function adminRequest(path, { method = 'GET', body } = {}) {
  const base = process.env.BASE_URL || 'http://localhost:8080'
  if (process.env.E2E_INSECURE_TLS) process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0'
  const response = await fetch(`${base}/api/v1${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${await adminToken(base)}`,
      'X-Requested-With': 'fetch',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  })
  if (!response.ok) throw new Error(`${method} ${path} failed (${response.status}): ${await response.text()}`)
  return response.status === 204 ? null : response.json()
}

export async function findStaff() {
  const { items } = await adminRequest('/users?page_size=100')
  return items.find((u) => u.email === E2E_STAFF_EMAIL)
}

export default async function globalSetup() {
  const password = `E2e-${randomBytes(12).toString('base64url')}`
  const existing = await findStaff()
  if (existing) {
    await adminRequest(`/users/${existing.id}`, { method: 'PATCH', body: { password, is_active: true } })
  } else {
    await adminRequest('/users', {
      method: 'POST',
      body: { email: E2E_STAFF_EMAIL, full_name: 'Sam Receptionist', role: 'STAFF', password },
    })
  }
  // Environment variables set here are inherited by the test workers.
  process.env.E2E_STAFF_PASSWORD = password
}
