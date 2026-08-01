import { onBeforeUnmount } from 'vue'

const POLL_INTERVAL_MS = 2500

/**
 * Polls only while something on screen is still moving. When `isActive()` goes
 * false the timer stops itself, so a page of finished runs costs nothing.
 *
 * Deliberately hits the same endpoint as the initial load, so swapping in
 * websockets later replaces this file and nothing else.
 */
export function useRunPolling(load: () => Promise<void>, isActive: () => boolean) {
  let timer: ReturnType<typeof setTimeout> | null = null

  function stop() {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  function schedule() {
    stop()
    if (!isActive()) return

    timer = setTimeout(async () => {
      try {
        await load()
      } finally {
        schedule()
      }
    }, POLL_INTERVAL_MS)
  }

  onBeforeUnmount(stop)

  return { start: schedule, stop }
}
