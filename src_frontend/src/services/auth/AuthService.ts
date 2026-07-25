import type { AuthResult, LoginPayload, RegisterPayload } from '@/types/auth'

export interface AuthService {
  login(payload: LoginPayload): Promise<AuthResult>
  register(payload: RegisterPayload): Promise<AuthResult>
  logout(token: string): Promise<void>
}
