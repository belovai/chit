<script lang="ts">
import { defineComponent } from 'vue'

const VARIANT_CLASSES = {
  neutral: 'bg-surface text-neutral-700 border-divider',
  accent: 'bg-accent-100 text-accent-700 border-accent-200',
  success: 'bg-success-100 text-success-700 border-success/25',
  warning: 'bg-warning-100 text-warning-700 border-warning/30',
  danger: 'bg-danger-100 text-danger-700 border-danger/25',
} as const

export type BadgeVariant = keyof typeof VARIANT_CLASSES

export default defineComponent({
  name: 'AppBadge',

  props: {
    variant: {
      type: String as () => BadgeVariant,
      default: 'neutral',
    },
  },

  computed: {
    variantClass(): string {
      return VARIANT_CLASSES[this.variant]
    },
  },
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-sm border px-1.5 py-0.5 text-[11px] font-medium"
    :class="variantClass"
  >
    <slot />
  </span>
</template>
