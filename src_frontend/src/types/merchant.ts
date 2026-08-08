export interface Merchant {
  hash_id: string
  name: string
  locations_count: number | null
}

export interface MerchantLocation {
  hash_id: string
  is_online: boolean
  address: string | null
  latitude: number | null
  longitude: number | null
}

export interface MerchantPayload {
  name: string
}

export interface MerchantLocationPayload {
  is_online: boolean
  address: string | null
  latitude: number | null
  longitude: number | null
}

export interface LocationSuggestion {
  hash_id: string
  address: string | null
  score: number | null
}

export interface LocationSuggestResult {
  candidates: LocationSuggestion[]
  accepted_hash_id: string | null
  ambiguous: boolean
}
