<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppCard',

  props: {
    title: { type: String, default: undefined },
    description: { type: String, default: undefined },
    padded: { type: Boolean, default: true },
  },
})
</script>

<template>
  <section class="overflow-hidden rounded-lg border border-divider bg-panel shadow-card">
    <header v-if="title || $slots.header" class="border-b border-divider bg-surface px-5 py-3.5">
      <slot name="header">
        <h2 class="text-sm font-semibold text-text">{{ title }}</h2>
        <p v-if="description" class="mt-0.5 text-[13px] text-neutral-600">{{ description }}</p>
      </slot>
    </header>

    <div :class="padded ? 'px-5 py-4' : ''">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="border-t border-divider bg-surface px-5 py-3">
      <slot name="footer" />
    </footer>
  </section>
</template>
