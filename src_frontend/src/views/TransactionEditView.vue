<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import FormField from '@/components/ui/FormField.vue'
import AppButton from '@/components/ui/AppButton.vue'
import TransactionItemRow, {
  emptyItemDraft,
  type TransactionItemDraft,
} from '@/components/transaction/TransactionItemRow.vue'
import { useAuthStore } from '@/stores/auth'
import { merchantService } from '@/services/merchant'
import { transactionService } from '@/services/transaction'
import { ApiError } from '@/types/auth'
import { translateErrorCode } from '@/utils/errors'
import { randomId } from '@/utils/id'
import type { MerchantLocation } from '@/types/merchant'
import type { MerchantMatch, PaymentMethod, TransactionPayload } from '@/types/transaction'

const SUGGEST_DEBOUNCE_MS = 300

export default defineComponent({
  name: 'TransactionEditView',

  components: {
    FormField,
    AppButton,
    TransactionItemRow,
  },

  setup() {
    const { t } = useI18n()
    return { t, translateErrorCode }
  },

  data() {
    return {
      merchantId: null as string | null,
      merchantQuery: '',
      merchantSuggestions: [] as MerchantMatch[],
      showMerchantSuggestions: false,
      merchantDebounceHandle: null as ReturnType<typeof setTimeout> | null,
      isCreatingMerchant: false,

      locations: [] as MerchantLocation[],
      locationId: '' as string,

      occurredAt: new Date().toISOString().slice(0, 10),
      paymentMethod: 'card' as PaymentMethod,
      discountAmount: '',
      totalAmount: '0',

      items: [emptyItemDraft()] as TransactionItemDraft[],
      itemKeys: [randomId()] as string[],

      fieldErrors: {} as Record<string, string[]>,
      itemFieldErrors: {} as Record<number, Record<string, string[]>>,
      generalError: null as string | null,
      isSaving: false,
      isLoading: false,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hashId(): string | undefined {
      return this.$route.params.hashId as string | undefined
    },

    isEditMode(): boolean {
      return this.hashId !== undefined
    },

    sumOfItems(): number {
      const itemsTotal = this.items.reduce(
        (sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unitPrice) || 0),
        0,
      )
      return itemsTotal - (Number(this.discountAmount) || 0)
    },

    totalMismatch(): boolean {
      return Math.abs((Number(this.totalAmount) || 0) - this.sumOfItems) > 0.01
    },

    canSubmit(): boolean {
      return this.merchantId !== null && this.items.length > 0 && !this.isSaving
    },
  },

  async mounted() {
    if (this.isEditMode) {
      await this.loadTransaction()
    }
  },

  methods: {
    async loadTransaction() {
      this.isLoading = true
      try {
        const transaction = await transactionService.get(
          this.token as string,
          this.hashId as string,
        )

        this.merchantId = transaction.merchant.hash_id
        this.merchantQuery = transaction.merchant.name
        this.locationId = transaction.location?.hash_id ?? ''
        this.occurredAt = transaction.occurred_at
        this.paymentMethod = transaction.payment_method
        this.discountAmount = transaction.discount_amount ?? ''
        this.totalAmount = transaction.total_amount
        this.items = transaction.items.map((item) => ({
          productId: item.product?.hash_id ?? null,
          description: item.description,
          quantity: item.quantity,
          unit: item.unit ?? '',
          unitPrice: item.unit_price,
        }))
        this.itemKeys = this.items.map(() => randomId())

        await this.loadLocations()
      } finally {
        this.isLoading = false
      }
    },

    fieldErrorsFor(field: string): string[] {
      const codes = this.fieldErrors[field] ?? []
      return codes.map((code) => translateErrorCode(this.t, code, field))
    },

    onMerchantInput(value: string) {
      this.merchantQuery = value
      this.merchantId = null
      this.locations = []
      this.locationId = ''
      this.showMerchantSuggestions = true

      if (this.merchantDebounceHandle) {
        clearTimeout(this.merchantDebounceHandle)
      }

      const query = value.trim()
      if (query.length === 0) {
        this.merchantSuggestions = []
        return
      }

      this.merchantDebounceHandle = setTimeout(async () => {
        this.merchantSuggestions = await merchantService.suggest(this.token as string, query)
      }, SUGGEST_DEBOUNCE_MS)
    },

    async selectMerchant(match: MerchantMatch) {
      this.merchantId = match.merchant.hash_id
      this.merchantQuery = match.merchant.name
      this.merchantSuggestions = []
      this.showMerchantSuggestions = false
      await this.loadLocations()
    },

    async createMerchant() {
      this.isCreatingMerchant = true
      try {
        const created = await merchantService.create(this.token as string, {
          name: this.merchantQuery.trim(),
        })
        this.merchantId = created.hash_id
        this.merchantQuery = created.name
        this.merchantSuggestions = []
        this.showMerchantSuggestions = false
        await this.loadLocations()
      } finally {
        this.isCreatingMerchant = false
      }
    },

    async loadLocations() {
      if (!this.merchantId) {
        this.locations = []
        return
      }
      this.locations = await merchantService.listLocations(this.token as string, this.merchantId)
    },

    addItem() {
      this.items.push(emptyItemDraft())
      this.itemKeys.push(randomId())
    },

    removeItem(index: number) {
      this.items.splice(index, 1)
      this.itemKeys.splice(index, 1)
    },

    updateItem(index: number, draft: TransactionItemDraft) {
      this.items.splice(index, 1, draft)
    },

    itemErrorsFor(index: number): Record<string, string[]> {
      return this.itemFieldErrors[index] ?? {}
    },

    buildPayload(): TransactionPayload {
      return {
        merchant_id: this.merchantId as string,
        location_id: this.locationId || null,
        currency: 'HUF',
        source: 'manual',
        payment_method: this.paymentMethod,
        discount_amount: this.discountAmount === '' ? null : Number(this.discountAmount),
        total_amount: Number(this.totalAmount) || 0,
        occurred_at: this.occurredAt,
        items: this.items.map((item) => ({
          product_id: item.productId,
          description: item.description,
          quantity: Number(item.quantity) || 0,
          unit: item.unit === '' ? null : item.unit,
          unit_price: Number(item.unitPrice) || 0,
        })),
      }
    },

    applyValidationErrors(errors: Record<string, string[]>) {
      const headerErrors: Record<string, string[]> = {}
      const itemErrors: Record<number, Record<string, string[]>> = {}

      for (const [key, codes] of Object.entries(errors)) {
        const itemMatch = /^items\.(\d+)\.(.+)$/.exec(key)
        if (itemMatch) {
          const index = Number(itemMatch[1])
          const field = itemMatch[2] as string
          itemErrors[index] = { ...itemErrors[index], [field]: codes }
        } else {
          headerErrors[key] = codes
        }
      }

      this.fieldErrors = headerErrors
      this.itemFieldErrors = itemErrors
    },

    async onSubmit() {
      this.fieldErrors = {}
      this.itemFieldErrors = {}
      this.generalError = null
      this.isSaving = true
      try {
        const transaction = this.isEditMode
          ? await transactionService.update(
              this.token as string,
              this.hashId as string,
              this.buildPayload(),
            )
          : await transactionService.create(this.token as string, this.buildPayload())

        await this.$router.push({
          name: 'transaction-detail',
          params: { hashId: transaction.hash_id },
        })
      } catch (error) {
        if (error instanceof ApiError && error.errors) {
          this.applyValidationErrors(error.errors)
        } else {
          this.generalError = 'network.connection_failed'
        }
      } finally {
        this.isSaving = false
      }
    },
  },
})
</script>

<template>
  <form class="flex flex-col gap-6" @submit.prevent="onSubmit">
    <div class="flex flex-col gap-4 border border-divider bg-surface p-6">
      <div class="relative">
        <FormField
          id="transaction-merchant"
          :model-value="merchantQuery"
          :label="t('transactions.merchantLabel')"
          :errors="fieldErrorsFor('merchant_id')"
          @update:model-value="onMerchantInput"
        />
        <ul
          v-if="
            showMerchantSuggestions &&
            (merchantSuggestions.length > 0 || merchantQuery.trim().length > 0)
          "
          class="absolute z-10 mt-1 w-full border border-divider bg-bg shadow-lg"
        >
          <li v-for="match in merchantSuggestions" :key="match.merchant.hash_id">
            <button
              type="button"
              class="block w-full px-3 py-2 text-left text-sm hover:bg-surface"
              @click="selectMerchant(match)"
            >
              {{ match.merchant.name }}
            </button>
          </li>
          <li v-if="merchantQuery.trim().length > 0 && merchantId === null">
            <button
              type="button"
              class="block w-full px-3 py-2 text-left text-sm text-accent hover:bg-surface"
              :disabled="isCreatingMerchant"
              @click="createMerchant"
            >
              {{ t('transactions.createMerchantOption', { name: merchantQuery.trim() }) }}
            </button>
          </li>
        </ul>
      </div>

      <div v-if="locations.length > 0" class="flex flex-col gap-1">
        <label for="transaction-location" class="text-xs font-semibold text-neutral-700">
          {{ t('transactions.locationLabel') }}
        </label>
        <select
          id="transaction-location"
          v-model="locationId"
          class="w-full border border-neutral-400 bg-bg px-3 py-2 text-sm text-text"
        >
          <option value="">{{ t('transactions.noLocation') }}</option>
          <option v-for="location in locations" :key="location.hash_id" :value="location.hash_id">
            {{ location.address ?? t('transactions.noLocation') }}
          </option>
        </select>
      </div>

      <FormField
        id="transaction-date"
        type="date"
        v-model="occurredAt"
        :label="t('transactions.dateLabel')"
        :errors="fieldErrorsFor('occurred_at')"
      />

      <div class="flex flex-col gap-1">
        <label for="transaction-payment-method" class="text-xs font-semibold text-neutral-700">
          {{ t('transactions.paymentMethodLabel') }}
        </label>
        <select
          id="transaction-payment-method"
          v-model="paymentMethod"
          class="w-full border border-neutral-400 bg-bg px-3 py-2 text-sm text-text"
        >
          <option value="card">{{ t('transactions.card') }}</option>
          <option value="cash">{{ t('transactions.cash') }}</option>
          <option value="bank_transfer">{{ t('transactions.bankTransfer') }}</option>
        </select>
      </div>

      <FormField
        id="transaction-discount"
        type="number"
        v-model="discountAmount"
        :label="t('transactions.discountLabel')"
        :errors="fieldErrorsFor('discount_amount')"
      />
    </div>

    <div class="flex flex-col gap-3 border border-divider bg-surface p-6">
      <h2 class="text-lg">{{ t('transactions.itemsTitle') }}</h2>

      <TransactionItemRow
        v-for="(item, index) in items"
        :key="itemKeys[index]"
        :model-value="item"
        :errors="itemErrorsFor(index)"
        @update:model-value="(draft: TransactionItemDraft) => updateItem(index, draft)"
        @remove="removeItem(index)"
      />

      <AppButton type="button" variant="ghost" @click="addItem">
        {{ t('transactions.addItem') }}
      </AppButton>

      <p class="text-sm" :class="totalMismatch ? 'text-danger-700' : 'text-neutral-600'">
        {{ t('transactions.sumOfItems', { sum: sumOfItems.toFixed(2) }) }}
      </p>

      <FormField
        id="transaction-total"
        type="number"
        v-model="totalAmount"
        :label="t('transactions.totalLabel')"
        :errors="fieldErrorsFor('total_amount')"
      />
    </div>

    <p v-if="generalError" class="text-sm text-danger-700">
      {{ translateErrorCode(t, generalError) }}
    </p>

    <AppButton type="submit" :disabled="!canSubmit">
      {{
        isEditMode
          ? isSaving
            ? t('transactions.saving')
            : t('transactions.save')
          : isSaving
            ? t('transactions.creating')
            : t('transactions.create')
      }}
    </AppButton>
  </form>
</template>
