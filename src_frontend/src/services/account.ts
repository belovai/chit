import { apiRequest } from '@/services/http'
import type {
  ChangePasswordPayload,
  DeleteAccountPayload,
  UpdateAccountPayload,
} from '@/types/account'
import type { User } from '@/types/auth'

export const accountService = {
  get(token: string) {
    return apiRequest<User>('/api/account', { token })
  },

  update(token: string, payload: UpdateAccountPayload) {
    return apiRequest<User>('/api/account', { method: 'PATCH', body: payload, token })
  },

  changePassword(token: string, payload: ChangePasswordPayload) {
    return apiRequest<void>('/api/account/password', { method: 'PUT', body: payload, token })
  },

  destroy(token: string, payload: DeleteAccountPayload) {
    return apiRequest<void>('/api/account', { method: 'DELETE', body: payload, token })
  },
}
