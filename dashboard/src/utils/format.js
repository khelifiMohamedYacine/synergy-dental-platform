// Formatting helpers. The API speaks UTC; staff see Europe/London time.
const TZ = 'Europe/London'

const dateTimeFmt = new Intl.DateTimeFormat('en-GB', {
  timeZone: TZ,
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})
const dateFmt = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'UTC', // plain dates (YYYY-MM-DD) carry no time zone
  weekday: 'short',
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})
const shortDateFmt = new Intl.DateTimeFormat('en-GB', { timeZone: 'UTC', day: 'numeric', month: 'short' })
const money = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' })
const number = new Intl.NumberFormat('en-GB')

export const formatDateTime = (iso) => (iso ? dateTimeFmt.format(new Date(iso)) : '—')
export const formatDate = (isoDate) => (isoDate ? dateFmt.format(new Date(`${isoDate}T00:00:00Z`)) : '—')
export const formatShortDate = (isoDate) => shortDateFmt.format(new Date(`${isoDate}T00:00:00Z`))
export const formatPence = (pence) => (pence === null || pence === undefined ? '—' : money.format(pence / 100))
export const formatNumber = (n) => (n === null || n === undefined ? '—' : number.format(n))
export const formatPercent = (fraction) =>
  fraction === null || fraction === undefined ? '—' : `${(fraction * 100).toFixed(1)}%`

export function timeAgo(iso, now = Date.now()) {
  const seconds = Math.round((now - new Date(iso).getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes} min ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours} h ago`
  const days = Math.round(hours / 24)
  if (days < 30) return `${days} d ago`
  return formatDateTime(iso)
}

export const STATUS_LABELS = {
  NEW: 'New',
  CONTACTED: 'Contacted',
  QUALIFIED: 'Qualified',
  BOOKED: 'Booked',
  CLOSED: 'Closed',
}

export const CLOSE_REASON_LABELS = {
  TREATED: 'Treated',
  NOT_INTERESTED: 'Not interested',
  UNREACHABLE: 'Unreachable',
  DUPLICATE: 'Duplicate',
  OTHER: 'Other',
}

export const TIME_WINDOW_LABELS = { MORNING: 'morning', AFTERNOON: 'afternoon', EVENING: 'evening' }

/** YYYY-MM-DD for "today minus n days" in London. */
export function londonDateMinus(days) {
  const now = new Date(Date.now() - days * 86400000)
  return new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(now)
}
