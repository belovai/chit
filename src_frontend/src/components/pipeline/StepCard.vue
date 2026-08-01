<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDurationMs } from '@/utils/datetime'
import type { PipelineStepDetail, StepStatus } from '@/types/pipeline'

const STATUS_CLASSES: Record<StepStatus, string> = {
  pending: 'border-divider text-neutral-400',
  queued: 'border-divider text-neutral-500',
  running: 'border-accent-200 text-accent-700',
  succeeded: 'border-success/25 text-success-700',
  failed: 'border-danger/25 text-danger-700',
  skipped: 'border-divider text-neutral-400',
  canceled: 'border-divider text-neutral-400',
  awaiting_manual: 'border-warning/30 text-warning-700',
  expired: 'border-divider text-neutral-400',
}

export default defineComponent({
  name: 'StepCard',

  props: {
    step: {
      type: Object as PropType<PipelineStepDetail>,
      required: true,
    },
    selected: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['select'],

  setup() {
    const { t } = useI18n()
    return { t, formatDurationMs }
  },

  computed: {
    statusClass(): string {
      const dynamic = this.step.is_dynamic ? ' border-dashed' : ''
      const selected = this.selected ? ' ring-2 ring-accent-200' : ''
      return `${STATUS_CLASSES[this.step.status]}${dynamic}${selected}`
    },
  },
})
</script>

<template>
  <button
    type="button"
    class="flex w-full flex-col gap-1 rounded-sm border bg-surface px-2 py-1.5 text-left"
    :class="statusClass"
    @click="$emit('select', step)"
  >
    <span class="truncate text-xs font-medium">{{ step.step_key }}</span>
    <span class="text-[10px]">
      {{ t(`pipeline.stepStatus.${step.status}`) }} · {{ formatDurationMs(step.duration_ms) }}
    </span>
    <span v-if="step.max_attempts > 1 || step.attempt > 1" class="text-[10px] opacity-70">
      {{ t('pipeline.detail.attempt', { n: step.attempt, max: step.max_attempts }) }}
    </span>
  </button>
</template>
