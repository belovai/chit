<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppSlideOver from '@/components/ui/AppSlideOver.vue'
import ModalFooter from '@/components/ui/ModalFooter.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import RadioCardGroup, { type RadioCardOption } from '@/components/ui/RadioCardGroup.vue'
import AiSettingFieldInput from '@/components/settings/AiSettingField.vue'
import { useModalRoute } from '@/composables/useModalRoute'
import { useAuthStore } from '@/stores/auth'
import { aiService } from '@/services/ai'
import { ApiError } from '@/types/auth'
import { translateApiMessage } from '@/utils/aiErrors'
import type { AiCredential, AiCredentialPayload, AiProvider, AiSettingField } from '@/types/ai'

function defaultSettings(provider: AiProvider): Record<string, unknown> {
  return Object.fromEntries(provider.settings.map((field) => [field.key, field.default]))
}

export default defineComponent({
  name: 'AiCredentialFormSlideOver',

  components: {
    AppButton,
    AppSlideOver,
    ModalFooter,
    ConfirmDialog,
    FormField,
    AppInput,
    AppSelect,
    RadioCardGroup,
    AiSettingFieldInput,
  },

  beforeRouteLeave(to, from, next) {
    if (!this.isDirty) {
      next()
      return
    }
    this.pendingLeave = next
    this.showUnsavedConfirm = true
  },

  setup() {
    const { t } = useI18n()
    const { close } = useModalRoute('settings-ai')
    return { t, close }
  },

  data() {
    return {
      providers: [] as AiProvider[],
      credential: null as AiCredential | null,

      providerId: '',
      label: '',
      apiKey: '',
      model: '',
      settings: {} as Record<string, unknown>,

      isLoading: false,
      isSaving: false,
      fieldErrors: {} as Record<string, string[]>,
      generalError: null as string | null,

      isDirty: false,
      showUnsavedConfirm: false,
      pendingLeave: null as null | ((allow?: boolean) => void),
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hashId(): string | undefined {
      return this.$route.params.hashId as string | undefined
    },

    isEditMode(): boolean {
      return this.hashId !== undefined
    },

    selectedProvider(): AiProvider | null {
      return this.providers.find((provider) => provider.id === this.providerId) ?? null
    },

    settingFields(): AiSettingField[] {
      return this.selectedProvider?.settings ?? []
    },

    modelOptions(): RadioCardOption[] {
      return (this.selectedProvider?.models ?? []).map((model) => ({
        value: model.id,
        label: model.label,
        description: [
          this.t('ai.pricing', {
            input: model.pricing.input.toFixed(2),
            output: model.pricing.output.toFixed(2),
          }),
          ...model.capabilities.map((capability) => this.t(`ai.capabilities.${capability}`)),
        ].join(' · '),
      }))
    },

    canSubmit(): boolean {
      if (this.isSaving || this.label.trim() === '' || this.model === '') {
        return false
      }
      // The key is required to create, optional to edit — an empty field on
      // edit means "keep the stored key".
      return this.isEditMode || this.apiKey.trim() !== ''
    },
  },

  async mounted() {
    this.isLoading = true
    try {
      this.providers = await aiService.providers(this.token as string)

      if (this.isEditMode) {
        const all = await aiService.list(this.token as string)
        this.credential = all.find((item) => item.id === this.hashId) ?? null

        if (this.credential) {
          this.providerId = this.credential.provider
          this.label = this.credential.label
          this.model = this.credential.model
          this.settings = this.mergedSettings(this.credential.settings)
        }
      } else {
        this.providerId = this.providers[0]?.id ?? ''
        this.settings = this.selectedProvider ? defaultSettings(this.selectedProvider) : {}
      }
    } finally {
      this.isLoading = false
    }
  },

  methods: {
    // A provider may have gained a setting since this credential was stored,
    // so every schema key gets a value: the stored one, or the default.
    mergedSettings(stored: Record<string, unknown>): Record<string, unknown> {
      const provider = this.selectedProvider
      if (!provider) {
        return { ...stored }
      }
      return Object.fromEntries(
        provider.settings.map((field) => [
          field.key,
          Object.prototype.hasOwnProperty.call(stored, field.key)
            ? stored[field.key]
            : field.default,
        ]),
      )
    },

    // Key sets are provider-specific: carrying values across would submit
    // fields the target provider rejects.
    onProviderChange(value: string) {
      this.providerId = value
      this.model = ''
      this.settings = this.selectedProvider ? defaultSettings(this.selectedProvider) : {}
      this.isDirty = true
    },

    onSettingChange(key: string, value: unknown) {
      this.settings = { ...this.settings, [key]: value }
      this.isDirty = true
    },

    fieldErrorsFor(field: string): string[] {
      return (this.fieldErrors[field] ?? []).map((message) =>
        translateApiMessage(this.t, message, field),
      )
    },

    settingErrorsFor(key: string): string[] {
      return this.fieldErrorsFor(`settings.${key}`)
    },

    requestClose() {
      if (this.isDirty) {
        this.showUnsavedConfirm = true
        return
      }
      this.close()
    },

    confirmDiscard() {
      this.showUnsavedConfirm = false
      this.isDirty = false
      if (this.pendingLeave) {
        const leave = this.pendingLeave
        this.pendingLeave = null
        leave()
        return
      }
      this.close()
    },

    cancelDiscard() {
      this.showUnsavedConfirm = false
      if (this.pendingLeave) {
        const leave = this.pendingLeave
        this.pendingLeave = null
        leave(false)
      }
    },

    async onSubmit() {
      this.fieldErrors = {}
      this.generalError = null
      this.isSaving = true

      try {
        if (this.isEditMode) {
          const payload: Partial<AiCredentialPayload> = {
            label: this.label,
            model: this.model,
            settings: this.settings,
          }
          // Provider is immutable server-side, and an untouched key field
          // must not overwrite the stored key.
          if (this.apiKey.trim() !== '') {
            payload.api_key = this.apiKey.trim()
          }
          await aiService.update(this.token as string, this.hashId as string, payload)
        } else {
          await aiService.create(this.token as string, {
            provider: this.providerId,
            label: this.label,
            api_key: this.apiKey.trim(),
            model: this.model,
            settings: this.settings,
          })
        }

        this.isDirty = false
        this.close()
      } catch (error) {
        if (error instanceof ApiError) {
          this.fieldErrors = error.errors ?? {}
          this.generalError = error.errors ? null : translateApiMessage(this.t, error.message)
        } else {
          this.generalError = this.t('network.connection_failed')
        }
      } finally {
        this.isSaving = false
      }
    },
  },
})
</script>

<template>
  <AppSlideOver :title="isEditMode ? t('ai.editTitle') : t('ai.createTitle')" @close="requestClose">
    <div v-if="isLoading" class="flex flex-col gap-3">
      <div class="h-4 w-24 animate-pulse rounded-sm bg-surface"></div>
      <div class="h-9 w-full animate-pulse rounded-md bg-surface"></div>
    </div>

    <form v-else id="ai-credential-form" class="flex flex-col gap-4" @submit.prevent="onSubmit">
      <FormField
        v-if="providers.length > 1"
        id="ai-provider"
        v-slot="{ describedBy }"
        :label="t('ai.providerLabel')"
        :hint="t('ai.providerHint')"
        :errors="fieldErrorsFor('provider')"
      >
        <AppSelect
          id="ai-provider"
          :model-value="providerId"
          :disabled="isEditMode"
          :invalid="fieldErrorsFor('provider').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="onProviderChange"
        >
          <option v-for="provider in providers" :key="provider.id" :value="provider.id">
            {{ provider.label }}
          </option>
        </AppSelect>
      </FormField>

      <p v-else class="text-[13px] text-neutral-600">
        {{ t('ai.providerLabel') }}: {{ selectedProvider?.label ?? providerId }}
      </p>

      <FormField
        id="ai-label"
        v-slot="{ describedBy }"
        :label="t('ai.labelLabel')"
        :hint="t('ai.labelHint')"
        :errors="fieldErrorsFor('label')"
      >
        <AppInput
          id="ai-label"
          :model-value="label"
          :invalid="fieldErrorsFor('label').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="
            (value: string) => {
              label = value
              isDirty = true
            }
          "
        />
      </FormField>

      <FormField
        id="ai-api-key"
        v-slot="{ describedBy }"
        :label="t('ai.apiKeyLabel')"
        :hint="isEditMode ? t('ai.apiKeyEditHint') : t('ai.apiKeyCreateHint')"
        :errors="fieldErrorsFor('api_key')"
      >
        <AppInput
          id="ai-api-key"
          type="password"
          autocomplete="off"
          :model-value="apiKey"
          :placeholder="isEditMode ? credential?.masked_key : undefined"
          :invalid="fieldErrorsFor('api_key').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="
            (value: string) => {
              apiKey = value
              isDirty = true
            }
          "
        />
      </FormField>

      <FormField id="ai-model" :label="t('ai.modelLabel')" :errors="fieldErrorsFor('model')">
        <RadioCardGroup
          name="ai-model"
          :model-value="model"
          :options="modelOptions"
          @update:model-value="
            (value: string) => {
              model = value
              isDirty = true
            }
          "
        />
      </FormField>

      <section v-if="settingFields.length > 0" class="flex flex-col gap-4">
        <h3 class="text-sm font-semibold text-text">{{ t('ai.settingsTitle') }}</h3>

        <AiSettingFieldInput
          v-for="field in settingFields"
          :key="field.key"
          :field="field"
          :model-value="settings[field.key]"
          :errors="settingErrorsFor(field.key)"
          @update:model-value="(value: unknown) => onSettingChange(field.key, value)"
        />
      </section>

      <p v-if="generalError" class="text-sm text-danger-700">{{ generalError }}</p>
    </form>

    <template #footer>
      <ModalFooter>
        <AppButton type="button" variant="ghost" @click="requestClose">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton type="submit" form="ai-credential-form" :disabled="!canSubmit">
          {{ isSaving ? t('ai.verifying') : isEditMode ? t('ai.save') : t('ai.create') }}
        </AppButton>
      </ModalFooter>
    </template>
  </AppSlideOver>

  <ConfirmDialog
    :open="showUnsavedConfirm"
    :title="t('overlay.unsavedTitle')"
    :message="t('overlay.unsavedMessage')"
    :confirm-label="t('overlay.unsavedConfirm')"
    :cancel-label="t('overlay.unsavedCancel')"
    variant="danger"
    @confirm="confirmDiscard"
    @cancel="cancelDiscard"
  />
</template>
