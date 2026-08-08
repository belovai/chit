<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/services/product'
import { randomId } from '@/utils/id'
import type { Candidate } from '@/types/receipt'
import type { ProductMatch } from '@/types/transaction'

const SUGGEST_DEBOUNCE_MS = 300

type ProductState = 'existing' | 'new'

/**
 * One line item's product, resolved the same way MerchantResolver resolves a
 * merchant: the badge says whether the name in the field is a row that already
 * exists or one approval will create. The old screen offered a "create as a new
 * product" button for a choice the reviewer never made — approval creates the
 * product either way when nothing was picked, so the button only hid which of
 * the two was about to happen.
 */
export default defineComponent({
  name: 'ProductResolver',

  components: { AppBadge, AppInput },

  props: {
    candidates: { type: Array as PropType<Candidate[]>, required: true },
    acceptedId: { type: Number as PropType<number | null>, default: null },
    rawName: { type: String as PropType<string | null>, default: null },
  },

  emits: ['update:selection'],

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    const accepted = this.candidates.find((candidate) => candidate.id === this.acceptedId)

    return {
      // Frozen at mount on purpose: which chips the matcher proposed is a fact
      // about the extraction, not about what the reviewer has picked since. Re-
      // deriving it would make the row grow and shrink under their cursor.
      showChips:
        this.candidates.length > 0 && (this.candidates.length > 1 || accepted === undefined),
      state: (accepted ? 'existing' : 'new') as ProductState,
      productId: accepted?.id ?? null,
      productHashId: accepted?.hash_id ?? null,
      input: accepted?.name ?? this.rawName ?? '',
      inputId: `product-name-${randomId()}`,
      suggestions: [] as ProductMatch[],
      showSuggestions: false,
      debounceHandle: null as ReturnType<typeof setTimeout> | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    badgeVariant(): 'success' | 'warning' {
      return this.state === 'existing' ? 'success' : 'warning'
    },

    badgeLabel(): string {
      return this.state === 'existing'
        ? this.t('receipts.review.badgeSelected')
        : this.t('receipts.review.badgeNew')
    },
  },

  beforeUnmount() {
    if (this.debounceHandle) clearTimeout(this.debounceHandle)
  },

  methods: {
    // Picking the same product from the suggest list instead of the chip must
    // still light the chip up, and that path only ever knows the hash id.
    isSelected(candidate: Candidate): boolean {
      if (this.state !== 'existing') return false
      if (this.productHashId !== null) return candidate.hash_id === this.productHashId
      return candidate.id === this.productId
    },

    selectCandidate(candidate: Candidate) {
      this.productId = candidate.id
      this.productHashId = candidate.hash_id ?? null
      this.state = 'existing'
      this.input = candidate.name
      this.suggestions = []
      this.showSuggestions = false
      this.emitSelection()
    },

    selectSuggestion(match: ProductMatch) {
      this.productId = null
      this.productHashId = match.product.hash_id
      this.state = 'existing'
      this.input = match.product.name
      this.suggestions = []
      this.showSuggestions = false
      this.emitSelection()
    },

    onInput(value: string) {
      this.input = value
      this.productId = null
      this.productHashId = null
      this.state = 'new'
      this.showSuggestions = true
      this.emitSelection()

      if (this.debounceHandle) clearTimeout(this.debounceHandle)

      const query = value.trim()

      if (query === '') {
        this.suggestions = []
        return
      }

      this.debounceHandle = setTimeout(async () => {
        const suggestions = await productService.suggest(this.token as string, query)

        if (this.input.trim() !== query) return

        this.suggestions = suggestions

        // Typing an existing name in full is a selection, not a request for a
        // duplicate. The list stays open so another product is still one click
        // away.
        const exact = suggestions.find(
          (match) => match.product.name.trim().toLowerCase() === query.toLowerCase(),
        )

        if (exact === undefined) return

        this.productHashId = exact.product.hash_id
        this.state = 'existing'
        this.emitSelection()
      }, SUGGEST_DEBOUNCE_MS)
    },

    // Always explicit, never a partial object: the parent replaces this item's
    // whole override, so an omitted key would leave approval reading the stale
    // auto-match the reviewer just corrected.
    emitSelection() {
      if (this.state === 'existing' && this.productHashId !== null) {
        this.$emit('update:selection', { product_hash_id: this.productHashId })
        return
      }

      if (this.state === 'existing' && this.productId !== null) {
        this.$emit('update:selection', { product_id: this.productId })
        return
      }

      this.$emit('update:selection', { product_id: null, product_name: this.input.trim() })
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-2">
    <button
      v-for="candidate in showChips ? candidates : []"
      :key="candidate.id"
      type="button"
      class="flex items-center justify-between rounded-sm border px-2 py-1 text-left text-xs"
      :class="isSelected(candidate) ? 'border-accent-200 bg-accent-100' : 'border-divider'"
      @click="selectCandidate(candidate)"
    >
      <span>{{ candidate.name }}</span>
      <span class="text-neutral-500">{{ (candidate.score * 100).toFixed(0) }}%</span>
    </button>

    <div class="relative">
      <AppInput
        :id="inputId"
        :model-value="input"
        :placeholder="t('receipts.review.productPlaceholder')"
        @update:model-value="onInput"
        @focus="showSuggestions = true"
      />

      <ul
        v-if="showSuggestions && suggestions.length > 0"
        class="absolute top-full z-10 mt-1 w-full overflow-hidden rounded-lg border border-divider bg-panel shadow-pop"
      >
        <li v-for="match in suggestions" :key="match.product.hash_id">
          <button
            type="button"
            class="block w-full px-3 py-2 text-left text-xs hover:bg-surface"
            @click="selectSuggestion(match)"
          >
            {{ match.product.name }}
          </button>
        </li>
      </ul>
    </div>

    <p class="flex items-center gap-2 text-xs" aria-live="polite">
      <AppBadge :variant="badgeVariant">{{ badgeLabel }}</AppBadge>
      <span class="text-neutral-700">{{ input }}</span>
    </p>
  </div>
</template>
