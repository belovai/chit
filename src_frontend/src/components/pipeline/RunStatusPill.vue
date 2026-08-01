<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppBadge, { type BadgeVariant } from '@/components/ui/AppBadge.vue'
import type { RunStatus } from '@/types/pipeline'

const STATUS_VARIANTS: Record<RunStatus, BadgeVariant> = {
  queued: 'neutral',
  running: 'accent',
  awaiting_manual: 'warning',
  succeeded: 'success',
  warning: 'warning',
  failed: 'danger',
  canceled: 'neutral',
  expired: 'neutral',
}

export default defineComponent({
  name: 'RunStatusPill',

  components: { AppBadge },

  props: {
    status: {
      type: String as () => RunStatus,
      required: true,
    },
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  computed: {
    variant(): BadgeVariant {
      return STATUS_VARIANTS[this.status]
    },
  },
})
</script>

<template>
  <AppBadge :variant="variant">{{ t(`pipeline.status.${status}`) }}</AppBadge>
</template>
