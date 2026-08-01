<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import AppBadge, { type BadgeVariant } from '@/components/ui/AppBadge.vue'
import type { FindingSeverity, PipelineFinding } from '@/types/pipeline'

const SEVERITY_VARIANTS: Record<FindingSeverity, BadgeVariant> = {
  info: 'neutral',
  warning: 'warning',
  blocker: 'danger',
}

export default defineComponent({
  name: 'FindingList',

  components: { AppBadge },

  props: {
    findings: {
      type: Array as PropType<PipelineFinding[]>,
      required: true,
    },
  },

  setup() {
    const { t, te } = useI18n()
    return { t, te }
  },

  methods: {
    variant(finding: PipelineFinding): BadgeVariant {
      return SEVERITY_VARIANTS[finding.severity]
    },

    // The backend sends a machine code; unknown codes fall back to the raw code
    // rather than rendering a missing-translation warning.
    label(finding: PipelineFinding): string {
      const key = `pipeline.finding.${finding.code}`
      return this.te(key) ? this.t(key) : finding.code
    },
  },
})
</script>

<template>
  <p v-if="findings.length === 0" class="text-xs text-neutral-500">
    {{ t('pipeline.detail.noFindings') }}
  </p>

  <ul v-else class="flex flex-col gap-2">
    <li v-for="finding in findings" :key="finding.code" class="flex items-start gap-2">
      <AppBadge :variant="variant(finding)">
        {{ t(`pipeline.severity.${finding.severity}`) }}
      </AppBadge>
      <span class="text-xs">{{ label(finding) }}</span>
    </li>
  </ul>
</template>
