import type { AuthResult, LoginPayload, RegisterPayload, RegistrationStatus } from '@/types/auth'

export interface AuthService {
  login(payload: LoginPayload): Promise<AuthResult>
  register(payload: RegisterPayload): Promise<AuthResult>
  registrationStatus(): Promise<RegistrationStatus>
  logout(token: string): Promise<void>
}
