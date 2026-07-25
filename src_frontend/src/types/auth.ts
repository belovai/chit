export interface User {
  hash_id: string
  name: string
  email: string
  role: string
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
}

export interface AuthResult {
  token: string
  user: User
}

export interface ApiValidationError {
  message: string
  errors: Record<string, string[]>
}

export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]> | undefined

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}
