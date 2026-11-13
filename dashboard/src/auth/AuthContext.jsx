import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { api, refreshSession, setAccessToken, setSessionExpiredHandler } from '../api/client.js'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [status, setStatus] = useState('loading') // loading | signed-in | signed-out

  useEffect(() => {
    setSessionExpiredHandler(() => {
      setUser(null)
      setStatus('signed-out')
    })
    // A page reload loses the in-memory token; the refresh cookie restores the session.
    refreshSession()
      .then((body) => {
        setUser(body.user)
        setStatus('signed-in')
      })
      .catch(() => setStatus('signed-out'))
  }, [])

  const login = useCallback(async (email, password) => {
    const body = await api('/auth/login', {
      method: 'POST',
      form: { username: email, password },
    })
    setAccessToken(body.access_token)
    setUser(body.user)
    setStatus('signed-in')
    return body.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST' })
    } finally {
      setAccessToken(null)
      setUser(null)
      setStatus('signed-out')
    }
  }, [])

  const value = useMemo(
    () => ({ user, status, login, logout, isAdmin: user?.role === 'ADMIN' }),
    [user, status, login, logout],
  )
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}
