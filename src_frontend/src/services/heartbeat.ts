import { apiRequest } from '@/services/http'

export interface HeartbeatResult {
  receipts_needs_review: number
}

export const heartbeatService = {
  get(token: string) {
    return apiRequest<HeartbeatResult>('/api/auth/heartbeat', { token })
  },
}
