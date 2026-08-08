<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useAuthStore } from '@/stores/auth'
import { merchantService } from '@/services/merchant'
import { randomId } from '@/utils/id'
import type { Candidate, LocationCandidate } from '@/types/receipt'
import type { LocationSuggestion } from '@/types/merchant'
import type { MerchantMatch } from '@/types/transaction'

const SUGGEST_DEBOUNCE_MS = 300

type MerchantState = 'existing' | 'new'
type LocationState = 'existing' | 'new' | 'none'

/**
 * Merchant and branch are one decision with two halves: a branch only means
 * something under a merchant. Each half is in one of three states, and the
 * badge next to it says which — a row that already exists is *selected*, a
 * typed value with no match will be *created*. The old screen offered "create"
 * buttons for a choice the reviewer never actually made (approval creates the
 * row either way) and then kept claiming a branch was new after the reviewer
 * corrected the merchant to one that already had it.
 *
 * Whether a branch exists is never guessed here. Every merchant change asks the
 * suggest endpoint, which runs the same matcher with the same thresholds the
 * approval path uses, so the badge cannot disagree with what gets written.
 */
export default defineComponent({
  name: 'MerchantResolver',

  components: { AppBadge, AppButton, AppInput },

  props: {
    merchantCandidates: { type: Array as PropType<Candidate[]>, required: true },
    merchantAcceptedId: { type: Number as PropType<number | null>, default: null },
    rawName: { type: String as PropType<string | null>, default: null },
    locationCandidates: { type: Array as PropType<LocationCandidate[]>, required: true },
    locationAcceptedHashId: { type: String as PropType<string | null>, default: null },
    rawAddress: { type: String as PropType<string | null>, default: null },
  },

  emits: ['update:values'],

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    const accepted = this.merchantCandidates.find(
      (candidate) => candidate.id === this.merchantAcceptedId,
    )
    const acceptedLocation = this.locationCandidates.find(
      (candidate) => candidate.hash_id === this.locationAcceptedHashId,
    )

    return {
      merchantState: (accepted?.hash_id ? 'existing' : 'new') as MerchantState,
      merchantHashId: accepted?.hash_id ?? null,
      merchantInput: accepted?.name ?? this.rawName ?? '',
      merchantInputId: `merchant-name-${randomId()}`,
      merchantSuggestions: [] as MerchantMatch[],
      showMerchantSuggestions: false,
      merchantDebounceHandle: null as ReturnType<typeof setTimeout> | null,

      locationState: 'none' as LocationState,
      locationHashId: null as string | null,
      locationInput: acceptedLocation?.name ?? this.rawAddress ?? '',
      locationInputId: `location-address-${randomId()}`,
      // Every branch of the selected merchant, best match first. Seeded from
      // the pipeline's candidates and replaced wholesale by each suggest call.
      locationOptions: this.locationCandidates.map(
        (candidate): LocationSuggestion => ({
          hash_id: candidate.hash_id,
          address: candidate.name,
          score: candidate.score,
        }),
      ),
      locationDebounceHandle: null as ReturnType<typeof setTimeout> | null,
      // Merchant changes and keystrokes both fire suggest calls; only the
      // newest one may touch state, or a slow early reply overwrites a fast
      // later one.
      locationRequestSeq: 0,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    // A single clear-cut match needs no picker — the badge already names it.
    // Chips earn their space only where the pipeline was unsure, which is
    // exactly the merchant_ambiguous / new_merchant situation.
    showMerchantChips(): boolean {
      return (
        this.merchantCandidates.length > 0 &&
        (this.merchantCandidates.length > 1 || this.merchantAcceptedId === null)
      )
    },

    merchantBadgeVariant(): 'success' | 'warning' {
      return this.merchantState === 'existing' ? 'success' : 'warning'
    },

    merchantBadgeLabel(): string {
      return this.merchantState === 'existing'
        ? this.t('receipts.review.badgeSelected')
        : this.t('receipts.review.badgeNew')
    },

    locationBadgeVariant(): 'success' | 'warning' | 'neutral' {
      if (this.locationState === 'existing') return 'success'
      if (this.locationState === 'new') return 'warning'
      return 'neutral'
    },

    locationBadgeLabel(): string {
      if (this.locationState === 'existing') return this.t('receipts.review.badgeSelected')
      if (this.locationState === 'new') return this.t('receipts.review.badgeNew')
      return this.t('receipts.review.noLocation')
    },
  },

  created() {
    // The pipeline's own location verdict is the starting point; from here on
    // every change re-derives it from the server.
    if (this.locationAcceptedHashId !== null) {
      this.locationState = 'existing'
      this.locationHashId = this.locationAcceptedHashId
    } else {
      this.locationState = this.locationInput.trim() === '' ? 'none' : 'new'
    }

    this.emitValues()
  },

  beforeUnmount() {
    if (this.merchantDebounceHandle) clearTimeout(this.merchantDebounceHandle)
    if (this.locationDebounceHandle) clearTimeout(this.locationDebounceHandle)
  },

  methods: {
    selectMerchantCandidate(candidate: Candidate) {
      if (!candidate.hash_id) return
      this.setMerchant(candidate.hash_id, candidate.name)
    },

    selectSuggestedMerchant(match: MerchantMatch) {
      this.setMerchant(match.merchant.hash_id, match.merchant.name)
    },

    setMerchant(hashId: string | null, name: string) {
      this.merchantHashId = hashId
      this.merchantState = hashId === null ? 'new' : 'existing'
      this.merchantInput = name
      this.merchantSuggestions = []
      this.showMerchantSuggestions = false
      void this.rebaseLocation()
    },

    onMerchantInput(value: string) {
      this.merchantInput = value
      this.merchantHashId = null
      this.merchantState = 'new'
      this.showMerchantSuggestions = true

      if (this.merchantDebounceHandle) clearTimeout(this.merchantDebounceHandle)

      const query = value.trim()

      if (query === '') {
        this.merchantSuggestions = []
        void this.rebaseLocation()
        return
      }

      this.merchantDebounceHandle = setTimeout(async () => {
        const suggestions = await merchantService.suggest(this.token as string, query)

        if (this.merchantInput.trim() !== query) return

        this.merchantSuggestions = suggestions

        // Typing an existing name in full is a selection, not a request for a
        // duplicate. The dropdown stays open so another shop is still one
        // click away.
        const exact = suggestions.find(
          (match) => match.merchant.name.trim().toLowerCase() === query.toLowerCase(),
        )

        if (exact === undefined) {
          void this.rebaseLocation()
          return
        }

        this.merchantHashId = exact.merchant.hash_id
        this.merchantState = 'existing'
        void this.rebaseLocation()
      }, SUGGEST_DEBOUNCE_MS)
    },

    // The branch belongs to whichever merchant is selected right now, so a
    // merchant change throws the previous verdict away and asks again with the
    // address printed on the receipt.
    async rebaseLocation() {
      this.locationInput = this.rawAddress ?? ''
      await this.resolveLocation()
    },

    onLocationInput(value: string) {
      this.locationInput = value

      if (this.locationDebounceHandle) clearTimeout(this.locationDebounceHandle)

      this.locationDebounceHandle = setTimeout(() => {
        void this.resolveLocation()
      }, SUGGEST_DEBOUNCE_MS)
    },

    async resolveLocation() {
      const seq = ++this.locationRequestSeq
      const address = this.locationInput.trim()

      this.locationHashId = null
      this.locationState = address === '' ? 'none' : 'new'

      if (this.merchantHashId === null) {
        // A merchant that does not exist yet has no branches to match against.
        this.locationOptions = []
        this.emitValues()
        return
      }

      this.emitValues()

      try {
        const result = await merchantService.suggestLocations(
          this.token as string,
          this.merchantHashId,
          address === '' ? undefined : address,
        )

        if (seq !== this.locationRequestSeq) return

        this.locationOptions = result.candidates

        if (result.accepted_hash_id !== null) {
          const accepted = result.candidates.find(
            (candidate) => candidate.hash_id === result.accepted_hash_id,
          )
          this.locationHashId = result.accepted_hash_id
          this.locationState = 'existing'
          this.locationInput = accepted?.address ?? this.locationInput
        }
      } catch {
        // The request failed, so nothing is known about this merchant's
        // branches. Staying on "new" is the honest answer — never claim a row
        // is selected without one behind it.
        if (seq === this.locationRequestSeq) this.locationOptions = []
      }

      if (seq === this.locationRequestSeq) this.emitValues()
    },

    selectLocation(option: LocationSuggestion) {
      this.locationRequestSeq += 1
      this.locationHashId = option.hash_id
      this.locationState = 'existing'
      this.locationInput = option.address ?? ''
      this.emitValues()
    },

    clearLocation() {
      this.locationRequestSeq += 1
      this.locationHashId = null
      this.locationState = 'none'
      this.emitValues()
    },

    // Always both halves, always explicit. The parent merges this object into
    // `values` after deleting all five keys, so an omitted key would leave the
    // backend guessing — and its guess is what produced the wrong branch.
    emitValues() {
      const values: Record<string, unknown> = {}

      if (this.merchantHashId !== null) {
        values.merchant_hash_id = this.merchantHashId
      } else {
        values.merchant_name = this.merchantInput.trim()
      }

      if (this.locationState === 'existing' && this.locationHashId !== null) {
        values.location_hash_id = this.locationHashId
      } else if (this.locationState === 'new') {
        values.location_address = this.locationInput.trim()
      } else {
        values.location_hash_id = null
      }

      this.$emit('update:values', values)
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-col gap-2">
      <p class="text-xs font-medium text-neutral-700">{{ t('receipts.review.merchantLabel') }}</p>

      <p v-if="merchantCandidates.length === 0" class="text-xs text-neutral-500">
        {{ t('receipts.review.noCandidates') }}
      </p>

      <button
        v-for="candidate in showMerchantChips ? merchantCandidates : []"
        :key="candidate.id"
        type="button"
        class="flex items-center justify-between rounded-sm border px-2 py-1 text-left text-xs"
        :class="
          candidate.hash_id === merchantHashId
            ? 'border-accent-200 bg-accent-100'
            : 'border-divider'
        "
        @click="selectMerchantCandidate(candidate)"
      >
        <span>{{ candidate.name }}</span>
        <span class="text-neutral-500">{{ (candidate.score * 100).toFixed(0) }}%</span>
      </button>

      <div class="relative">
        <AppInput
          :id="merchantInputId"
          :model-value="merchantInput"
          :placeholder="t('receipts.review.merchantPlaceholder')"
          @update:model-value="onMerchantInput"
          @focus="showMerchantSuggestions = true"
        />

        <ul
          v-if="showMerchantSuggestions && merchantSuggestions.length > 0"
          class="absolute top-full z-10 mt-1 w-full overflow-hidden rounded-lg border border-divider bg-panel shadow-pop"
        >
          <li v-for="match in merchantSuggestions" :key="match.merchant.hash_id">
            <button
              type="button"
              class="block w-full px-3 py-2 text-left text-xs hover:bg-surface"
              @click="selectSuggestedMerchant(match)"
            >
              {{ match.merchant.name }}
            </button>
          </li>
        </ul>
      </div>

      <p class="flex items-center gap-2 text-xs" aria-live="polite">
        <AppBadge :variant="merchantBadgeVariant">{{ merchantBadgeLabel }}</AppBadge>
        <span class="text-neutral-700">{{ merchantInput }}</span>
      </p>
    </div>

    <div class="flex flex-col gap-2 border-t border-divider pt-3">
      <p class="text-xs font-medium text-neutral-700">{{ t('receipts.review.locationLabel') }}</p>

      <p v-if="merchantHashId === null" class="text-xs text-neutral-500">
        {{ t('receipts.review.locationNeedsMerchant') }}
      </p>
      <p v-else-if="locationOptions.length === 0" class="text-xs text-neutral-500">
        {{ t('receipts.review.noLocationCandidatesForMerchant') }}
      </p>

      <button
        v-for="option in locationOptions"
        :key="option.hash_id"
        type="button"
        class="flex items-center justify-between rounded-sm border px-2 py-1 text-left text-xs"
        :class="
          option.hash_id === locationHashId ? 'border-accent-200 bg-accent-100' : 'border-divider'
        "
        @click="selectLocation(option)"
      >
        <span>{{ option.address }}</span>
        <span v-if="option.score !== null" class="text-neutral-500">
          {{ (option.score * 100).toFixed(0) }}%
        </span>
      </button>

      <div class="flex items-center gap-2">
        <AppInput
          :id="locationInputId"
          class="flex-1"
          :model-value="locationInput"
          :placeholder="t('receipts.review.locationPlaceholder')"
          @update:model-value="onLocationInput"
        />
        <AppButton variant="ghost" @click="clearLocation">
          {{ t('receipts.review.noLocation') }}
        </AppButton>
      </div>

      <p class="flex items-center gap-2 text-xs" aria-live="polite">
        <AppBadge :variant="locationBadgeVariant">{{ locationBadgeLabel }}</AppBadge>
        <span v-if="locationState === 'none'" class="text-neutral-500">
          {{ t('receipts.review.noLocationChosen') }}
        </span>
        <span v-else class="text-neutral-700">{{ locationInput }}</span>
      </p>
    </div>
  </div>
</template>
