import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Served behind Caddy at /dashboard/ so the dashboard and API share one origin (ADR-05).
export default defineConfig({
  base: '/dashboard/',
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    // File events from a Windows bind mount do not reach the container; poll there instead.
    watch: process.env.VITE_POLL ? { usePolling: true, interval: 300 } : undefined,
    // When running Vite on the host (outside Docker), forward API calls to the local API.
    proxy: { '/api': process.env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000' },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.js'],
  },
})
