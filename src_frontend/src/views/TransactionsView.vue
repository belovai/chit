<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppListItem from '@/components/ui/AppListItem.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'
import { transactionService } from '@/services/transaction'
import { formatDateTime } from '@/utils/datetime'
import type { Transaction } from '@/types/transaction'

export default defineComponent({
  name: 'TransactionsView',

  components: {
    AppSection,
    AppCard,
    AppListItem,
    AppEmptyState,
    AppBadge,
    AppButton,
  },

  setup() {
    const { t } = useI18n()
    return { t, formatDateTime }
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

  watch: {
    // A lista a `transaction-new` overlay named view szülője, ezért nyitáskor
    // nem unmountol. Záráskor újratöltjük az első oldalt.
    '$route.name'(name: string | undefined) {
      if (name === 'transactions') {
        void this.loadPage(1)
      }
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
    <AppSection :title="t('transactions.title')">
      <template #actions>
        <AppButton @click="goToNew">{{ t('transactions.newTransaction') }}</AppButton>
      </template>
    </AppSection>

    <AppCard :padded="false">
      <AppEmptyState
        v-if="transactions.length === 0 && !isLoading"
        :title="t('transactions.empty')"
      />

      <ul v-else class="divide-y divide-divider">
        <li v-for="transaction in transactions" :key="transaction.hash_id">
          <AppListItem interactive @click="goToDetail(transaction)">
            <span class="flex flex-col gap-0.5">
              <span class="truncate text-sm font-medium text-text">
                {{ transaction.merchant.name }}
              </span>
              <span class="flex items-center gap-2 text-[13px] text-neutral-600">
                {{ formatDateTime(transaction.occurred_at) }}
                <AppBadge>{{ paymentMethodLabel(transaction.payment_method) }}</AppBadge>
              </span>
            </span>

            <template #trailing>
              <span class="text-sm font-medium text-text">
                {{ transaction.total_amount }} {{ transaction.currency }}
              </span>
            </template>
          </AppListItem>
        </li>
      </ul>

      <template v-if="hasMore" #footer>
        <AppButton
          variant="ghost"
          size="sm"
          :disabled="isLoading"
          @click="loadPage(currentPage + 1)"
        >
          {{ t('transactions.loadMore') }}
        </AppButton>
      </template>
    </AppCard>

    <RouterView name="modal" />
  </div>
</template>
