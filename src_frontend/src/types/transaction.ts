import type { Merchant, MerchantLocation } from '@/types/merchant'
import type { Product } from '@/types/product'

export type TransactionSource = 'manual' | 'receipt'
export type PaymentMethod = 'cash' | 'bank_transfer' | 'card'

export interface Transaction {
  hash_id: string
  merchant: Merchant
  location: MerchantLocation | null
  currency: string
  source: TransactionSource
  payment_method: PaymentMethod
  discount_amount: string | null
  total_amount: string
  occurred_at: string
  items: TransactionItem[]
}

export interface TransactionItem {
  product: Product | null
  description: string
  quantity: string
  unit: string | null
  unit_price: string
}

export interface TransactionPayload {
  merchant_id: string
  location_id: string | null
  currency: string
  source: 'manual'
  payment_method: PaymentMethod
  discount_amount: number | null
  total_amount: number
  occurred_at: string
  items: TransactionItemPayload[]
}

export interface TransactionItemPayload {
  product_id: string | null
  description: string
  quantity: number
  unit: string | null
  unit_price: number
}

export interface MerchantMatch {
  merchant: Merchant
  score: number
}

export interface ProductMatch {
  product: Product
  score: number
}
