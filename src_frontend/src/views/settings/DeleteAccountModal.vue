<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppModal from '@/components/ui/AppModal.vue'
import ModalFooter from '@/components/ui/ModalFooter.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import FormField from '@/components/ui/FormField.vue'
import { useAuthStore } from '@/stores/auth'
import { accountService } from '@/services/account'
import { ApiError } from '@/types/auth'
import { translateErrorCode } from '@/utils/errors'

export default defineComponent({
  name: 'DeleteAccountModal',

  components: {
    AppModal,
    ModalFooter,
    AppButton,
    AppInput,
    FormField,
  },

  emits: ['close', 'deleted'],

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      currentPassword: '',
      isDeleting: false,
      fieldErrors: {} as Record<string, string[]>,
      generalError: null as string | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    passwordErrors(): string[] {
      return (this.fieldErrors.current_password ?? []).map((code) =>
        translateErrorCode(this.t, code, 'current_password'),
      )
    },

    generalErrorText(): string {
      return this.generalError ? translateErrorCode(this.t, this.generalError) : ''
    },
  },

  methods: {
    async confirmDelete() {
      if (this.currentPassword.length === 0 || this.isDeleting) return

      this.fieldErrors = {}
      this.generalError = null
      this.isDeleting = true
      try {
        await accountService.destroy(this.token as string, {
          current_password: this.currentPassword,
        })
        this.$emit('deleted')
      } catch (error) {
        if (error instanceof ApiError) {
          this.fieldErrors = error.errors ?? {}
          this.generalError = error.errors ? null : error.message
        } else {
          this.generalError = 'network.connection_failed'
        }
      } finally {
        this.isDeleting = false
      }
    },
  },
})
</script>

<template>
  <AppModal
    :title="t('profile.deleteModalTitle')"
    :description="t('profile.deleteModalDescription')"
    @close="$emit('close')"
  >
    <form id="delete-account-form" class="flex flex-col gap-4" @submit.prevent="confirmDelete">
      <p class="rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[13px] text-text">
        {{ t('profile.deleteModalWarning') }}
      </p>

      <FormField
        id="delete-account-password"
        v-slot="{ describedBy }"
        :label="t('profile.currentPasswordLabel')"
        :hint="t('profile.deleteModalPasswordHint')"
        :errors="passwordErrors"
      >
        <AppInput
          id="delete-account-password"
          v-model="currentPassword"
          type="password"
          autocomplete="current-password"
          :invalid="passwordErrors.length > 0"
          :aria-describedby="describedBy"
        />
      </FormField>

      <p v-if="generalErrorText" class="text-sm text-danger-700">{{ generalErrorText }}</p>
    </form>

    <template #footer>
      <ModalFooter>
        <AppButton variant="ghost" :disabled="isDeleting" @click="$emit('close')">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton
          type="submit"
          form="delete-account-form"
          variant="danger"
          :disabled="currentPassword.length === 0 || isDeleting"
        >
          {{ isDeleting ? t('profile.deleting') : t('profile.deleteConfirm') }}
        </AppButton>
      </ModalFooter>
    </template>
  </AppModal>
</template>
