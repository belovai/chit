<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import { mapState } from 'pinia'
import FormField from '@/components/ui/FormField.vue'
import AppInput from '@/components/ui/AppInput.vue'
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
    AppInput,
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
  <!-- The slide-over is only 28rem wide, so the fields don't fit on one row —
       the description sits on its own row, with the quantity/unit/unit-price grid below. -->
  <div class="flex flex-col gap-2.5 px-4 py-3">
    <div class="relative min-w-0">
      <FormField
        :id="`item-description-${rowId}`"
        v-slot="{ describedBy }"
        :label="t('transactions.descriptionLabel')"
        :errors="errorsFor('description')"
      >
        <AppInput
          :id="`item-description-${rowId}`"
          :model-value="modelValue.description"
          :invalid="errorsFor('description').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="onDescriptionInput"
        />
      </FormField>
      <ul
        v-if="showSuggestions && suggestions.length > 0"
        class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-divider bg-panel shadow-pop"
      >
        <li v-for="match in suggestions" :key="match.product.hash_id">
          <button
            type="button"
            class="block w-full cursor-pointer px-3 py-2 text-left text-sm hover:bg-surface"
            @click="selectSuggestion(match)"
          >
            {{ match.product.name }}
          </button>
        </li>
      </ul>
    </div>

    <div class="grid grid-cols-3 gap-2">
      <FormField
        :id="`item-quantity-${rowId}`"
        v-slot="{ describedBy }"
        :label="t('transactions.quantityLabel')"
        :errors="errorsFor('quantity')"
        class="min-w-0"
      >
        <AppInput
          :id="`item-quantity-${rowId}`"
          type="number"
          :model-value="modelValue.quantity"
          :invalid="errorsFor('quantity').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="(value: string) => update({ quantity: value })"
        />
      </FormField>

      <FormField
        :id="`item-unit-${rowId}`"
        v-slot="{ describedBy }"
        :label="t('transactions.unitLabel')"
        :errors="errorsFor('unit')"
        class="min-w-0"
      >
        <AppInput
          :id="`item-unit-${rowId}`"
          :model-value="modelValue.unit"
          :invalid="errorsFor('unit').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="(value: string) => update({ unit: value })"
        />
      </FormField>

      <FormField
        :id="`item-unit-price-${rowId}`"
        v-slot="{ describedBy }"
        :label="t('transactions.unitPriceLabel')"
        :errors="errorsFor('unit_price')"
        class="min-w-0"
      >
        <AppInput
          :id="`item-unit-price-${rowId}`"
          type="number"
          :model-value="modelValue.unitPrice"
          :invalid="errorsFor('unit_price').length > 0"
          :aria-describedby="describedBy"
          @update:model-value="(value: string) => update({ unitPrice: value })"
        />
      </FormField>
    </div>

    <AppButton type="button" variant="ghost" size="sm" class="self-end" @click="$emit('remove')">
      {{ t('transactions.removeItem') }}
    </AppButton>
  </div>
</template>
