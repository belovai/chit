<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/services/product'
import type { Product } from '@/types/product'

export default defineComponent({
  name: 'SettingsProductsView',

  components: {
    ConfirmDialog,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      products: [] as Product[],
      isLoading: false,
      productPendingDelete: null as Product | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),
  },

  async mounted() {
    await this.loadProducts()
  },

  methods: {
    async loadProducts() {
      this.isLoading = true
      try {
        this.products = await productService.list(this.token as string)
      } finally {
        this.isLoading = false
      }
    },

    requestDeleteProduct(product: Product) {
      this.productPendingDelete = product
    },

    cancelDeleteProduct() {
      this.productPendingDelete = null
    },

    async confirmDeleteProduct() {
      const product = this.productPendingDelete
      if (!product) {
        return
      }
      await productService.destroy(this.token as string, product.hash_id)
      this.products = this.products.filter((item) => item.hash_id !== product.hash_id)
      this.productPendingDelete = null
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl">{{ t('products.title') }}</h1>
      <RouterLink
        :to="{ name: 'settings-product-new' }"
        class="border font-[family-name:var(--font-heading)] font-semibold px-4 py-2.5 text-sm transition-colors bg-accent border-accent text-neutral-100 hover:bg-accent-600 hover:border-accent-600"
      >
        {{ t('products.addProduct') }}
      </RouterLink>
    </div>

    <p v-if="!isLoading && products.length === 0" class="text-sm text-neutral-600">
      {{ t('products.emptyState') }}
    </p>

    <ul v-else class="flex flex-col divide-y divide-divider border border-divider bg-surface">
      <li
        v-for="product in products"
        :key="product.hash_id"
        class="flex items-center justify-between gap-4 px-4 py-3"
      >
        <span class="text-sm font-semibold text-text">{{ product.name }}</span>
        <div class="flex shrink-0 items-center gap-4">
          <RouterLink
            :to="{ name: 'settings-product-edit', params: { hashId: product.hash_id } }"
            class="text-sm text-accent hover:text-accent-600"
          >
            {{ t('products.editLink') }}
          </RouterLink>
          <button
            type="button"
            class="text-sm text-danger-700 hover:text-danger"
            @click="requestDeleteProduct(product)"
          >
            {{ t('products.deleteProduct') }}
          </button>
        </div>
      </li>
    </ul>

    <ConfirmDialog
      :open="productPendingDelete !== null"
      :message="t('products.deleteProductConfirm')"
      :confirm-label="t('products.deleteProduct')"
      variant="danger"
      @confirm="confirmDeleteProduct"
      @cancel="cancelDeleteProduct"
    />
  </div>
</template>
