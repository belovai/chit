<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import { mapState } from 'pinia'
import FormField from '@/components/ui/FormField.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/services/product'
import { translateErrorCode } from '@/utils/errors'
import { randomId } from '@/utils/id'
import type { ProductMatch } from '@/types/transaction'

export interface TransactionItemDraft {
  productId: string | null
  description: string
  quantity: string
  unit: string
  unitPrice: string
}

export function emptyItemDraft(): TransactionItemDraft {
  return { productId: null, description: '', quantity: '1', unit: '', unitPrice: '0' }
}

const SUGGEST_DEBOUNCE_MS = 300

export default defineComponent({
  name: 'TransactionItemRow',

  components: {
    FormField,
    AppButton,
  },

  props: {
    modelValue: {
      type: Object as PropType<TransactionItemDraft>,
      required: true,
    },
    errors: {
      type: Object as PropType<Record<string, string[]>>,
      default: () => ({}),
    },
  },

  emits: ['update:modelValue', 'remove'],

  setup() {
    const { t } = useI18n()
    return { t, translateErrorCode }
  },

  data() {
    return {
      rowId: randomId(),
      suggestions: [] as ProductMatch[],
      showSuggestions: false,
      debounceHandle: null as ReturnType<typeof setTimeout> | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),
  },

  methods: {
    errorsFor(field: string): string[] {
      const codes = this.errors[field] ?? []
      return codes.map((code) => translateErrorCode(this.t, code, field))
    },

    update(patch: Partial<TransactionItemDraft>) {
      this.$emit('update:modelValue', { ...this.modelValue, ...patch })
    },

    onDescriptionInput(value: string) {
      this.update({ description: value, productId: null })
      this.showSuggestions = true

      if (this.debounceHandle) {
        clearTimeout(this.debounceHandle)
      }

      const query = value.trim()
      if (query.length === 0) {
        this.suggestions = []
        return
      }

      this.debounceHandle = setTimeout(async () => {
        this.suggestions = await productService.suggest(this.token as string, query)
      }, SUGGEST_DEBOUNCE_MS)
    },

    selectSuggestion(match: ProductMatch) {
      this.update({ productId: match.product.hash_id, description: match.product.name })
      this.suggestions = []
      this.showSuggestions = false
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-2 border border-divider p-3 sm:flex-row sm:items-start sm:gap-3">
    <div class="relative min-w-0 flex-1">
      <FormField
        :id="`item-description-${rowId}`"
        :model-value="modelValue.description"
        :label="t('transactions.descriptionLabel')"
        :errors="errorsFor('description')"
        @update:model-value="onDescriptionInput"
      />
      <ul
        v-if="showSuggestions && suggestions.length > 0"
        class="absolute z-10 mt-1 w-full border border-divider bg-bg shadow-lg"
      >
        <li v-for="match in suggestions" :key="match.product.hash_id">
          <button
            type="button"
            class="block w-full px-3 py-2 text-left text-sm hover:bg-surface"
            @click="selectSuggestion(match)"
          >
            {{ match.product.name }}
          </button>
        </li>
      </ul>
    </div>

    <FormField
      :id="`item-quantity-${rowId}`"
      type="number"
      :model-value="modelValue.quantity"
      :label="t('transactions.quantityLabel')"
      :errors="errorsFor('quantity')"
      class="sm:w-28"
      @update:model-value="(value: string) => update({ quantity: value })"
    />

    <FormField
      :id="`item-unit-${rowId}`"
      :model-value="modelValue.unit"
      :label="t('transactions.unitLabel')"
      :errors="errorsFor('unit')"
      class="sm:w-24"
      @update:model-value="(value: string) => update({ unit: value })"
    />

    <FormField
      :id="`item-unit-price-${rowId}`"
      type="number"
      :model-value="modelValue.unitPrice"
      :label="t('transactions.unitPriceLabel')"
      :errors="errorsFor('unit_price')"
      class="sm:w-28"
      @update:model-value="(value: string) => update({ unitPrice: value })"
    />

    <AppButton type="button" variant="ghost" class="self-start sm:mt-5" @click="$emit('remove')">
      {{ t('transactions.removeItem') }}
    </AppButton>
  </div>
</template>
