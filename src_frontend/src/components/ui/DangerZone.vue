<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'DangerZone',

  props: {
    title: { type: String, required: true },
    description: { type: String, default: undefined },
  },
})
</script>

<template>
  <section class="flex flex-col gap-3">
    <div>
      <h2 class="text-base font-semibold text-danger">{{ title }}</h2>
      <p v-if="description" class="mt-0.5 text-[13px] text-neutral-600">{{ description }}</p>
    </div>
    <div class="overflow-hidden rounded-lg border border-danger/40 bg-panel shadow-card">
      <slot />
    </div>
  </section>
</template>
