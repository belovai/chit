<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import ModalFooter from '@/components/ui/ModalFooter.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useModalRoute } from '@/composables/useModalRoute'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/services/product'
import { ApiError } from '@/types/auth'
import { translateErrorCode } from '@/utils/errors'
import type { Product } from '@/types/product'

export default defineComponent({
  name: 'ProductFormModal',

  components: {
    AppButton,
    AppModal,
    ModalFooter,
    ConfirmDialog,
    FormField,
    AppInput,
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
    const { close } = useModalRoute('settings-products')
    return { t, translateErrorCode, close }
  },

  data() {
    return {
      product: null as Product | null,
      name: '',
      isLoadingProduct: false,
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
  },

  async mounted() {
    if (this.isEditMode) {
      await this.loadProduct()
    }
  },

  methods: {
    async loadProduct() {
      this.isLoadingProduct = true
      try {
        this.product = await productService.get(this.token as string, this.hashId as string)
        this.name = this.product.name
      } finally {
        this.isLoadingProduct = false
      }
    },

    fieldErrorsFor(field: string): string[] {
      const codes = this.fieldErrors[field] ?? []
      return codes.map((code) => translateErrorCode(this.t, code, field))
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
          this.product = await productService.update(this.token as string, this.hashId as string, {
            name: this.name,
          })
        } else {
          this.product = await productService.create(this.token as string, { name: this.name })
        }
        this.isDirty = false
        this.close()
      } catch (error) {
        if (error instanceof ApiError) {
          this.fieldErrors = error.errors ?? {}
          this.generalError = error.errors ? null : error.message
        } else {
          this.generalError = 'network.connection_failed'
        }
      } finally {
        this.isSaving = false
      }
    },
  },
})
</script>

<template>
  <AppModal
    :title="isEditMode ? t('products.editTitle') : t('products.createTitle')"
    @close="requestClose"
  >
    <div v-if="isLoadingProduct" class="flex flex-col gap-3">
      <div class="h-4 w-24 animate-pulse rounded-sm bg-surface"></div>
      <div class="h-9 w-full animate-pulse rounded-md bg-surface"></div>
    </div>

    <form v-else id="product-form" class="flex flex-col gap-4" @submit.prevent="onSubmit">
      <FormField
        id="product-name"
        v-slot="{ describedBy }"
        :label="t('products.nameLabel')"
        :errors="fieldErrorsFor('name')"
      >
        <AppInput
          id="product-name"
          :model-value="name"
          :invalid="fieldErrorsFor('name').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="
            (value: string) => {
              name = value
              isDirty = true
            }
          "
        />
      </FormField>
      <p v-if="generalError" class="text-sm text-danger-700">
        {{ translateErrorCode(t, generalError) }}
      </p>
    </form>

    <template #footer>
      <ModalFooter>
        <AppButton type="button" variant="ghost" @click="requestClose">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton
          type="submit"
          form="product-form"
          :disabled="isSaving || name.trim().length === 0"
        >
          {{
            isEditMode
              ? isSaving
                ? t('products.saving')
                : t('products.save')
              : isSaving
                ? t('products.creating')
                : t('products.create')
          }}
        </AppButton>
      </ModalFooter>
    </template>
  </AppModal>

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
