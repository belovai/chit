import { apiRequest, apiRequestPaginated } from '@/services/http'
import { ApiError } from '@/types/auth'
import type { DocType, Receipt, ReceiptDetail, ReviewPayload } from '@/types/receipt'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://chit.127.0.0.1.nip.io'

export const receiptService = {
  list(token: string, params: { page?: number; status?: string } = {}) {
    const query: Record<string, string | number> = {}
    if (params.page !== undefined) query.page = params.page
    if (params.status) query.status = params.status

    return apiRequestPaginated<Receipt>('/api/receipts', { token, query })
  },

  get(token: string, hashId: string) {
    return apiRequest<ReceiptDetail>(`/api/receipts/${hashId}`, { token })
  },

  review(token: string, hashId: string, payload: ReviewPayload) {
    return apiRequest<ReceiptDetail>(`/api/receipts/${hashId}/review`, {
      method: 'POST',
      body: payload,
      token,
    })
  },

  // Multipart cannot go through apiRequest — that helper JSON-encodes the body
  // and sets a JSON content type, which would corrupt the upload.
  async upload(token: string, file: File, hint?: DocType): Promise<ReceiptDetail> {
    const form = new FormData()
    form.append('file', file)
    if (hint) form.append('doc_type_hint', hint)

    const response = await fetch(`${API_BASE_URL}/api/receipts`, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      body: form,
    })

    const payload = await response.json().catch(() => null)

    if (!response.ok) {
      throw new ApiError(
        payload && typeof payload === 'object' && 'message' in payload
          ? String((payload as { message: unknown }).message)
          : response.statusText,
        response.status,
      )
    }

    return (payload as { data: ReceiptDetail }).data
  },
}
