<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppListItem from '@/components/ui/AppListItem.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { aiService } from '@/services/ai'
import { translateApiMessage } from '@/utils/aiErrors'
import type { AiCredential, AiCredentialStatus } from '@/types/ai'

export default defineComponent({
  name: 'SettingsAiView',

  components: {
    AppSection,
    AppCard,
    AppEmptyState,
    AppButton,
    AppListItem,
    AppBadge,
    ConfirmDialog,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      credentials: [] as AiCredential[],
      isLoading: false,
      busyId: null as string | null,
      busyAction: null as 'activate' | 'verify' | null,
      credentialPendingDelete: null as AiCredential | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),
  },

  watch: {
    // The list is the parent of the named view, so it doesn't unmount while
    // the overlay is open. Reload when the overlay closes.
    '$route.name'(name: string | undefined) {
      if (name === 'settings-ai') {
        void this.loadCredentials()
      }
    },
  },

  async mounted() {
    await this.loadCredentials()
  },

  methods: {
    async loadCredentials() {
      this.isLoading = true
      try {
        this.credentials = await aiService.list(this.token as string)
      } finally {
        this.isLoading = false
      }
    },

    statusVariant(status: AiCredentialStatus): 'neutral' | 'success' | 'warning' | 'danger' {
      if (status === 'verified') return 'success'
      if (status === 'failing') return 'warning'
      if (status === 'disabled') return 'danger'
      return 'neutral'
    },

    statusLabel(status: AiCredentialStatus): string {
      const keys: Record<AiCredentialStatus, string> = {
        pending: 'ai.statusPending',
        verified: 'ai.statusVerified',
        failing: 'ai.statusFailing',
        disabled: 'ai.statusDisabled',
      }
      return this.t(keys[status])
    },

    formatDate(value: string | null): string | null {
      return value === null ? null : new Date(value).toLocaleString()
    },

    // Only a verified credential is usable, so only a verified credential is
    // worth activating; the way back from failing/disabled is Verify.
    canActivate(credential: AiCredential): boolean {
      return !credential.is_active && credential.status === 'verified'
    },

    isBusy(credential: AiCredential, action: 'activate' | 'verify'): boolean {
      return this.busyId === credential.id && this.busyAction === action
    },

    async onActivate(credential: AiCredential) {
      this.busyId = credential.id
      this.busyAction = 'activate'
      try {
        await aiService.activate(this.token as string, credential.id)
        // The previously active credential changed too, so reload everything.
        await this.loadCredentials()
      } finally {
        this.busyId = null
        this.busyAction = null
      }
    },

    async onVerify(credential: AiCredential) {
      this.busyId = credential.id
      this.busyAction = 'verify'
      try {
        const updated = await aiService.verify(this.token as string, credential.id)
        const index = this.credentials.findIndex((item) => item.id === credential.id)
        this.credentials.splice(index, 1, updated)
      } finally {
        this.busyId = null
        this.busyAction = null
      }
    },

    requestDelete(credential: AiCredential) {
      this.credentialPendingDelete = credential
    },

    cancelDelete() {
      this.credentialPendingDelete = null
    },

    async confirmDelete() {
      const credential = this.credentialPendingDelete
      if (!credential) {
        return
      }
      await aiService.destroy(this.token as string, credential.id)
      this.credentials = this.credentials.filter((item) => item.id !== credential.id)
      this.credentialPendingDelete = null
    },

    errorMessage(credential: AiCredential): string | null {
      return credential.last_error === null
        ? null
        : translateApiMessage(this.t, credential.last_error)
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppSection :title="t('ai.title')" :description="t('ai.description')">
      <template #actions>
        <AppButton @click="$router.push({ name: 'settings-ai-new' })">
          {{ t('ai.addKey') }}
        </AppButton>
      </template>
    </AppSection>

    <AppCard :padded="false">
      <AppEmptyState
        v-if="!isLoading && credentials.length === 0"
        :title="t('ai.emptyState')"
        :description="t('ai.emptyStateDescription')"
      >
        <template #action>
          <AppButton @click="$router.push({ name: 'settings-ai-new' })">
            {{ t('ai.addKey') }}
          </AppButton>
        </template>
      </AppEmptyState>

      <ul v-else class="divide-y divide-divider">
        <li v-for="credential in credentials" :key="credential.id">
          <AppListItem align="start">
            <span class="flex flex-col gap-1">
              <span class="flex flex-wrap items-center gap-2">
                <span class="truncate text-sm font-medium text-text">{{ credential.label }}</span>
                <AppBadge v-if="credential.is_active" variant="accent">
                  {{ t('ai.active') }}
                </AppBadge>
                <AppBadge :variant="statusVariant(credential.status)">
                  {{ statusLabel(credential.status) }}
                </AppBadge>
              </span>

              <span class="text-[13px] text-neutral-600">
                {{ credential.provider }} · {{ credential.model }} · {{ credential.masked_key }}
              </span>

              <span class="text-[13px] text-neutral-600">
                <template v-if="credential.last_verified_at">
                  {{ t('ai.lastVerifiedAt', { date: formatDate(credential.last_verified_at) }) }}
                </template>
                <template v-if="credential.last_verified_at && credential.last_used_at">
                  ·
                </template>
                <template v-if="credential.last_used_at">
                  {{ t('ai.lastUsedAt', { date: formatDate(credential.last_used_at) }) }}
                </template>
              </span>

              <span v-if="errorMessage(credential)" class="text-[13px] text-danger-700">
                {{ errorMessage(credential) }}
              </span>
            </span>

            <template #trailing>
              <span class="flex flex-wrap gap-2">
                <AppButton
                  v-if="canActivate(credential)"
                  size="sm"
                  :disabled="isBusy(credential, 'activate')"
                  @click="onActivate(credential)"
                >
                  {{ isBusy(credential, 'activate') ? t('ai.activating') : t('ai.activate') }}
                </AppButton>
                <AppButton
                  variant="ghost"
                  size="sm"
                  :disabled="isBusy(credential, 'verify')"
                  @click="onVerify(credential)"
                >
                  {{ isBusy(credential, 'verify') ? t('ai.verifyingShort') : t('ai.verify') }}
                </AppButton>
                <AppButton
                  variant="ghost"
                  size="sm"
                  @click="
                    $router.push({ name: 'settings-ai-edit', params: { hashId: credential.id } })
                  "
                >
                  {{ t('ai.edit') }}
                </AppButton>
                <AppButton variant="ghost" size="sm" @click="requestDelete(credential)">
                  {{ t('ai.delete') }}
                </AppButton>
              </span>
            </template>
          </AppListItem>
        </li>
      </ul>
    </AppCard>

    <RouterView name="modal" />

    <ConfirmDialog
      :open="credentialPendingDelete !== null"
      :message="t('ai.deleteConfirm')"
      :confirm-label="t('ai.delete')"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="cancelDelete"
    />
  </div>
</template>
