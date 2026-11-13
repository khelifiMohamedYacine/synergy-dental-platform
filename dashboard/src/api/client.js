// API client. The access token is held in memory only (never localStorage) to limit XSS impact;
// the refresh token is an HttpOnly cookie the browser sends to /api/v1/auth only (ADR-05).

export const API_BASE = '/api/v1'

let accessToken = null
let refreshing = null
let onSessionExpired = () => {}

export class ApiError extends Error {
  constructor(status, body) {
    const err = body?.error ?? {}
    super(err.message || `Request failed (${status})`)
    this.status = status
    this.code = err.code || 'UNKNOWN'
    this.details = err.details || []
    this.requestId = body?.request_id
  }

  fieldErrors() {
    return Object.fromEntries(this.details.map((d) => [d.field, d.message]))
  }
}

export function setAccessToken(token) {
  accessToken = token
}

export function setSessionExpiredHandler(handler) {
  onSessionExpired = handler
}

async function parse(response) {
  if (response.status === 204) return null
  const type = response.headers.get('content-type') || ''
  if (type.includes('application/json')) return response.json()
  return response.text()
}

/** Exchange the refresh cookie for a new access token. Concurrent callers share one request. */
export function refreshSession() {
  if (!refreshing) {
    refreshing = fetch(`${API_BASE}/auth/refresh`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' },
    })
      .then(async (response) => {
        const body = await parse(response)
        if (!response.ok) throw new ApiError(response.status, body)
        setAccessToken(body.access_token)
        return body
      })
      .finally(() => {
        refreshing = null
      })
  }
  return refreshing
}

function buildUrl(path, params) {
  const url = new URL(`${API_BASE}${path}`, window.location.origin)
  for (const [key, value] of Object.entries(params || {})) {
    if (value === undefined || value === null || value === '') continue
    if (Array.isArray(value)) value.forEach((v) => url.searchParams.append(key, v))
    else url.searchParams.set(key, value)
  }
  return url.pathname + url.search
}

export async function api(path, { method = 'GET', params, body, form, retry = true } = {}) {
  const headers = { 'X-Requested-With': 'fetch' }
  if (accessToken) headers.Authorization = `Bearer ${accessToken}`
  let payload
  if (form) {
    payload = new URLSearchParams(form)
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }
  const response = await fetch(buildUrl(path, params), {
    method,
    headers,
    body: payload,
    credentials: 'same-origin',
  })
  const data = await parse(response)
  if (response.ok) return data

  const error = new ApiError(response.status, data)
  const expired = response.status === 401 && ['TOKEN_EXPIRED', 'NOT_AUTHENTICATED'].includes(error.code)
  if (expired && retry && accessToken && !path.startsWith('/auth/')) {
    try {
      await refreshSession()
    } catch {
      setAccessToken(null)
      onSessionExpired()
      throw error
    }
    return api(path, { method, params, body, form, retry: false })
  }
  throw error
}

export function downloadUrl(path, params) {
  return buildUrl(path, params)
}
