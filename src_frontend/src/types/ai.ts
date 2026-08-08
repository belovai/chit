export type AiCapability = 'vision' | 'json_schema' | 'prompt_cache'
export type AiCredentialStatus = 'pending' | 'verified' | 'failing' | 'disabled'
export type AiSettingType = 'int' | 'enum' | 'bool'

export interface AiModelPricing {
  input: number
  output: number
  cached_input: number
}

export interface AiModelDescriptor {
  id: string
  label: string
  capabilities: AiCapability[]
  pricing: AiModelPricing
}

/** One provider setting, as declared by the provider's `settingsSchema()`. */
export interface AiSettingField {
  key: string
  type: AiSettingType
  default: unknown
  required: boolean
  /** `int` fields only. */
  min?: number
  /** `int` fields only. */
  max?: number
  /** `enum` fields only. */
  options?: string[]
}

export interface AiProvider {
  id: string
  label: string
  models: AiModelDescriptor[]
  settings: AiSettingField[]
}

export interface AiCredential {
  /** Hash id. The API field is `id`, not `hash_id`. */
  id: string
  provider: string
  label: string
  model: string
  settings: Record<string, unknown>
  is_active: boolean
  status: AiCredentialStatus
  /** e.g. `••••1234`. The key itself is never returned. */
  masked_key: string
  last_verified_at: string | null
  last_used_at: string | null
  last_error: string | null
}

export interface AiCredentialPayload {
  provider: string
  label: string
  api_key: string
  model: string
  settings: Record<string, unknown>
}
