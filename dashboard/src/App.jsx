import { useEffect, useState } from 'react'

const STATUS_TEXT = {
  checking: 'Checking API…',
  ok: 'API and database are reachable.',
  down: 'API or database is not reachable.',
}

export default function App() {
  const [status, setStatus] = useState('checking')

  useEffect(() => {
    let active = true
    fetch('/api/v1/health/ready')
      .then((res) => active && setStatus(res.ok ? 'ok' : 'down'))
      .catch(() => active && setStatus('down'))
    return () => {
      active = false
    }
  }, [])

  return (
    <main>
      <h1>Synergy Dental – Staff Dashboard</h1>
      <p>Skeleton build. Fictional demo data only.</p>
      <p role="status">{STATUS_TEXT[status]}</p>
    </main>
  )
}
