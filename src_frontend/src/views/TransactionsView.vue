<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'
import { transactionService } from '@/services/transaction'
import type { Transaction } from '@/types/transaction'

export default defineComponent({
  name: 'TransactionsView',

  components: {
    AppButton,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      transactions: [] as Transaction[],
      currentPage: 1,
      lastPage: 1,
      isLoading: false,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hasMore(): boolean {
      return this.currentPage < this.lastPage
    },
  },

  async mounted() {
    await this.loadPage(1)
  },

  methods: {
    async loadPage(page: number) {
      this.isLoading = true
      try {
        const result = await transactionService.list(this.token as string, page)
        this.transactions = page === 1 ? result.data : [...this.transactions, ...result.data]
        this.currentPage = result.currentPage
        this.lastPage = result.lastPage
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

    goToNew() {
      this.$router.push({ name: 'transaction-new' })
    },

    goToDetail(transaction: Transaction) {
      this.$router.push({ name: 'transaction-detail', params: { hashId: transaction.hash_id } })
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="hidden justify-end md:flex">
      <AppButton @click="goToNew">{{ t('transactions.newTransaction') }}</AppButton>
    </div>

    <p v-if="transactions.length === 0 && !isLoading" class="text-sm text-neutral-600">
      {{ t('transactions.empty') }}
    </p>

    <button
      v-for="transaction in transactions"
      :key="transaction.hash_id"
      type="button"
      class="flex flex-col gap-1 border border-divider bg-surface p-4 text-left hover:border-accent"
      @click="goToDetail(transaction)"
    >
      <div class="flex items-center justify-between">
        <span class="text-sm font-semibold">{{ transaction.merchant.name }}</span>
        <span class="text-sm">{{ transaction.total_amount }} {{ transaction.currency }}</span>
      </div>
      <div class="flex items-center justify-between text-xs text-neutral-600">
        <span>{{ transaction.occurred_at }}</span>
        <span>{{ paymentMethodLabel(transaction.payment_method) }}</span>
        <span>{{ transaction.items.length }}</span>
      </div>
    </button>

    <AppButton
      v-if="hasMore"
      variant="ghost"
      :disabled="isLoading"
      @click="loadPage(currentPage + 1)"
    >
      {{ t('transactions.loadMore') }}
    </AppButton>
  </div>
</template>
