<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import FormField from '@/components/ui/FormField.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSlideOver from '@/components/ui/AppSlideOver.vue'
import ModalFooter from '@/components/ui/ModalFooter.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
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
  name: 'TransactionFormSlideOver',

  components: {
    FormField,
    AppInput,
    AppSelect,
    AppButton,
    AppSlideOver,
    ModalFooter,
    ConfirmDialog,
    TransactionItemRow,
  },

  beforeRouteLeave(to, from, next) {
    if (!this.isDirty) {
      next()
      return
    }
    this.pendingLeave = next
    this.showUnsavedConfirm = true
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

      isDirty: false,
      showUnsavedConfirm: false,
      pendingLeave: null as null | ((allow?: boolean) => void),
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

    /**
     * A szülő route a módtól függ (létrehozásnál a lista, szerkesztésnél a
     * részletező oldal), ezért nem a `useModalRoute` composable-t használjuk —
     * a döntési logika viszont azonos.
     */
    close() {
      const hashId = this.$route.params.hashId as string | undefined
      const parent = hashId
        ? this.$router.resolve({ name: 'transaction-detail', params: { hashId } })
        : this.$router.resolve({ name: 'transactions' })

      const back = this.$router.options.history.state.back

      if (typeof back === 'string' && back === parent.fullPath) {
        this.$router.back()
        return
      }

      void this.$router.replace(parent.fullPath)
    },

    requestClose() {
      if (this.isDirty) {
        this.showUnsavedConfirm = true
        return
      }
      this.close()
    },

    confirmDiscard() {
      this.showUnsavedConfirm = false
      this.isDirty = false
      if (this.pendingLeave) {
        const leave = this.pendingLeave
        this.pendingLeave = null
        leave()
        return
      }
      this.close()
    },

    cancelDiscard() {
      this.showUnsavedConfirm = false
      if (this.pendingLeave) {
        const leave = this.pendingLeave
        this.pendingLeave = null
        leave(false)
      }
    },

    markDirty() {
      this.isDirty = true
    },

    onMerchantInput(value: string) {
      this.markDirty()
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
      this.markDirty()
      this.items.push(emptyItemDraft())
      this.itemKeys.push(randomId())
    },

    removeItem(index: number) {
      this.markDirty()
      this.items.splice(index, 1)
      this.itemKeys.splice(index, 1)
    },

    updateItem(index: number, draft: TransactionItemDraft) {
      this.markDirty()
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

        // A `beforeRouteLeave` guard előtt kell törölni, különben a saját
        // navigációnkat blokkolná egy „nem mentett módosítás" kérdéssel.
        this.isDirty = false

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
  <AppSlideOver
    :title="isEditMode ? t('transactions.editTitle') : t('transactions.createTitle')"
    @close="requestClose"
  >
    <form id="transaction-form" class="flex flex-col gap-6" @submit.prevent="onSubmit">
      <div class="flex flex-col gap-4">
        <div class="relative">
          <FormField
            id="transaction-merchant"
            v-slot="{ describedBy }"
            :label="t('transactions.merchantLabel')"
            :errors="fieldErrorsFor('merchant_id')"
          >
            <AppInput
              id="transaction-merchant"
              :model-value="merchantQuery"
              :invalid="fieldErrorsFor('merchant_id').length > 0"
              :aria-describedby="describedBy"
              @update:model-value="onMerchantInput"
            />
          </FormField>
          <ul
            v-if="
              showMerchantSuggestions &&
              (merchantSuggestions.length > 0 || merchantQuery.trim().length > 0)
            "
            class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-divider bg-panel shadow-pop"
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

        <FormField
          v-if="locations.length > 0"
          id="transaction-location"
          :label="t('transactions.locationLabel')"
        >
          <AppSelect
            id="transaction-location"
            :model-value="locationId"
            @update:model-value="
              (value: string) => {
                locationId = value
                markDirty()
              }
            "
          >
            <option value="">{{ t('transactions.noLocation') }}</option>
            <option v-for="location in locations" :key="location.hash_id" :value="location.hash_id">
              {{ location.address ?? t('transactions.noLocation') }}
            </option>
          </AppSelect>
        </FormField>

        <FormField
          id="transaction-date"
          v-slot="{ describedBy }"
          :label="t('transactions.dateLabel')"
          :errors="fieldErrorsFor('occurred_at')"
        >
          <AppInput
            id="transaction-date"
            v-model="occurredAt"
            type="date"
            :invalid="fieldErrorsFor('occurred_at').length > 0"
            :aria-describedby="describedBy"
          />
        </FormField>

        <FormField id="transaction-payment-method" :label="t('transactions.paymentMethodLabel')">
          <AppSelect
            id="transaction-payment-method"
            :model-value="paymentMethod"
            @update:model-value="
              (value: string) => {
                paymentMethod = value as PaymentMethod
                markDirty()
              }
            "
          >
            <option value="card">{{ t('transactions.card') }}</option>
            <option value="cash">{{ t('transactions.cash') }}</option>
            <option value="bank_transfer">{{ t('transactions.bankTransfer') }}</option>
          </AppSelect>
        </FormField>

        <FormField
          id="transaction-discount"
          v-slot="{ describedBy }"
          :label="t('transactions.discountLabel')"
          :errors="fieldErrorsFor('discount_amount')"
        >
          <AppInput
            id="transaction-discount"
            v-model="discountAmount"
            type="number"
            :invalid="fieldErrorsFor('discount_amount').length > 0"
            :aria-describedby="describedBy"
          />
        </FormField>
      </div>

      <div class="flex flex-col gap-3">
        <h3 class="text-sm font-semibold text-text">{{ t('transactions.itemsTitle') }}</h3>

        <div class="divide-y divide-divider rounded-lg border border-divider">
          <TransactionItemRow
            v-for="(item, index) in items"
            :key="itemKeys[index]"
            :model-value="item"
            :errors="itemErrorsFor(index)"
            @update:model-value="(draft: TransactionItemDraft) => updateItem(index, draft)"
            @remove="removeItem(index)"
          />
        </div>

        <AppButton type="button" variant="ghost" size="sm" @click="addItem">
          {{ t('transactions.addItem') }}
        </AppButton>

        <p class="text-[13px]" :class="totalMismatch ? 'text-danger-700' : 'text-neutral-600'">
          {{ t('transactions.sumOfItems', { sum: sumOfItems.toFixed(2) }) }}
        </p>

        <FormField
          id="transaction-total"
          v-slot="{ describedBy }"
          :label="t('transactions.totalLabel')"
          :errors="fieldErrorsFor('total_amount')"
        >
          <AppInput
            id="transaction-total"
            v-model="totalAmount"
            type="number"
            :invalid="fieldErrorsFor('total_amount').length > 0"
            :aria-describedby="describedBy"
          />
        </FormField>
      </div>

      <p v-if="generalError" class="text-sm text-danger-700">
        {{ translateErrorCode(t, generalError) }}
      </p>
    </form>

    <template #footer>
      <ModalFooter>
        <AppButton type="button" variant="ghost" @click="requestClose">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton type="submit" form="transaction-form" :disabled="!canSubmit">
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
      </ModalFooter>
    </template>
  </AppSlideOver>

  <ConfirmDialog
    :open="showUnsavedConfirm"
    :title="t('overlay.unsavedTitle')"
    :message="t('overlay.unsavedMessage')"
    :confirm-label="t('overlay.unsavedConfirm')"
    :cancel-label="t('overlay.unsavedCancel')"
    variant="danger"
    @confirm="confirmDiscard"
    @cancel="cancelDiscard"
  />
</template>
