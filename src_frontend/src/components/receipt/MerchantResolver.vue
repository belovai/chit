<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { randomId } from '@/utils/id'
import type { Candidate, LocationCandidate } from '@/types/receipt'

/**
 * Merchant and branch are one decision with two halves: a branch only means
 * something under a merchant. Both halves are either picked from a candidate
 * or typed, and typing shows a pill saying what will be created — the old
 * bare picker changed nothing on screen, so the create button looked dead.
 */
export default defineComponent({
  name: 'MerchantResolver',

  components: { AppButton, AppInput },

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
    return {
      selectedMerchantId: this.merchantAcceptedId,
      newMerchantName: null as string | null,
      merchantInput: this.rawName ?? '',
      merchantInputId: `merchant-name-${randomId()}`,

      selectedLocationHashId: this.locationAcceptedHashId,
      newLocationAddress: null as string | null,
      locationCleared: false,
      locationInput: this.rawAddress ?? '',
      locationInputId: `location-address-${randomId()}`,
    }
  },

  computed: {
    // Only the candidates of the merchant the artifact was built for are
    // trustworthy; picking a different merchant means we know nothing about
    // its branches, so the chips go away and typing is the only way in.
    visibleLocationCandidates(): LocationCandidate[] {
      if (this.newMerchantName !== null) return []
      if (this.selectedMerchantId !== this.merchantAcceptedId) return []
      return this.locationCandidates
    },
  },

  methods: {
    selectMerchant(id: number) {
      this.selectedMerchantId = id
      this.newMerchantName = null
      this.resetLocation()
      this.emitValues()
    },

    createMerchant() {
      const name = this.merchantInput.trim()
      if (name === '') return
      this.newMerchantName = name
      this.selectedMerchantId = null
      this.resetLocation()
      this.emitValues()
    },

    resetLocation() {
      this.selectedLocationHashId = null
      this.newLocationAddress = null
      this.locationCleared = false
    },

    selectLocation(hashId: string) {
      this.selectedLocationHashId = hashId
      this.newLocationAddress = null
      this.locationCleared = false
      this.emitValues()
    },

    createLocation() {
      const address = this.locationInput.trim()
      if (address === '') return
      this.newLocationAddress = address
      this.selectedLocationHashId = null
      this.locationCleared = false
      this.emitValues()
    },

    clearLocation() {
      this.locationCleared = true
      this.selectedLocationHashId = null
      this.newLocationAddress = null
      this.emitValues()
    },

    // Mutually exclusive by construction: the parent merges this object into
    // `values`, so a key that is absent here must be absent there too.
    emitValues() {
      const values: Record<string, unknown> = {}

      if (this.newMerchantName !== null) {
        values.merchant_name = this.newMerchantName
      } else if (this.selectedMerchantId !== null) {
        values.merchant_id = this.selectedMerchantId
      }

      if (this.locationCleared) {
        values.location_hash_id = null
      } else if (this.newLocationAddress !== null) {
        values.location_address = this.newLocationAddress
      } else if (this.selectedLocationHashId !== null) {
        values.location_hash_id = this.selectedLocationHashId
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
        v-for="candidate in merchantCandidates"
        :key="candidate.id"
        type="button"
        class="flex items-center justify-between rounded-sm border px-2 py-1 text-left text-xs"
        :class="
          candidate.id === selectedMerchantId ? 'border-accent-200 bg-accent-100' : 'border-divider'
        "
        @click="selectMerchant(candidate.id)"
      >
        <span>{{ candidate.name }}</span>
        <span class="text-neutral-500">{{ (candidate.score * 100).toFixed(0) }}%</span>
      </button>

      <div class="flex items-center gap-2">
        <AppInput :id="merchantInputId" v-model="merchantInput" class="flex-1" />
        <AppButton :disabled="merchantInput.trim() === ''" @click="createMerchant">
          {{ t('receipts.review.newMerchant') }}
        </AppButton>
      </div>

      <p v-if="newMerchantName !== null" class="text-xs text-accent-700" aria-live="polite">
        {{ t('receipts.review.willCreateMerchant', { name: newMerchantName }) }}
      </p>
    </div>

    <div class="flex flex-col gap-2 border-t border-divider pt-3">
      <p class="text-xs font-medium text-neutral-700">{{ t('receipts.review.locationLabel') }}</p>

      <p v-if="visibleLocationCandidates.length === 0" class="text-xs text-neutral-500">
        {{ t('receipts.review.noLocationCandidates') }}
      </p>

      <button
        v-for="candidate in visibleLocationCandidates"
        :key="candidate.hash_id"
        type="button"
        class="flex items-center justify-between rounded-sm border px-2 py-1 text-left text-xs"
        :class="
          candidate.hash_id === selectedLocationHashId
            ? 'border-accent-200 bg-accent-100'
            : 'border-divider'
        "
        @click="selectLocation(candidate.hash_id)"
      >
        <span>{{ candidate.name }}</span>
        <span class="text-neutral-500">{{ (candidate.score * 100).toFixed(0) }}%</span>
      </button>

      <div class="flex items-center gap-2">
        <AppInput :id="locationInputId" v-model="locationInput" class="flex-1" />
        <AppButton :disabled="locationInput.trim() === ''" @click="createLocation">
          {{ t('receipts.review.newLocation') }}
        </AppButton>
        <AppButton variant="ghost" @click="clearLocation">
          {{ t('receipts.review.noLocation') }}
        </AppButton>
      </div>

      <p v-if="newLocationAddress !== null" class="text-xs text-accent-700" aria-live="polite">
        {{ t('receipts.review.willCreateLocation', { name: newLocationAddress }) }}
      </p>
      <p v-else-if="locationCleared" class="text-xs text-neutral-500" aria-live="polite">
        {{ t('receipts.review.noLocationChosen') }}
      </p>
    </div>
  </div>
</template>
