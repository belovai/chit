<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { transactionService } from '@/services/transaction'
import type { Transaction } from '@/types/transaction'

export default defineComponent({
  name: 'TransactionDetailView',

  components: {
    AppButton,
    ConfirmDialog,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      transaction: null as Transaction | null,
      isLoading: false,
      isDeletePending: false,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hashId(): string {
      return this.$route.params.hashId as string
    },
  },

  async mounted() {
    await this.loadTransaction()
  },

  methods: {
    async loadTransaction() {
      this.isLoading = true
      try {
        this.transaction = await transactionService.get(this.token as string, this.hashId)
      } finally {
        this.isLoading = false
      }
    },

    paymentMethodLabel(method: Transaction['payment_method']): string {
      return method === 'cash'
        ? this.t('transactions.cash')
        : method === 'bank_transfer'
          ? this.t('transactions.bankTransfer')
          : this.t('transactions.card')
    },

    lineTotal(quantity: string, unitPrice: string): string {
      return (Number(quantity) * Number(unitPrice)).toFixed(2)
    },

    goToEdit() {
      this.$router.push({ name: 'transaction-edit', params: { hashId: this.hashId } })
    },

    requestDelete() {
      this.isDeletePending = true
    },

    cancelDelete() {
      this.isDeletePending = false
    },

    async confirmDelete() {
      await transactionService.destroy(this.token as string, this.hashId)
      this.isDeletePending = false
      await this.$router.push({ name: 'transactions' })
    },
  },
})
</script>

<template>
  <div v-if="transaction" class="flex flex-col gap-6">
    <RouterLink :to="{ name: 'transactions' }" class="text-sm text-accent hover:text-accent-600">
      {{ t('transactions.backToList') }}
    </RouterLink>

    <div class="flex flex-col gap-2 border border-divider bg-surface p-6">
      <div class="flex items-center justify-between">
        <h1 class="text-lg font-semibold">{{ transaction.merchant.name }}</h1>
        <span class="text-lg font-semibold">
          {{ transaction.total_amount }} {{ transaction.currency }}
        </span>
      </div>
      <p v-if="transaction.location?.address" class="text-sm text-neutral-600">
        {{ transaction.location.address }}
      </p>
      <div class="flex flex-wrap gap-4 text-sm text-neutral-600">
        <span>{{ transaction.occurred_at }}</span>
        <span>{{ paymentMethodLabel(transaction.payment_method) }}</span>
        <span v-if="transaction.discount_amount">
          {{ t('transactions.discountLabel') }}: {{ transaction.discount_amount }}
        </span>
      </div>
    </div>

    <div class="flex flex-col gap-3 border border-divider bg-surface p-6">
      <h2 class="text-lg">{{ t('transactions.itemsTitle') }}</h2>
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-divider text-xs text-neutral-600">
            <th class="py-2">{{ t('transactions.descriptionLabel') }}</th>
            <th class="py-2">{{ t('transactions.quantityLabel') }}</th>
            <th class="py-2">{{ t('transactions.unitPriceLabel') }}</th>
            <th class="py-2 text-right">{{ t('transactions.totalLabel') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(item, index) in transaction.items"
            :key="index"
            class="border-b border-divider"
          >
            <td class="py-2">{{ item.description }}</td>
            <td class="py-2">{{ item.quantity }} {{ item.unit }}</td>
            <td class="py-2">{{ item.unit_price }}</td>
            <td class="py-2 text-right">{{ lineTotal(item.quantity, item.unit_price) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex gap-3">
      <AppButton @click="goToEdit">{{ t('transactions.edit') }}</AppButton>
      <AppButton variant="danger" @click="requestDelete">{{ t('transactions.delete') }}</AppButton>
    </div>

    <ConfirmDialog
      :open="isDeletePending"
      :message="t('transactions.deleteConfirm')"
      :confirm-label="t('transactions.delete')"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="cancelDelete"
    />
  </div>
</template>
