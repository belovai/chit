<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppStatTile',

  props: {
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: undefined },
  },
})
</script>

<template>
  <div class="rounded-lg border border-divider bg-panel px-5 py-4 shadow-card">
    <p class="text-[13px] text-neutral-600">{{ label }}</p>
    <p class="mt-1 text-2xl font-semibold text-text">{{ value }}</p>
    <p v-if="hint" class="mt-2 text-[13px] text-neutral-600">{{ hint }}</p>
  </div>
</template>
