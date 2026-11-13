import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client.js'

/**
 * Load data from the API. Re-runs when the path/params change or `reload()` is called.
 *
 * `loading` is derived (the stored result belongs to an older request) rather than set inside
 * the effect, and responses for superseded requests are ignored, so fast filter changes cannot
 * race. While a new request is in flight the previous data stays visible.
 */
export function useApi(path, params, { enabled = true } = {}) {
  const key = JSON.stringify([path, params ?? null])
  const [version, setVersion] = useState(0)
  const [result, setResult] = useState({ requestKey: null, data: null, error: null })
  const requestKey = `${key}#${version}`

  useEffect(() => {
    if (!enabled) return undefined
    let active = true
    const [p, q] = JSON.parse(key)
    api(p, { params: q ?? undefined })
      .then((data) => active && setResult({ requestKey, data, error: null }))
      .catch((error) => active && setResult({ requestKey, data: null, error }))
    return () => {
      active = false
    }
  }, [key, requestKey, enabled])

  const loading = enabled && result.requestKey !== requestKey
  const reload = useCallback(() => setVersion((v) => v + 1), [])
  const setData = useCallback((data) => setResult((r) => ({ ...r, data })), [])
  return { data: result.data, error: loading ? null : result.error, loading, reload, setData }
}

export function useDebounced(value, delay = 300) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(t)
  }, [value, delay])
  return debounced
}
