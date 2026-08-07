export type ReceiptStatus =
  | 'pending'
  | 'processing'
  | 'needs_review'
  | 'approved'
  | 'rejected'
  | 'failed'
  | 'canceled'

export type DocType = 'receipt' | 'utility_bill' | 'unknown'

export interface Candidate {
  id: number
  hash_id?: string
  name: string
  score: number
}

export interface LocationCandidate {
  id: number
  hash_id: string
  name: string
  score: number
}

export interface ReceiptFinding {
  code: string
  severity: 'info' | 'warning' | 'blocker'
  message: string | null
  context: Record<string, unknown>
  step_key?: string
}

export interface ReviewRequest {
  doc_type: DocType
  reason: string | null
  blockers: string[]
  warnings: string[]
  findings: ReceiptFinding[]
  fields: string[]
}

export interface Receipt {
  hash_id: string
  original_filename: string
  mime: string
  size_bytes: number
  status: ReceiptStatus
  doc_type: DocType | null
  run_hash_id: string | null
  transaction_hash_id: string | null
  created_at: string | null
}

export interface ReceiptDetail extends Receipt {
  extracted: Record<string, unknown> | null
  candidates: {
    merchant: {
      raw_name: string | null
      accepted_id: number | null
      candidates: Candidate[]
    } | null
    location: {
      raw_address: string | null
      accepted_id: number | null
      accepted_hash_id: string | null
      candidates: LocationCandidate[]
    } | null
    products: {
      items: Array<{
        item_index: number
        description: string
        accepted_id: number | null
        candidates: Candidate[]
      }>
    } | null
    previous_bill: Record<string, unknown> | null
  }
  review_request: ReviewRequest | null
}

export interface ReviewPayload {
  decision: 'approve' | 'reject'
  values?: Record<string, unknown>
  note?: string
}

const SETTLED: ReceiptStatus[] = ['needs_review', 'approved', 'rejected', 'failed', 'canceled']

export function isReceiptSettled(status: ReceiptStatus): boolean {
  return SETTLED.includes(status)
}
