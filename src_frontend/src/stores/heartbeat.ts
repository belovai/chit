import { defineStore } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { heartbeatService } from '@/services/heartbeat'

const POLL_INTERVAL_MS = 30_000

interface HeartbeatState {
  receiptsNeedsReview: number
  intervalId: ReturnType<typeof setInterval> | null
}

export const useHeartbeatStore = defineStore('heartbeat', {
  state: (): HeartbeatState => ({
    receiptsNeedsReview: 0,
    intervalId: null,
  }),

  actions: {
    start() {
      if (this.intervalId !== null) return
      this.poll()
      this.intervalId = setInterval(() => this.poll(), POLL_INTERVAL_MS)
    },

    stop() {
      if (this.intervalId !== null) clearInterval(this.intervalId)
      this.intervalId = null
      this.receiptsNeedsReview = 0
    },

    async poll() {
      const authStore = useAuthStore()
      if (!authStore.token) return

      try {
        const result = await heartbeatService.get(authStore.token)
        this.receiptsNeedsReview = result.receipts_needs_review
      } catch {
        // A 401 here already triggers a global logout/redirect in http.ts —
        // any other failure just leaves the last known count in place.
      }
    },
  },
})
