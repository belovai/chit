<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppSection',

  props: {
    title: { type: String, required: true },
    description: { type: String, default: undefined },
  },
})
</script>

<template>
  <section class="flex flex-col gap-3">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-base font-semibold text-text">{{ title }}</h2>
        <p v-if="description" class="mt-0.5 text-[13px] text-neutral-600">{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="shrink-0">
        <slot name="actions" />
      </div>
    </div>
    <slot />
  </section>
</template>
