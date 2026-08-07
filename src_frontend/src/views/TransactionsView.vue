<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import { MapPinIcon } from '@heroicons/vue/24/outline'
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
    MapPinIcon,
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
    // The list is the parent of the `transaction-new` overlay named view, so
    // it doesn't unmount on open. Reload the first page on close.
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

    locationLabel(location: Transaction['location']): string | null {
      if (location === null) return null
      if (location.is_online) return this.t('transactions.onlineLocation')

      return location.address
    },

    itemCountLabel(transaction: Transaction): string {
      return this.t('transactions.itemCount', transaction.items.length)
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
          <AppListItem align="start" interactive @click="goToDetail(transaction)">
            <span class="flex flex-col gap-1">
              <span class="truncate text-sm font-medium text-text">
                {{ transaction.merchant.name }}
              </span>
              <span
                v-if="locationLabel(transaction.location)"
                class="flex items-center gap-1 text-[13px] text-neutral-600"
              >
                <MapPinIcon class="size-3.5 shrink-0" />
                <span class="truncate">{{ locationLabel(transaction.location) }}</span>
              </span>
              <span
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-neutral-600"
              >
                {{ formatDateTime(transaction.occurred_at) }}
                <AppBadge>{{ paymentMethodLabel(transaction.payment_method) }}</AppBadge>
                <AppBadge v-if="transaction.items.length > 0">
                  {{ itemCountLabel(transaction) }}
                </AppBadge>
              </span>
            </span>

            <template #trailing>
              <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-text">
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
