import { ApiError } from '@/types/auth'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://chit.127.0.0.1.nip.io'

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  token?: string | null
  query?: Record<string, string | number>
}

interface ApiEnvelope<T> {
  message: string
  data: T
  status: number
}

interface PaginationMeta {
  current_page: number
  last_page: number
}

interface PaginatedEnvelope<T> extends ApiEnvelope<T[]> {
  meta: PaginationMeta
}

export interface PaginatedResult<T> {
  data: T[]
  currentPage: number
  lastPage: number
}

function buildUrl(path: string, query?: Record<string, string | number>): string {
  if (!query) {
    return `${API_BASE_URL}${path}`
  }
  const params = new URLSearchParams(
    Object.fromEntries(Object.entries(query).map(([key, value]) => [key, String(value)])),
  )
  return `${API_BASE_URL}${path}?${params.toString()}`
}

async function performRequest(path: string, options: RequestOptions): Promise<unknown> {
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

  const response = await fetch(buildUrl(path, options.query), {
    method: options.method ?? 'GET',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    // credentials: 'include', // needed only for the cookie-based alternative above
  })

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    const message =
      payload && typeof payload === 'object' && 'message' in payload
        ? (payload as { message: string }).message
        : response.statusText
    const errors =
      payload && typeof payload === 'object' && 'errors' in payload
        ? (payload as { errors?: Record<string, string[]> }).errors
        : undefined

    if (response.status === 401 && router.currentRoute.value.name !== 'login') {
      useAuthStore().clear()
      router.push({ name: 'login' })
    }

    throw new ApiError(message, response.status, errors)
  }

  return payload
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const payload = await performRequest(path, options)
  return (payload as ApiEnvelope<T>).data
}

// Binary artifact downloads: `<img src>` / `window.open` cannot attach the
// Authorization header this app's Bearer-token auth needs, so a protected
// binary route has to be fetched here and turned into an object URL instead.
export async function apiRequestBlob(
  path: string,
  options: Pick<RequestOptions, 'token' | 'query'> = {},
): Promise<Blob> {
  const headers: Record<string, string> = {}
  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`
  }

  const response = await fetch(buildUrl(path, options.query), { headers })

  if (!response.ok) {
    if (response.status === 401 && router.currentRoute.value.name !== 'login') {
      useAuthStore().clear()
      router.push({ name: 'login' })
    }
    throw new ApiError(response.statusText, response.status)
  }

  return response.blob()
}

// The backend's ApiResponses trait puts a paginated resource collection's items in `data`
// (flat array) and the pagination info in a sibling `meta` object — not nested under `data`
// (see app/Traits/ApiResponses.php: `resolved['data']` / `resolved['meta']` from Laravel's
// standard ResourceCollection::response() shape).
export async function apiRequestPaginated<T>(
  path: string,
  options: RequestOptions = {},
): Promise<PaginatedResult<T>> {
  const payload = await performRequest(path, options)
  const envelope = payload as PaginatedEnvelope<T>

  return {
    data: envelope.data,
    currentPage: envelope.meta.current_page,
    lastPage: envelope.meta.last_page,
  }
}
