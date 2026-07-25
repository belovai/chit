import { tokenAuthService } from './TokenAuthService'

export type { AuthService } from './AuthService'

// Default: Bearer token auth. To switch to cookie-based (Sanctum SPA) auth,
// see CookieAuthService.ts and swap this export.
export const authService = tokenAuthService
