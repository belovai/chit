type Translator = (key: string, params?: Record<string, unknown>) => string

/**
 * Resolves a backend/frontend error code to display text.
 * A code containing a dot (e.g. `auth.invalid_credentials`) is a namespaced,
 * self-contained translation key. A bare code (e.g. `required`) is a generic
 * validation rule and needs the field name interpolated in.
 */
export function translateErrorCode(t: Translator, code: string, field?: string): string {
  if (code.includes('.')) {
    return t(code)
  }
  return t(`validation.${code}`, { field: field ? t(`fields.${field}`) : '' })
}
