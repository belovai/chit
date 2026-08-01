<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppCardRow',

  props: {
    label: { type: String, required: true },
    description: { type: String, default: undefined },
  },
})
</script>

<template>
  <div
    class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6"
  >
    <div class="min-w-0">
      <p class="text-sm font-medium text-text">{{ label }}</p>
      <p v-if="description" class="mt-0.5 text-[13px] text-neutral-600">{{ description }}</p>
    </div>
    <div v-if="$slots.default" class="shrink-0 sm:text-right">
      <slot />
    </div>
  </div>
</template>
