import { apiRequest, apiRequestPaginated, type PaginatedResult } from '@/services/http'
import type { Product, ProductPayload } from '@/types/product'
import type { ProductMatch } from '@/types/transaction'

export const productService = {
  list(token: string, page: number) {
    return apiRequestPaginated<Product>('/api/products', { token, query: { page } })
  },

  get(token: string, hashId: string) {
    return apiRequest<Product>(`/api/products/${hashId}`, { token })
  },

  create(token: string, payload: ProductPayload) {
    return apiRequest<Product>('/api/products', { method: 'POST', body: payload, token })
  },

  update(token: string, hashId: string, payload: ProductPayload) {
    return apiRequest<Product>(`/api/products/${hashId}`, { method: 'PUT', body: payload, token })
  },

  destroy(token: string, hashId: string) {
    return apiRequest<void>(`/api/products/${hashId}`, { method: 'DELETE', token })
  },

  suggest(token: string, query: string) {
    return apiRequest<ProductMatch[]>('/api/products/suggest', { token, query: { query } })
  },
}

export type { PaginatedResult }
