import { apiRequest } from '@/services/http'
import type { AuthResult, LoginPayload, RegisterPayload } from '@/types/auth'
import type { AuthService } from './AuthService'

/**
 * Bearer-token auth (Laravel Sanctum personal access tokens). Token is
 * returned in the login/register response body and must be sent back as
 * `Authorization: Bearer <token>` on every subsequent request.
 */
export const tokenAuthService: AuthService = {
  login(payload: LoginPayload) {
    return apiRequest<AuthResult>('/api/auth/login', { method: 'POST', body: payload })
  },

  register(payload: RegisterPayload) {
    return apiRequest<AuthResult>('/api/auth/register', { method: 'POST', body: payload })
  },

  logout(token: string) {
    return apiRequest<void>('/api/auth/logout', { method: 'POST', token })
  },
}
