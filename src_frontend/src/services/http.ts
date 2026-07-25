import { ApiError } from '@/types/auth'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://chit.127.0.0.1.nip.io'

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  token?: string | null
}

interface ApiEnvelope<T> {
  message: string
  data: T
  status: number
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }

  // Token-based auth (default): send the Sanctum personal access token as a Bearer header.
  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`
  }

  // Cookie-based auth (alternative, not active): drop the Authorization header above and
  // instead set `credentials: 'include'` below, plus fetch `/sanctum/csrf-cookie` once on
  // app boot and mirror the XSRF-TOKEN cookie into an X-XSRF-TOKEN header per request.

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    // credentials: 'include', // needed only for the cookie-based alternative above
  })

  const payload = (await response.json().catch(() => null)) as
    | ApiEnvelope<T>
    | { message: string; errors?: Record<string, string[]> }
    | null

  if (!response.ok) {
    const message = payload?.message ?? response.statusText
    const errors = payload && 'errors' in payload ? payload.errors : undefined
    throw new ApiError(message, response.status, errors)
  }

  return (payload as ApiEnvelope<T>).data
}
