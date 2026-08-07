<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppListItem',

  props: {
    interactive: { type: Boolean, default: false },
    /** Cross-axis alignment; `start` reads better when the main slot is multi-line. */
    align: { type: String as () => 'center' | 'start', default: 'center' },
  },

  computed: {
    alignClass(): string {
      return this.align === 'start' ? 'items-start' : 'items-center'
    },
  },

  emits: ['click'],

  methods: {
    onClick() {
      if (this.interactive) {
        this.$emit('click')
      }
    },
  },
})
</script>

<template>
  <component
    :is="interactive ? 'button' : 'div'"
    :type="interactive ? 'button' : undefined"
    class="flex w-full gap-3 px-5 py-3.5 text-left"
    :class="[alignClass, interactive ? 'cursor-pointer transition-colors hover:bg-surface' : '']"
    @click="onClick"
  >
    <span v-if="$slots.leading" class="shrink-0">
      <slot name="leading" />
    </span>
    <span class="min-w-0 flex-1">
      <slot />
    </span>
    <span v-if="$slots.trailing" class="flex shrink-0 items-center gap-3">
      <slot name="trailing" />
    </span>
  </component>
</template>
