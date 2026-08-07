<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppListItem from '@/components/ui/AppListItem.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppPager from '@/components/ui/AppPager.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/services/product'
import type { Product } from '@/types/product'

export default defineComponent({
  name: 'SettingsProductsView',

  components: {
    AppSection,
    AppCard,
    AppListItem,
    AppEmptyState,
    AppButton,
    AppPager,
    ConfirmDialog,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      products: [] as Product[],
      currentPage: 1,
      lastPage: 1,
      isLoading: false,
      productPendingDelete: null as Product | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),
  },

  watch: {
    // A lista a named view szülője, ezért overlay nyitásakor nem unmountol.
    // Záráskor újratöltjük, hogy a modalban létrehozott/szerkesztett termék
    // megjelenjen.
    '$route.name'(name: string | undefined) {
      if (name === 'settings-products') {
        void this.loadPage(1)
      }
    },
  },

  async mounted() {
    await this.loadPage(1)
  },

  methods: {
    async loadPage(page: number) {
      this.isLoading = true
      try {
        const result = await productService.list(this.token as string, page)
        this.products = result.data
        this.currentPage = result.currentPage
        this.lastPage = result.lastPage
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
      this.productPendingDelete = null

      const isLastItemOnPage = this.products.length === 1 && this.currentPage > 1
      await this.loadPage(isLastItemOnPage ? this.currentPage - 1 : this.currentPage)
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppSection :title="t('products.title')">
      <template #actions>
        <AppButton @click="$router.push({ name: 'settings-product-new' })">
          {{ t('products.addProduct') }}
        </AppButton>
      </template>
    </AppSection>

    <AppCard :padded="false">
      <AppEmptyState v-if="!isLoading && products.length === 0" :title="t('products.emptyState')" />

      <ul v-else class="divide-y divide-divider">
        <li v-for="product in products" :key="product.hash_id">
          <AppListItem>
            <span class="truncate text-sm font-medium text-text">{{ product.name }}</span>

            <template #trailing>
              <RouterLink
                :to="{ name: 'settings-product-edit', params: { hashId: product.hash_id } }"
                class="text-sm text-accent hover:text-accent-600"
              >
                {{ t('products.editLink') }}
              </RouterLink>
              <button
                type="button"
                class="cursor-pointer text-sm text-danger-700 hover:text-danger"
                @click="requestDeleteProduct(product)"
              >
                {{ t('products.deleteProduct') }}
              </button>
            </template>
          </AppListItem>
        </li>
      </ul>

      <template v-if="lastPage > 1" #footer>
        <AppPager
          :current-page="currentPage"
          :last-page="lastPage"
          :disabled="isLoading"
          @change="loadPage"
        />
      </template>
    </AppCard>

    <ConfirmDialog
      :open="productPendingDelete !== null"
      :message="t('products.deleteProductConfirm')"
      :confirm-label="t('products.deleteProduct')"
      variant="danger"
      @confirm="confirmDeleteProduct"
      @cancel="cancelDeleteProduct"
    />

    <RouterView name="modal" />
  </div>
</template>
