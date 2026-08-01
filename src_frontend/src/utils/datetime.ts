// The API sends and accepts `occurred_at` as a wall-clock string without an offset
// ("2026-07-26T14:30"), which is exactly the value format of <input type="datetime-local">.
// Keeping it as a plain string everywhere avoids a Date round-trip silently shifting the
// stored time by the browser's timezone offset.

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

/** Local (not UTC) "YYYY-MM-DDTHH:mm" for a datetime-local input default. */
export function toDateTimeInputValue(date: Date): string {
  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  )
}

/** "2026-07-26T14:30" -> "2026-07-26 14:30" for display. */
export function formatDateTime(value: string): string {
  return value.replace('T', ' ')
}

/**
 * Pipeline timestamps are full ISO-8601 WITH an offset (unlike `occurred_at`,
 * which is deliberately offset-free above), so a Date round-trip is correct here.
 */
export function formatRelativeFromIso(iso: string | null): string {
  if (iso === null) return '—'

  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000)
  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
  ]

  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) {
      return formatter.format(-Math.round(seconds / size), unit)
    }
  }

  return formatter.format(-seconds, 'second')
}

/** Elapsed step/run time as mm:ss, or h:mm:ss once it passes an hour. */
export function formatDurationMs(ms: number | null): string {
  if (ms === null) return '—'

  const total = Math.round(ms / 1000)
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor((total % 3600) / 60)
  const seconds = total % 60
  const pad2 = (value: number) => String(value).padStart(2, '0')

  return hours > 0
    ? `${hours}:${pad2(minutes)}:${pad2(seconds)}`
    : `${pad2(minutes)}:${pad2(seconds)}`
}
