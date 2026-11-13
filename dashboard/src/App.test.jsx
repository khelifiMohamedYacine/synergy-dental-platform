import { render, screen } from '@testing-library/react'
import { afterEach, expect, test, vi } from 'vitest'
import App from './App.jsx'

afterEach(() => vi.unstubAllGlobals())

test('reports a healthy API', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
  render(<App />)
  expect(await screen.findByText('API and database are reachable.')).toBeInTheDocument()
  expect(fetch).toHaveBeenCalledWith('/api/v1/health/ready')
})

test('reports an unreachable API', async () => {
  vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')))
  render(<App />)
  expect(await screen.findByText('API or database is not reachable.')).toBeInTheDocument()
})
