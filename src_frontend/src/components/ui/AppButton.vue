<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppButton',

  props: {
    type: {
      type: String as () => 'button' | 'submit',
      default: 'button',
    },
    variant: {
      type: String as () => 'primary' | 'ghost' | 'danger',
      default: 'primary',
    },
    size: {
      type: String as () => 'sm' | 'md',
      default: 'md',
    },
    block: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    variantClass(): string {
      if (this.variant === 'primary') {
        return 'bg-accent border-accent text-neutral-100 hover:bg-accent-600 hover:border-accent-600'
      }
      if (this.variant === 'danger') {
        return 'bg-danger border-danger text-neutral-100 hover:bg-danger-700 hover:border-danger-700'
      }
      return 'border-neutral-300 bg-panel text-text hover:bg-surface'
    },

    sizeClass(): string {
      return this.size === 'sm' ? 'px-2.5 py-1.5 text-[13px]' : 'px-3.5 py-2 text-sm'
    },
  },
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex cursor-pointer items-center justify-center rounded-md border font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
    :class="[variantClass, sizeClass, block ? 'w-full' : '']"
  >
    <slot />
  </button>
</template>
