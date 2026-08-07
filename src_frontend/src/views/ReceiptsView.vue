<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppListItem from '@/components/ui/AppListItem.vue'
import AppBadge, { type BadgeVariant } from '@/components/ui/AppBadge.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import UploadActionSheet, { type UploadAction } from '@/components/layout/UploadActionSheet.vue'
import { useAuthStore } from '@/stores/auth'
import { receiptService } from '@/services/receipt'
import { useRunPolling } from '@/composables/useRunPolling'
import { formatRelativeFromIso } from '@/utils/datetime'
import { isReceiptSettled, type DocType, type Receipt, type ReceiptStatus } from '@/types/receipt'

const STATUS_VARIANTS: Record<ReceiptStatus, BadgeVariant> = {
  pending: 'neutral',
  processing: 'neutral',
  needs_review: 'warning',
  approved: 'success',
  rejected: 'danger',
  failed: 'danger',
  canceled: 'neutral',
}

export default defineComponent({
  name: 'ReceiptsView',

  components: {
    AppButton,
    AppSection,
    AppCard,
    AppEmptyState,
    AppListItem,
    AppBadge,
    AppSelect,
    UploadActionSheet,
  },

  setup() {
    const { t } = useI18n()
    return { t, formatRelativeFromIso, isReceiptSettled }
  },

  data() {
    return {
      isMenuOpen: false,
      receipts: [] as Receipt[],
      currentPage: 1,
      lastPage: 1,
      isLoading: false,
      isUploading: false,
      uploadError: null as string | null,
      docTypeHint: '' as DocType | '',
      capturePhoto: false,
      polling: null as ReturnType<typeof useRunPolling> | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hasMore(): boolean {
      return this.currentPage < this.lastPage
    },

    hasMovingReceipt(): boolean {
      return this.receipts.some((receipt) => !isReceiptSettled(receipt.status))
    },
  },

  async mounted() {
    this.polling = useRunPolling(
      () => this.refreshFirstPage(),
      () => this.hasMovingReceipt,
    )
    await this.loadPage(1)
    this.polling.start()
  },

  methods: {
    statusVariant(status: ReceiptStatus): BadgeVariant {
      return STATUS_VARIANTS[status]
    },

    async loadPage(page: number) {
      this.isLoading = true
      try {
        const result = await receiptService.list(this.token as string, { page })
        this.receipts = page === 1 ? result.data : [...this.receipts, ...result.data]
        this.currentPage = result.currentPage
        this.lastPage = result.lastPage
      } finally {
        this.isLoading = false
      }
    },

    // Poll refresh only replaces the first page, so "load more" results survive.
    async refreshFirstPage() {
      const result = await receiptService.list(this.token as string, { page: 1 })
      const fresh = new Map(result.data.map((receipt) => [receipt.hash_id, receipt]))
      this.receipts = this.receipts.map((receipt) => fresh.get(receipt.hash_id) ?? receipt)
      for (const receipt of result.data) {
        if (!this.receipts.some((existing) => existing.hash_id === receipt.hash_id)) {
          this.receipts = [receipt, ...this.receipts]
        }
      }
    },

    toggleMenu() {
      this.isMenuOpen = !this.isMenuOpen
    },
    closeMenu() {
      this.isMenuOpen = false
    },

    onSelect(action: UploadAction) {
      this.closeMenu()
      if (action === 'manual') {
        this.$router.push({ name: 'transaction-new' })
        return
      }
      this.capturePhoto = action === 'photo'
      const input = this.$refs.fileInput as HTMLInputElement
      input.value = ''
      input.click()
    },

    async onFileChange(event: Event) {
      const input = event.target as HTMLInputElement
      const file = input.files?.[0]
      if (!file) return

      this.uploadError = null
      this.isUploading = true
      try {
        await receiptService.upload(
          this.token as string,
          file,
          this.docTypeHint === '' ? undefined : this.docTypeHint,
        )
        await this.$router.push({ name: 'receipts' })
        await this.loadPage(1)
        this.polling?.start()
      } catch {
        this.uploadError = this.t('receipts.upload.failed')
      } finally {
        this.isUploading = false
      }
    },

    review(receipt: Receipt) {
      this.$router.push({ name: 'receipt-review', params: { hashId: receipt.hash_id } })
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppSection :title="t('nav.receipts')">
      <template #actions>
        <div class="flex items-center gap-2">
          <AppSelect
            id="receipt-doc-type-hint"
            v-model="docTypeHint"
            class="hidden w-40 md:block"
            :disabled="isUploading"
          >
            <option value="">{{ t('receipts.upload.hintLabel') }}</option>
            <option value="receipt">{{ t('receipts.docType.receipt') }}</option>
            <option value="utility_bill">{{ t('receipts.docType.utility_bill') }}</option>
          </AppSelect>

          <div class="relative hidden md:block">
            <AppButton :disabled="isUploading" @click="toggleMenu">
              {{ isUploading ? t('receipts.upload.uploading') : t('receipts.newReceipt') }}
            </AppButton>
            <div v-if="isMenuOpen" class="fixed inset-0 z-10" @click="closeMenu"></div>
            <UploadActionSheet v-if="isMenuOpen" variant="dropdown" @select="onSelect" />
          </div>
        </div>
      </template>

      <input
        ref="fileInput"
        type="file"
        accept="image/*,application/pdf"
        class="hidden"
        :capture="capturePhoto ? 'environment' : undefined"
        @change="onFileChange"
      />

      <p v-if="uploadError" class="text-sm text-danger-700">{{ uploadError }}</p>
    </AppSection>

    <AppCard :padded="false">
      <AppEmptyState
        v-if="receipts.length === 0 && !isLoading"
        :title="t('receipts.placeholder')"
      />

      <ul v-else class="divide-y divide-divider">
        <li v-for="receipt in receipts" :key="receipt.hash_id">
          <AppListItem>
            <div class="flex w-full flex-col gap-2 md:flex-row md:items-center md:gap-4">
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ receipt.original_filename }}</p>
                <span class="text-[11px] text-neutral-500">
                  {{ formatRelativeFromIso(receipt.created_at) }}
                </span>
              </div>

              <div class="flex items-center gap-1">
                <AppBadge :variant="statusVariant(receipt.status)">
                  {{ t(`receipts.status.${receipt.status}`) }}
                </AppBadge>
                <AppBadge v-if="receipt.doc_type" variant="neutral">
                  {{ t(`receipts.docType.${receipt.doc_type}`) }}
                </AppBadge>
              </div>

              <div class="shrink-0">
                <AppButton
                  v-if="receipt.status !== 'pending' && receipt.status !== 'processing'"
                  size="sm"
                  :variant="receipt.status === 'needs_review' ? 'primary' : 'ghost'"
                  @click="review(receipt)"
                >
                  {{
                    receipt.status === 'needs_review'
                      ? t('receipts.review.title')
                      : t('receipts.view')
                  }}
                </AppButton>
              </div>
            </div>
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
          {{ t('receipts.loadMore') }}
        </AppButton>
      </template>
    </AppCard>
  </div>
</template>
