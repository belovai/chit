import { apiRequest } from '@/services/http'
import type { AiCredential, AiCredentialPayload, AiProvider } from '@/types/ai'

export const aiService = {
  providers(token: string) {
    return apiRequest<AiProvider[]>('/api/ai/providers', { token })
  },

  list(token: string) {
    return apiRequest<AiCredential[]>('/api/ai/credentials', { token })
  },

  create(token: string, payload: AiCredentialPayload) {
    return apiRequest<AiCredential>('/api/ai/credentials', {
      method: 'POST',
      body: payload,
      token,
    })
  },

  update(token: string, id: string, payload: Partial<AiCredentialPayload>) {
    return apiRequest<AiCredential>(`/api/ai/credentials/${id}`, {
      method: 'PATCH',
      body: payload,
      token,
    })
  },

  activate(token: string, id: string) {
    return apiRequest<AiCredential>(`/api/ai/credentials/${id}/activate`, {
      method: 'POST',
      token,
    })
  },

  verify(token: string, id: string) {
    return apiRequest<AiCredential>(`/api/ai/credentials/${id}/verify`, {
      method: 'POST',
      token,
    })
  },

  destroy(token: string, id: string) {
    return apiRequest<void>(`/api/ai/credentials/${id}`, { method: 'DELETE', token })
  },
}
