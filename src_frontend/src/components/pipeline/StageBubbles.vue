<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PipelineStep, StepStatus } from '@/types/pipeline'

const STATUS_CLASSES: Record<StepStatus, string> = {
  pending: 'bg-surface text-neutral-400 border-divider',
  queued: 'bg-surface text-neutral-500 border-divider',
  running: 'bg-accent-100 text-accent-700 border-accent-200 animate-pulse',
  succeeded: 'bg-success-100 text-success-700 border-success/25',
  failed: 'bg-danger-100 text-danger-700 border-danger/25',
  skipped: 'bg-surface text-neutral-400 border-divider',
  canceled: 'bg-surface text-neutral-400 border-divider',
  awaiting_manual: 'bg-warning-100 text-warning-700 border-warning/30',
  expired: 'bg-surface text-neutral-400 border-divider',
}

const STATUS_GLYPHS: Record<StepStatus, string> = {
  pending: '·',
  queued: '·',
  running: '●',
  succeeded: '✓',
  failed: '✕',
  skipped: '»',
  canceled: '⃠',
  awaiting_manual: '!',
  expired: '⏱',
}

export default defineComponent({
  name: 'StageBubbles',

  props: {
    stages: {
      type: Array as PropType<string[]>,
      required: true,
    },
    steps: {
      type: Array as PropType<PipelineStep[]>,
      required: true,
    },
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  computed: {
    // One group per declared stage, in declared order. Empty stages are kept so
    // the row shows what is still to come rather than growing as the run moves.
    groups(): { stage: string; steps: PipelineStep[] }[] {
      return this.stages.map((stage) => ({
        stage,
        steps: this.steps
          .filter((step) => step.stage === stage)
          .sort((a, b) => a.position - b.position),
      }))
    },
  },

  methods: {
    bubbleClass(step: PipelineStep): string {
      return `${STATUS_CLASSES[step.status]}${step.is_dynamic ? ' border-dashed' : ''}`
    },

    glyph(step: PipelineStep): string {
      return STATUS_GLYPHS[step.status]
    },

    label(step: PipelineStep): string {
      const stage = this.t(`pipeline.stage.${step.stage}`)
      const status = this.t(`pipeline.stepStatus.${step.status}`)
      const dynamic = step.is_dynamic ? ` — ${this.t('pipeline.detail.dynamic')}` : ''
      return `${step.step_key} (${stage}) — ${status}${dynamic}`
    },

    stageLabel(stage: string): string {
      return this.t(`pipeline.stage.${stage}`)
    },
  },
})
</script>

<template>
  <div class="flex items-center gap-2 overflow-x-auto">
    <div
      v-for="group in groups"
      :key="group.stage"
      class="flex shrink-0 items-center gap-1"
      :title="stageLabel(group.stage)"
    >
      <span
        v-if="group.steps.length === 0"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-dashed border-divider text-[10px] text-neutral-300"
        :title="`${stageLabel(group.stage)} — ${t('pipeline.detail.emptyStage')}`"
      >
        ·
      </span>
      <span
        v-for="step in group.steps"
        :key="step.step_key"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[10px] font-medium"
        :class="bubbleClass(step)"
        :title="label(step)"
      >
        {{ glyph(step) }}
      </span>
    </div>
  </div>
</template>
