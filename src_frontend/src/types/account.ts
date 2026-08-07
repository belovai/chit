export interface UpdateAccountPayload {
  name?: string
  email?: string
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
}

export interface DeleteAccountPayload {
  current_password: string
}
