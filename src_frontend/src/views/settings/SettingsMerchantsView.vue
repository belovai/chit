<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  ChevronRightIcon,
  ChevronDownIcon,
  GlobeAltIcon,
  MapPinIcon,
  ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppListItem from '@/components/ui/AppListItem.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppButton from '@/components/ui/AppButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { merchantService } from '@/services/merchant'
import type { Merchant, MerchantLocation } from '@/types/merchant'

export default defineComponent({
  name: 'SettingsMerchantsView',

  components: {
    ChevronRightIcon,
    ChevronDownIcon,
    GlobeAltIcon,
    MapPinIcon,
    ArrowTopRightOnSquareIcon,
    AppSection,
    AppCard,
    AppBadge,
    AppListItem,
    AppEmptyState,
    AppButton,
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
      expandedHashIds: [] as string[],
      locationsByMerchant: {} as Record<string, MerchantLocation[]>,
      loadingHashIds: [] as string[],
      merchantPendingDelete: null as Merchant | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),
  },

  watch: {
    // The list is the parent of the named view, so it doesn't unmount when
    // the overlay opens. Reload on close (returning to the `settings-merchants`
    // route) so a merchant created/edited in the overlay shows up.
    '$route.name'(name: string | undefined) {
      if (name === 'settings-merchants') {
        this.locationsByMerchant = {}
        this.expandedHashIds = []
        void this.loadMerchants()
      }
    },
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

    isExpanded(hashId: string): boolean {
      return this.expandedHashIds.includes(hashId)
    },

    isLoadingLocations(hashId: string): boolean {
      return this.loadingHashIds.includes(hashId)
    },

    async toggleExpand(merchant: Merchant) {
      if (this.isExpanded(merchant.hash_id)) {
        this.expandedHashIds = this.expandedHashIds.filter((id) => id !== merchant.hash_id)
        return
      }

      this.expandedHashIds = [...this.expandedHashIds, merchant.hash_id]

      if (this.locationsByMerchant[merchant.hash_id]) return

      this.loadingHashIds = [...this.loadingHashIds, merchant.hash_id]
      try {
        this.locationsByMerchant[merchant.hash_id] = await merchantService.listLocations(
          this.token as string,
          merchant.hash_id,
        )
      } finally {
        this.loadingHashIds = this.loadingHashIds.filter((id) => id !== merchant.hash_id)
      }
    },

    hasCoordinates(location: MerchantLocation): boolean {
      return location.latitude !== null && location.longitude !== null
    },

    formatCoordinates(location: MerchantLocation): string {
      return `${Number(location.latitude).toFixed(6)}, ${Number(location.longitude).toFixed(6)}`
    },

    // Coordinates are more precise, so they take priority over the address;
    // for address-only locations we fall back to a text search.
    mapsHref(location: MerchantLocation): string | null {
      if (this.hasCoordinates(location)) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
          `${location.latitude},${location.longitude}`,
        )}`
      }

      if (location.address) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location.address)}`
      }

      return null
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
    <AppSection :title="t('merchants.title')">
      <template #actions>
        <AppButton @click="$router.push({ name: 'settings-merchant-new' })">
          {{ t('merchants.addMerchant') }}
        </AppButton>
      </template>
    </AppSection>

    <AppCard :padded="false">
      <AppEmptyState
        v-if="!isLoading && merchants.length === 0"
        :title="t('merchants.emptyState')"
      />

      <ul v-else class="divide-y divide-divider">
        <li v-for="merchant in merchants" :key="merchant.hash_id">
          <AppListItem>
            <template #leading>
              <button
                type="button"
                class="flex cursor-pointer items-center rounded-md p-1 text-neutral-600 hover:bg-surface hover:text-text"
                :aria-expanded="isExpanded(merchant.hash_id)"
                :aria-label="merchant.name"
                @click="toggleExpand(merchant)"
              >
                <ChevronDownIcon
                  v-if="isExpanded(merchant.hash_id)"
                  class="h-4 w-4"
                  aria-hidden="true"
                />
                <ChevronRightIcon v-else class="h-4 w-4" aria-hidden="true" />
              </button>
            </template>

            <span class="flex flex-col">
              <span class="truncate text-sm font-medium text-text">{{ merchant.name }}</span>
              <span v-if="(merchant.locations_count ?? 0) > 0" class="text-[13px] text-neutral-600">
                {{ t('merchants.locationsCount', { count: merchant.locations_count }) }}
              </span>
            </span>

            <template #trailing>
              <RouterLink
                :to="{ name: 'settings-merchant-edit', params: { hashId: merchant.hash_id } }"
                class="text-sm text-accent hover:text-accent-600"
              >
                {{ t('merchants.editLink') }}
              </RouterLink>
              <button
                v-if="(merchant.locations_count ?? 0) === 0"
                type="button"
                class="cursor-pointer text-sm text-danger-700 hover:text-danger"
                @click="requestDeleteMerchant(merchant)"
              >
                {{ t('merchants.deleteMerchant') }}
              </button>
            </template>
          </AppListItem>

          <div v-if="isExpanded(merchant.hash_id)" class="border-t border-divider bg-surface">
            <p
              v-if="isLoadingLocations(merchant.hash_id)"
              class="px-5 py-2.5 pl-14 text-[13px] text-neutral-600"
            >
              {{ t('merchants.loadingLocations') }}
            </p>
            <ul
              v-else-if="(locationsByMerchant[merchant.hash_id] ?? []).length > 0"
              class="divide-y divide-divider"
            >
              <li
                v-for="location in locationsByMerchant[merchant.hash_id]"
                :key="location.hash_id"
                class="flex items-center gap-3 px-5 py-2.5 pl-14"
              >
                <GlobeAltIcon
                  v-if="location.is_online"
                  class="h-4 w-4 shrink-0 text-neutral-500"
                  aria-hidden="true"
                />
                <MapPinIcon v-else class="h-4 w-4 shrink-0 text-neutral-500" aria-hidden="true" />

                <span class="min-w-0 flex-1">
                  <AppBadge v-if="location.is_online" variant="accent">
                    {{ t('merchants.onlineBadge') }}
                  </AppBadge>
                  <span v-else-if="location.address" class="block truncate text-[13px] text-text">
                    {{ location.address }}
                  </span>
                  <span
                    v-else-if="hasCoordinates(location)"
                    class="block font-mono text-[13px] text-neutral-600"
                  >
                    {{ formatCoordinates(location) }}
                  </span>
                  <span v-else class="block text-[13px] text-neutral-500">&mdash;</span>
                </span>

                <a
                  v-if="!location.is_online && mapsHref(location)"
                  :href="mapsHref(location) ?? undefined"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="shrink-0 rounded-md p-1 text-neutral-500 hover:bg-panel hover:text-accent"
                  :aria-label="t('merchants.openInMaps')"
                  :title="t('merchants.openInMaps')"
                >
                  <ArrowTopRightOnSquareIcon class="h-4 w-4" aria-hidden="true" />
                </a>
              </li>
            </ul>
            <p v-else class="px-5 py-2.5 pl-14 text-[13px] text-neutral-600">
              {{ t('merchants.noLocations') }}
            </p>
          </div>
        </li>
      </ul>
    </AppCard>

    <ConfirmDialog
      :open="merchantPendingDelete !== null"
      :message="t('merchants.deleteMerchantConfirm')"
      :confirm-label="t('merchants.deleteMerchant')"
      variant="danger"
      @confirm="confirmDeleteMerchant"
      @cancel="cancelDeleteMerchant"
    />

    <RouterView name="modal" />
  </div>
</template>
