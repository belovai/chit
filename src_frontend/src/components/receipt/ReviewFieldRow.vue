<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppBadge from '@/components/ui/AppBadge.vue'

export default defineComponent({
  name: 'ReviewFieldRow',

  components: { AppBadge },

  props: {
    field: { type: String, required: true },
    flagged: { type: Boolean, default: false },
  },

  setup() {
    const { t, te } = useI18n()
    return { t, te }
  },

  computed: {
    label(): string {
      const key = `receipts.fields.${this.field}`
      return this.te(key) ? this.t(key) : this.field
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-1 border-b border-divider py-2 last:border-0">
    <div class="flex items-center gap-2">
      <span class="text-xs font-medium">{{ label }}</span>
      <AppBadge v-if="flagged" variant="warning">!</AppBadge>
    </div>
    <slot />
  </div>
</template>
