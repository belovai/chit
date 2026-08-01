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
