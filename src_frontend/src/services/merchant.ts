import { apiRequest } from '@/services/http'
import type {
  Merchant,
  MerchantLocation,
  MerchantPayload,
  MerchantLocationPayload,
} from '@/types/merchant'

export const merchantService = {
  list(token: string) {
    return apiRequest<Merchant[]>('/api/merchants', { token })
  },

  get(token: string, hashId: string) {
    return apiRequest<Merchant>(`/api/merchants/${hashId}`, { token })
  },

  create(token: string, payload: MerchantPayload) {
    return apiRequest<Merchant>('/api/merchants', { method: 'POST', body: payload, token })
  },

  update(token: string, hashId: string, payload: MerchantPayload) {
    return apiRequest<Merchant>(`/api/merchants/${hashId}`, {
      method: 'PUT',
      body: payload,
      token,
    })
  },

  destroy(token: string, hashId: string) {
    return apiRequest<void>(`/api/merchants/${hashId}`, { method: 'DELETE', token })
  },

  listLocations(token: string, merchantHashId: string) {
    return apiRequest<MerchantLocation[]>(`/api/merchants/${merchantHashId}/locations`, { token })
  },

  createLocation(token: string, merchantHashId: string, payload: MerchantLocationPayload) {
    return apiRequest<MerchantLocation>(`/api/merchants/${merchantHashId}/locations`, {
      method: 'POST',
      body: payload,
      token,
    })
  },

  updateLocation(token: string, locationHashId: string, payload: MerchantLocationPayload) {
    return apiRequest<MerchantLocation>(`/api/merchant-locations/${locationHashId}`, {
      method: 'PUT',
      body: payload,
      token,
    })
  },

  destroyLocation(token: string, locationHashId: string) {
    return apiRequest<void>(`/api/merchant-locations/${locationHashId}`, {
      method: 'DELETE',
      token,
    })
  },
}
