<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppEmptyState',

  props: {
    title: { type: String, required: true },
    description: { type: String, default: undefined },
  },
})
</script>

<template>
  <div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
    <div v-if="$slots.icon" class="text-neutral-400">
      <slot name="icon" />
    </div>
    <div>
      <p class="text-sm font-semibold text-text">{{ title }}</p>
      <p v-if="description" class="mt-1 text-[13px] text-neutral-600">{{ description }}</p>
    </div>
    <div v-if="$slots.action" class="mt-1">
      <slot name="action" />
    </div>
  </div>
</template>
