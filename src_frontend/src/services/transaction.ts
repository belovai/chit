import { apiRequest, apiRequestPaginated, type PaginatedResult } from '@/services/http'
import type { Transaction, TransactionPayload } from '@/types/transaction'

export const transactionService = {
  list(token: string, page: number) {
    return apiRequestPaginated<Transaction>('/api/transactions', {
      token,
      query: { page },
    })
  },

  get(token: string, hashId: string) {
    return apiRequest<Transaction>(`/api/transactions/${hashId}`, { token })
  },

  create(token: string, payload: TransactionPayload) {
    return apiRequest<Transaction>('/api/transactions', { method: 'POST', body: payload, token })
  },

  update(token: string, hashId: string, payload: TransactionPayload) {
    return apiRequest<Transaction>(`/api/transactions/${hashId}`, {
      method: 'PUT',
      body: payload,
      token,
    })
  },

  destroy(token: string, hashId: string) {
    return apiRequest<void>(`/api/transactions/${hashId}`, { method: 'DELETE', token })
  },
}

export type { PaginatedResult }
