export {}

// Cookie-based auth alternative (Laravel Sanctum SPA cookie mode) — NOT active.
//
// To switch to this mode:
//   1. In services/http.ts, add `credentials: 'include'` to the fetch() call and
//      drop the Authorization header logic.
//   2. Call GET /sanctum/csrf-cookie once before the first mutating request (e.g.
//      in main.ts on boot) to obtain the XSRF-TOKEN cookie.
//   3. Read the XSRF-TOKEN cookie value and send it back as `X-XSRF-TOKEN` on every
//      request (Laravel reads it from there for CSRF verification).
//   4. The backend's `SANCTUM_STATEFUL_DOMAINS` must list the SPA's origin, and the
//      SPA + API must share a parent domain for cookies to be sent cross-origin.
//   5. Swap the export in services/auth/index.ts from tokenAuthService to
//      cookieAuthService below.
//
// import { apiRequest } from '@/services/http'
// import type { AuthResult, LoginPayload, RegisterPayload } from '@/types/auth'
// import type { AuthService } from './AuthService'
//
// export const cookieAuthService: AuthService = {
//   login(payload: LoginPayload) {
//     return apiRequest<AuthResult>('/api/auth/login', { method: 'POST', body: payload })
//   },
//
//   register(payload: RegisterPayload) {
//     return apiRequest<AuthResult>('/api/auth/register', { method: 'POST', body: payload })
//   },
//
//   logout() {
//     return apiRequest<void>('/api/auth/logout', { method: 'POST' })
//   },
// }
