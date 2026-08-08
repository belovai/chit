<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { randomId } from '@/utils/id'
import type { Candidate } from '@/types/receipt'

export default defineComponent({
  name: 'CandidatePicker',

  components: { AppButton, AppInput },

  props: {
    candidates: { type: Array as PropType<Candidate[]>, required: true },
    acceptedId: { type: Number as PropType<number | null>, default: null },
    rawName: { type: String as PropType<string | null>, default: null },
    createLabel: { type: String as PropType<string | null>, default: null },
  },

  emits: ['select', 'create'],

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return { newName: this.rawName ?? '', newNameId: `candidate-new-name-${randomId()}` }
  },
})
</script>

<template>
  <div class="flex flex-col gap-2">
    <p v-if="candidates.length === 0" class="text-xs text-neutral-500">
      {{ t('receipts.review.noCandidates') }}
    </p>

    <button
      v-for="candidate in candidates"
      :key="candidate.id"
      type="button"
      class="flex items-center justify-between rounded-sm border px-2 py-1 text-left text-xs"
      :class="candidate.id === acceptedId ? 'border-accent-200 bg-accent-100' : 'border-divider'"
      @click="$emit('select', candidate.id)"
    >
      <span>{{ candidate.name }}</span>
      <span class="text-neutral-500">{{ (candidate.score * 100).toFixed(0) }}%</span>
    </button>

    <div class="flex items-center gap-2">
      <AppInput :id="newNameId" v-model="newName" class="flex-1" />
      <AppButton :disabled="newName.trim() === ''" @click="$emit('create', newName.trim())">
        {{ createLabel ?? t('receipts.review.newProduct') }}
      </AppButton>
    </div>
  </div>
</template>
