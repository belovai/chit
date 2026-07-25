import { defineStore } from 'pinia'
import { authService } from '@/services/auth'
import { ApiError, type LoginPayload, type RegisterPayload, type User } from '@/types/auth'

const TOKEN_STORAGE_KEY = 'chit.auth.token'
const USER_STORAGE_KEY = 'chit.auth.user'

function readStoredUser(): User | null {
  const raw = localStorage.getItem(USER_STORAGE_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw) as User
  } catch {
    return null
  }
}

interface AuthState {
  token: string | null
  user: User | null
  isLoading: boolean
  fieldErrors: Record<string, string[]>
  generalError: string | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem(TOKEN_STORAGE_KEY),
    user: readStoredUser(),
    isLoading: false,
    fieldErrors: {},
    generalError: null,
  }),

  getters: {
    isAuthenticated: (state) => state.token !== null,
  },

  actions: {
    async login(payload: LoginPayload) {
      this.resetErrors()
      this.isLoading = true
      try {
        const result = await authService.login(payload)
        this.persist(result.token, result.user)
      } catch (error) {
        this.handleError(error)
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async register(payload: RegisterPayload) {
      this.resetErrors()
      this.isLoading = true
      try {
        const result = await authService.register(payload)
        this.persist(result.token, result.user)
      } catch (error) {
        this.handleError(error)
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      if (this.token) {
        try {
          await authService.logout(this.token)
        } catch {
          // Token may already be invalid/expired server-side — clear local state regardless.
        }
      }
      this.clear()
    },

    persist(token: string, user: User) {
      this.token = token
      this.user = user
      localStorage.setItem(TOKEN_STORAGE_KEY, token)
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user))
    },

    clear() {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_STORAGE_KEY)
      localStorage.removeItem(USER_STORAGE_KEY)
    },

    resetErrors() {
      this.fieldErrors = {}
      this.generalError = null
    },

    handleError(error: unknown) {
      if (error instanceof ApiError) {
        this.fieldErrors = error.errors ?? {}
        this.generalError = error.errors ? null : error.message
        return
      }
      this.generalError = 'network.connection_failed'
    },
  },
})
