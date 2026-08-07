<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppCardRow from '@/components/ui/AppCardRow.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { transactionService } from '@/services/transaction'
import { formatDateTime } from '@/utils/datetime'
import type { Transaction } from '@/types/transaction'

export default defineComponent({
  name: 'TransactionDetailView',

  components: {
    AppButton,
    AppSection,
    AppCard,
    AppCardRow,
    AppBadge,
    ConfirmDialog,
  },

  setup() {
    const { t } = useI18n()
    return { t, formatDateTime }
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

  watch: {
    // Detail is the parent of the `transaction-edit` overlay named view, so it
    // doesn't unmount on open. Reload on close so the edited data shows up.
    '$route.name'(name: string | undefined) {
      if (name === 'transaction-detail') {
        void this.loadTransaction()
      }
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
  <div class="flex flex-col gap-6">
    <template v-if="transaction">
      <RouterLink :to="{ name: 'transactions' }" class="text-sm text-accent hover:text-accent-600">
        {{ t('transactions.backToList') }}
      </RouterLink>

      <AppSection :title="transaction.merchant.name">
        <template #actions>
          <div class="flex gap-2">
            <AppButton variant="ghost" @click="goToEdit">{{ t('transactions.edit') }}</AppButton>
            <AppButton variant="danger" @click="requestDelete">
              {{ t('transactions.delete') }}
            </AppButton>
          </div>
        </template>

        <AppCard :padded="false">
          <div class="divide-y divide-divider">
            <AppCardRow :label="t('transactions.totalLabel')">
              <span class="text-base font-semibold text-text">
                {{ transaction.total_amount }} {{ transaction.currency }}
              </span>
            </AppCardRow>
            <AppCardRow :label="t('transactions.dateLabel')">
              <span class="text-sm text-text">{{ formatDateTime(transaction.occurred_at) }}</span>
            </AppCardRow>
            <AppCardRow :label="t('transactions.paymentMethodLabel')">
              <AppBadge>{{ paymentMethodLabel(transaction.payment_method) }}</AppBadge>
            </AppCardRow>
            <AppCardRow
              v-if="transaction.location?.address"
              :label="t('transactions.locationLabel')"
            >
              <span class="text-sm text-text">{{ transaction.location.address }}</span>
            </AppCardRow>
            <AppCardRow v-if="transaction.discount_amount" :label="t('transactions.discountLabel')">
              <span class="text-sm text-text">{{ transaction.discount_amount }}</span>
            </AppCardRow>
          </div>
        </AppCard>
      </AppSection>

      <AppCard :title="t('transactions.itemsTitle')" :padded="false">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="border-b border-divider text-[13px] text-neutral-600">
                <th class="px-5 py-2.5 font-medium">{{ t('transactions.descriptionLabel') }}</th>
                <th class="px-5 py-2.5 font-medium">{{ t('transactions.quantityLabel') }}</th>
                <th class="px-5 py-2.5 font-medium">{{ t('transactions.unitPriceLabel') }}</th>
                <th class="px-5 py-2.5 text-right font-medium">
                  {{ t('transactions.totalLabel') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-divider">
              <tr v-for="(item, index) in transaction.items" :key="index">
                <td class="px-5 py-2.5">{{ item.description }}</td>
                <td class="px-5 py-2.5">{{ item.quantity }} {{ item.unit }}</td>
                <td class="px-5 py-2.5">{{ item.unit_price }}</td>
                <td class="px-5 py-2.5 text-right">
                  {{ lineTotal(item.quantity, item.unit_price) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <ConfirmDialog
        :open="isDeletePending"
        :message="t('transactions.deleteConfirm')"
        :confirm-label="t('transactions.delete')"
        variant="danger"
        @confirm="confirmDelete"
        @cancel="cancelDelete"
      />
    </template>

    <RouterView name="modal" />
  </div>
</template>
