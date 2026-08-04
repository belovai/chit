<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FindingList from '@/components/pipeline/FindingList.vue'
import ReviewFieldRow from '@/components/receipt/ReviewFieldRow.vue'
import CandidatePicker from '@/components/receipt/CandidatePicker.vue'
import { useAuthStore } from '@/stores/auth'
import { receiptService } from '@/services/receipt'
import { pipelineService } from '@/services/pipeline'
import type { Candidate, ReceiptDetail, ReceiptFinding } from '@/types/receipt'

// The flagged field name shown in the review UI does not always match the key
// the extraction payload uses for it — `merchant` is the gate's field name,
// but the raw extracted value lives under `merchant_name`. This maps the two
// so the "everything else" section does not repeat what is already expanded.
const FIELD_TO_EXTRACTED_KEY: Record<string, string> = {
  merchant: 'merchant_name',
}

interface ExtractedItem {
  description: string
  quantity: number
  unit: string | null
  unit_price_minor: number
  total_minor: number | null
}

interface ItemOverride {
  item_index: number
  product_id: number | null
  product_name?: string
}

export default defineComponent({
  name: 'ReceiptReviewView',

  components: {
    AppSection,
    AppCard,
    AppButton,
    AppInput,
    AppEmptyState,
    ConfirmDialog,
    FindingList,
    ReviewFieldRow,
    CandidatePicker,
  },

  setup() {
    const { t, te } = useI18n()
    return { t, te }
  },

  data() {
    return {
      receipt: null as ReceiptDetail | null,
      values: {} as Record<string, unknown>,
      originalValues: {} as Record<string, unknown>,
      isRejectOpen: false,
      isSubmitting: false,
      isLoading: false,
      error: null as string | null,
      imageFailed: false,
      imageObjectUrl: null as string | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hashId(): string {
      return String(this.$route.params.hashId)
    },

    fields(): string[] {
      return this.receipt?.review_request?.fields ?? []
    },

    findings(): ReceiptFinding[] {
      return this.receipt?.review_request?.findings ?? []
    },

    extractedItems(): ExtractedItem[] {
      const raw = this.receipt?.extracted?.items
      return Array.isArray(raw) ? (raw as ExtractedItem[]) : []
    },

    merchantAcceptedId(): number | null {
      if (typeof this.values.merchant_id === 'number') return this.values.merchant_id
      return this.receipt?.candidates.merchant?.accepted_id ?? null
    },

    // The AppInput bound to this field displays major units and stores minor
    // units in `values.total_minor` — the get/set boundary IS the "divide by
    // 100 on load, multiply on submit" the field is meant to do.
    totalAmountInput: {
      get(): string {
        const minor = this.values.total_minor
        return typeof minor === 'number' ? (minor / 100).toFixed(2) : ''
      },
      set(value: string) {
        const major = Number(value)
        this.values.total_minor =
          value.trim() === '' || Number.isNaN(major) ? null : Math.round(major * 100)
      },
    },

    otherEntries(): Array<[string, unknown]> {
      const extracted = this.receipt?.extracted ?? {}
      const covered = new Set(this.fields.map((field) => FIELD_TO_EXTRACTED_KEY[field] ?? field))
      // Shown in the items reconciliation strip instead, once items are expanded.
      if (this.fields.includes('items')) covered.add('discount_minor')
      return Object.entries(extracted).filter(([key]) => !covered.has(key))
    },

    // Discount is a document-level deduction, not a line item — the extraction
    // prompt is explicit that a discount printed as a negative line goes here
    // instead of into `items`, so it never subtracts twice. Which is exactly
    // why it is worth correcting by hand: one deduction line read twice shows
    // up only as an off-by-that-amount in the reconciliation below.
    discountMinor(): number | null {
      if ('discount_minor' in this.values) {
        const edited = this.values.discount_minor
        return typeof edited === 'number' ? edited : null
      }
      const raw = this.receipt?.extracted?.discount_minor
      return typeof raw === 'number' ? raw : null
    },

    discountAmountInput: {
      get(): string {
        const minor = this.discountMinor
        return minor === null ? '' : (minor / 100).toFixed(2)
      },
      set(value: string) {
        const major = Number(value)
        this.values.discount_minor =
          value.trim() === '' || Number.isNaN(major) ? null : Math.round(major * 100)
      },
    },

    itemsSubtotalMinor(): number {
      return this.extractedItems.reduce((sum, item) => sum + this.itemLineTotalMinor(item), 0)
    },

    // What the recognized items and discount imply the total should be — the
    // number to compare against the (possibly OCR-garbled) printed total.
    expectedTotalMinor(): number {
      return this.itemsSubtotalMinor - (this.discountMinor ?? 0)
    },

    reconciliationDeltaMinor(): number | null {
      const current =
        typeof this.values.total_minor === 'number'
          ? this.values.total_minor
          : typeof this.receipt?.extracted?.total_minor === 'number'
            ? this.receipt.extracted.total_minor
            : null
      return current === null ? null : this.expectedTotalMinor - current
    },
  },

  async mounted() {
    await this.load()
  },

  beforeUnmount() {
    if (this.imageObjectUrl !== null) URL.revokeObjectURL(this.imageObjectUrl)
  },

  methods: {
    async load() {
      this.isLoading = true
      this.error = null
      try {
        this.receipt = await receiptService.get(this.token as string, this.hashId)
        if (this.receipt.status === 'needs_review') {
          this.initializeValues()
        }
        await this.loadImage()
      } catch (err) {
        this.error = err instanceof Error ? err.message : String(err)
      } finally {
        this.isLoading = false
      }
    },

    async loadImage() {
      if (this.receipt === null || this.receipt.run_hash_id === null) return
      try {
        const blob = await pipelineService.artifactBlob(
          this.token as string,
          this.receipt.run_hash_id,
          'normalized_image',
        )
        if (this.imageObjectUrl !== null) URL.revokeObjectURL(this.imageObjectUrl)
        this.imageObjectUrl = URL.createObjectURL(blob)
      } catch {
        this.imageFailed = true
      }
    },

    // Only fields the gate flagged get a starting value in `values` — anything
    // the user never touches must stay absent so the diff in `submit()` never
    // reports a "change" that was not one.
    initializeValues() {
      const extracted = this.receipt?.extracted ?? {}
      const values: Record<string, unknown> = {}

      for (const field of this.fields) {
        // The line items themselves are resolved through CandidatePicker
        // selections rather than typed, but the discount they reconcile
        // against is a plain number the reviewer can correct.
        if (field === 'items') {
          values.discount_minor =
            typeof extracted.discount_minor === 'number' ? extracted.discount_minor : null
          continue
        }

        // Resolved through a CandidatePicker selection, not typed directly.
        if (field === 'merchant') continue

        if (field === 'total_minor') {
          values.total_minor =
            typeof extracted.total_minor === 'number' ? extracted.total_minor : null
          continue
        }

        const raw = extracted[field]
        values[field] = typeof raw === 'string' || typeof raw === 'number' ? String(raw) : ''
      }

      this.values = values
      this.originalValues = { ...values }
    },

    fieldLabel(key: string): string {
      const path = `receipts.fields.${key}`
      return this.te(path) ? this.t(path) : key
    },

    formatMoney(minor: number | null | undefined): string {
      if (typeof minor !== 'number') return '—'
      const currency = this.receipt?.extracted?.currency
      const suffix = typeof currency === 'string' ? ` ${currency}` : ''
      return `${(minor / 100).toFixed(2)}${suffix}`
    },

    itemLineTotalMinor(item: ExtractedItem): number {
      return item.total_minor ?? Math.round(item.quantity * item.unit_price_minor)
    },

    formatValue(value: unknown): string {
      if (value === null || value === undefined || value === '') return '—'
      if (typeof value === 'object') return JSON.stringify(value)
      return String(value)
    },

    genericValue(field: string): string {
      const raw = this.values[field]
      if (typeof raw === 'string') return raw
      if (typeof raw === 'number') return String(raw)
      return ''
    },

    setGenericValue(field: string, value: string) {
      this.values[field] = value
    },

    selectMerchant(id: number) {
      this.values.merchant_id = id
      delete this.values.merchant_name
    },

    createMerchant(name: string) {
      this.values.merchant_name = name
      delete this.values.merchant_id
    },

    productCandidatesFor(index: number): Candidate[] {
      return this.receipt?.candidates.products?.items[index]?.candidates ?? []
    },

    productAcceptedIdFor(index: number): number | null {
      const overrides = Array.isArray(this.values.items)
        ? (this.values.items as ItemOverride[])
        : []
      const override = overrides.find((entry) => entry.item_index === index)
      if (override) return override.product_id
      return this.receipt?.candidates.products?.items[index]?.accepted_id ?? null
    },

    selectProduct(index: number, id: number) {
      this.setItemOverride(index, { item_index: index, product_id: id })
    },

    createProduct(index: number, name: string) {
      this.setItemOverride(index, { item_index: index, product_id: null, product_name: name })
    },

    setItemOverride(index: number, entry: ItemOverride) {
      const items = Array.isArray(this.values.items)
        ? [...(this.values.items as ItemOverride[])]
        : []
      const existingIndex = items.findIndex((item) => item.item_index === index)
      if (existingIndex >= 0) {
        items[existingIndex] = entry
      } else {
        items.push(entry)
      }
      this.values.items = items
    },

    submit() {
      const changed: Record<string, unknown> = {}
      for (const [key, value] of Object.entries(this.values)) {
        if (value !== this.originalValues[key]) changed[key] = value
      }
      return receiptService.review(this.token as string, this.hashId, {
        decision: 'approve',
        values: changed,
      })
    },

    async approve() {
      this.error = null
      this.isSubmitting = true
      try {
        await this.submit()
        await this.$router.push({ name: 'receipts' })
      } catch (err) {
        this.error = err instanceof Error ? err.message : String(err)
      } finally {
        this.isSubmitting = false
      }
    },

    async reject() {
      this.isRejectOpen = false
      this.error = null
      this.isSubmitting = true
      try {
        await receiptService.review(this.token as string, this.hashId, { decision: 'reject' })
        await this.$router.push({ name: 'receipts' })
      } catch (err) {
        this.error = err instanceof Error ? err.message : String(err)
      } finally {
        this.isSubmitting = false
      }
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppSection
      :title="t('receipts.review.heading')"
      :description="t('receipts.review.subheading')"
    />

    <template v-if="receipt !== null && receipt.status !== 'needs_review'">
      <AppCard>
        <p class="text-sm text-neutral-600">{{ t('receipts.review.notAwaiting') }}</p>
        <RouterLink :to="{ name: 'receipts' }" class="text-sm text-accent hover:text-accent-600">
          {{ t('receipts.backToList') }}
        </RouterLink>
      </AppCard>
    </template>

    <template v-else-if="receipt !== null">
      <div class="flex flex-col gap-4 md:flex-row md:items-start">
        <div class="min-w-0 md:w-1/2">
          <AppCard :padded="false">
            <img
              v-if="imageObjectUrl !== null && !imageFailed"
              :src="imageObjectUrl"
              alt=""
              class="w-full"
              @error="imageFailed = true"
            />
            <AppEmptyState v-else :title="receipt.original_filename" />
          </AppCard>
        </div>

        <div class="flex min-w-0 flex-col gap-4 md:w-1/2">
          <AppCard :title="t('pipeline.detail.findings')">
            <FindingList :findings="findings" />
          </AppCard>

          <AppCard v-if="fields.length > 0">
            <ReviewFieldRow v-for="field in fields" :key="field" :field="field" flagged>
              <CandidatePicker
                v-if="field === 'merchant'"
                :candidates="receipt.candidates.merchant?.candidates ?? []"
                :accepted-id="merchantAcceptedId"
                :raw-name="receipt.candidates.merchant?.raw_name ?? null"
                @select="selectMerchant"
                @create="createMerchant"
              />

              <AppInput
                v-else-if="field === 'total_minor'"
                id="review-total-minor"
                v-model="totalAmountInput"
                type="number"
                step="0.01"
              />

              <AppInput
                v-else-if="field === 'occurred_at'"
                id="review-occurred-at"
                type="datetime-local"
                :model-value="genericValue(field)"
                @update:model-value="(value: string) => setGenericValue(field, value)"
              />

              <div v-else-if="field === 'items'" class="flex flex-col gap-3">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-sm bg-neutral-50 p-2 text-xs">
                  <dt class="text-neutral-500">{{ t('receipts.review.itemsSubtotal') }}</dt>
                  <dd class="text-right">{{ formatMoney(itemsSubtotalMinor) }}</dd>
                  <dt class="self-center text-neutral-500">
                    <label for="review-discount-minor">{{ t('receipts.review.discount') }}</label>
                  </dt>
                  <dd class="flex justify-end">
                    <AppInput
                      id="review-discount-minor"
                      v-model="discountAmountInput"
                      type="number"
                      step="0.01"
                      class="max-w-28 py-1 text-right"
                    />
                  </dd>
                  <dt class="text-neutral-500">{{ t('receipts.review.expectedTotal') }}</dt>
                  <dd class="text-right">{{ formatMoney(expectedTotalMinor) }}</dd>
                  <template v-if="reconciliationDeltaMinor">
                    <dt class="text-danger-700">{{ t('receipts.review.difference') }}</dt>
                    <dd class="text-right text-danger-700">
                      {{ formatMoney(reconciliationDeltaMinor) }}
                    </dd>
                  </template>
                </dl>

                <div
                  v-for="(item, index) in extractedItems"
                  :key="index"
                  class="flex flex-col gap-1.5 rounded-sm border border-divider p-2"
                >
                  <div class="flex items-baseline justify-between gap-2">
                    <p class="text-xs text-neutral-700">
                      {{ item.description }} · {{ item.quantity
                      }}{{ item.unit ? ` ${item.unit}` : '' }}
                      <template v-if="item.quantity !== 1">
                        × {{ formatMoney(item.unit_price_minor) }}
                      </template>
                    </p>
                    <p class="whitespace-nowrap text-xs font-medium text-neutral-700">
                      {{ formatMoney(itemLineTotalMinor(item)) }}
                    </p>
                  </div>
                  <CandidatePicker
                    :candidates="productCandidatesFor(index)"
                    :accepted-id="productAcceptedIdFor(index)"
                    :raw-name="item.description"
                    :create-label="t('receipts.review.newProduct')"
                    @select="(id: number) => selectProduct(index, id)"
                    @create="(name: string) => createProduct(index, name)"
                  />
                </div>
              </div>

              <AppInput
                v-else
                :id="`review-${field}`"
                :model-value="genericValue(field)"
                @update:model-value="(value: string) => setGenericValue(field, value)"
              />
            </ReviewFieldRow>
          </AppCard>

          <AppCard v-if="otherEntries.length > 0" :padded="false">
            <details class="px-5 py-4">
              <summary class="cursor-pointer text-sm font-medium">
                {{ t('receipts.review.othersTitle') }}
              </summary>
              <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                <template v-for="[key, value] in otherEntries" :key="key">
                  <dt class="text-neutral-500">{{ fieldLabel(key) }}</dt>
                  <dd class="truncate">{{ formatValue(value) }}</dd>
                </template>
              </dl>
            </details>
          </AppCard>

          <p v-if="error" class="text-sm text-danger-700">{{ error }}</p>

          <div class="flex justify-end gap-2">
            <AppButton variant="ghost" :disabled="isSubmitting" @click="isRejectOpen = true">
              {{ t('receipts.review.reject') }}
            </AppButton>
            <AppButton :disabled="isSubmitting" @click="approve">
              {{ t('receipts.review.approve') }}
            </AppButton>
          </div>
        </div>
      </div>
    </template>

    <p v-else-if="error" class="text-sm text-danger-700">{{ error }}</p>

    <ConfirmDialog
      :open="isRejectOpen"
      :title="t('receipts.review.rejectTitle')"
      :message="t('receipts.review.rejectBody')"
      variant="danger"
      @confirm="reject"
      @cancel="isRejectOpen = false"
    />
  </div>
</template>
