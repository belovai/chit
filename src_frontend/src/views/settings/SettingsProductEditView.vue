<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import FormField from '@/components/ui/FormField.vue'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/services/product'
import { ApiError } from '@/types/auth'
import { translateErrorCode } from '@/utils/errors'
import type { Product } from '@/types/product'

export default defineComponent({
  name: 'SettingsProductEditView',

  components: {
    AppButton,
    FormField,
  },

  setup() {
    const { t } = useI18n()
    return { t, translateErrorCode }
  },

  data() {
    return {
      product: null as Product | null,
      name: '',
      isLoadingProduct: false,
      isSaving: false,
      fieldErrors: {} as Record<string, string[]>,
      generalError: null as string | null,
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
          await this.$router.push({
            name: 'settings-product-edit',
            params: { hashId: this.product.hash_id },
          })
        }
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
  <div class="flex flex-col gap-6">
    <RouterLink
      :to="{ name: 'settings-products' }"
      class="text-sm text-accent hover:text-accent-600"
    >
      {{ t('products.backToList') }}
    </RouterLink>

    <form
      class="flex flex-col gap-4 border border-divider bg-surface p-6"
      @submit.prevent="onSubmit"
    >
      <FormField
        id="product-name"
        v-model="name"
        :label="t('products.nameLabel')"
        :errors="fieldErrorsFor('name')"
      />
      <p v-if="generalError" class="text-sm text-danger-700">
        {{ translateErrorCode(t, generalError) }}
      </p>
      <AppButton type="submit" :disabled="isSaving || name.trim().length === 0">
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
    </form>
  </div>
</template>
