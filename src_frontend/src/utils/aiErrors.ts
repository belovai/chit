import { translateErrorCode } from '@/utils/errors'

type Translator = (key: string, params?: Record<string, unknown>) => string

/**
 * A backend validation entry is either a machine code (`required`,
 * `validation.max`, `ai.key_rejected`) or a prose sentence written by a
 * provider rule ("The provider rejected this API key."). Codes are
 * lowercase, dot- or underscore-separated, and contain no spaces; anything
 * else is prose and is shown as-is.
 */
const CODE_PATTERN = /^[a-z0-9_]+(\.[a-z0-9_]+)*$/

export function translateApiMessage(t: Translator, message: string, field?: string): string {
  if (!CODE_PATTERN.test(message)) {
    return message
  }
  return translateErrorCode(t, message, field)
}
