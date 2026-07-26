<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import { ChevronRightIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { merchantService } from '@/services/merchant'
import type { Merchant, MerchantLocation } from '@/types/merchant'

export default defineComponent({
  name: 'SettingsMerchantsView',

  components: {
    ChevronRightIcon,
    ChevronDownIcon,
    ConfirmDialog,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      merchants: [] as Merchant[],
      isLoading: false,
      expandedHashId: null as string | null,
      locationsByMerchant: {} as Record<string, MerchantLocation[]>,
      locationsLoading: null as string | null,
      merchantPendingDelete: null as Merchant | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),
  },

  async mounted() {
    await this.loadMerchants()
  },

  methods: {
    async loadMerchants() {
      this.isLoading = true
      try {
        this.merchants = await merchantService.list(this.token as string)
      } finally {
        this.isLoading = false
      }
    },

    async toggleExpand(merchant: Merchant) {
      if (this.expandedHashId === merchant.hash_id) {
        this.expandedHashId = null
        return
      }

      this.expandedHashId = merchant.hash_id

      if (this.locationsByMerchant[merchant.hash_id]) return

      this.locationsLoading = merchant.hash_id
      try {
        this.locationsByMerchant[merchant.hash_id] = await merchantService.listLocations(
          this.token as string,
          merchant.hash_id,
        )
      } finally {
        this.locationsLoading = null
      }
    },

    requestDeleteMerchant(merchant: Merchant) {
      this.merchantPendingDelete = merchant
    },

    cancelDeleteMerchant() {
      this.merchantPendingDelete = null
    },

    async confirmDeleteMerchant() {
      const merchant = this.merchantPendingDelete
      if (!merchant) {
        return
      }
      await merchantService.destroy(this.token as string, merchant.hash_id)
      this.merchants = this.merchants.filter((item) => item.hash_id !== merchant.hash_id)
      this.merchantPendingDelete = null
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl">{{ t('merchants.title') }}</h1>
      <RouterLink
        :to="{ name: 'settings-merchant-new' }"
        class="border font-[family-name:var(--font-heading)] font-semibold px-4 py-2.5 text-sm transition-colors bg-accent border-accent text-neutral-100 hover:bg-accent-600 hover:border-accent-600"
      >
        {{ t('merchants.addMerchant') }}
      </RouterLink>
    </div>

    <p v-if="!isLoading && merchants.length === 0" class="text-sm text-neutral-600">
      {{ t('merchants.emptyState') }}
    </p>

    <ul v-else class="flex flex-col divide-y divide-divider border border-divider bg-surface">
      <li v-for="merchant in merchants" :key="merchant.hash_id" class="flex flex-col">
        <div class="flex items-start justify-between gap-4 px-4 py-3">
          <button
            type="button"
            class="flex flex-1 items-start gap-2 text-left"
            @click="toggleExpand(merchant)"
          >
            <ChevronDownIcon
              v-if="expandedHashId === merchant.hash_id"
              class="mt-0.5 h-4 w-4 shrink-0"
            />
            <ChevronRightIcon v-else class="mt-0.5 h-4 w-4 shrink-0" />
            <span class="flex flex-col">
              <span class="text-sm font-semibold text-text">{{ merchant.name }}</span>
              <span v-if="(merchant.locations_count ?? 0) > 0" class="text-xs text-neutral-600">
                {{ t('merchants.locationsCount', { count: merchant.locations_count }) }}
              </span>
            </span>
          </button>
          <div class="flex shrink-0 items-center gap-4">
            <RouterLink
              :to="{ name: 'settings-merchant-edit', params: { hashId: merchant.hash_id } }"
              class="text-sm text-accent hover:text-accent-600"
            >
              {{ t('merchants.editLink') }}
            </RouterLink>
            <button
              v-if="(merchant.locations_count ?? 0) === 0"
              type="button"
              class="text-sm text-danger-700 hover:text-danger"
              @click="requestDeleteMerchant(merchant)"
            >
              {{ t('merchants.deleteMerchant') }}
            </button>
          </div>
        </div>

        <div v-if="expandedHashId === merchant.hash_id" class="bg-bg px-4 py-3 pl-10">
          <p v-if="locationsLoading === merchant.hash_id" class="text-xs text-neutral-600">
            {{ t('merchants.saving') }}
          </p>
          <ul
            v-else-if="(locationsByMerchant[merchant.hash_id] ?? []).length > 0"
            class="flex flex-col gap-1"
          >
            <li
              v-for="location in locationsByMerchant[merchant.hash_id]"
              :key="location.hash_id"
              class="text-xs text-neutral-700"
            >
              {{ location.is_online ? t('merchants.isOnlineLabel') : location.address }}
            </li>
          </ul>
          <p v-else class="text-xs text-neutral-600">{{ t('merchants.noLocations') }}</p>
        </div>
      </li>
    </ul>

    <ConfirmDialog
      :open="merchantPendingDelete !== null"
      :message="t('merchants.deleteMerchantConfirm')"
      :confirm-label="t('merchants.deleteMerchant')"
      variant="danger"
      @confirm="confirmDeleteMerchant"
      @cancel="cancelDeleteMerchant"
    />
  </div>
</template>
