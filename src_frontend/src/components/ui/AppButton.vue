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
    disabled: {
      type: Boolean,
      default: false,
    },
  },
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="border font-[family-name:var(--font-heading)] font-semibold px-4 py-2.5 text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60"
    :class="
      variant === 'primary'
        ? 'bg-accent border-accent text-neutral-100 hover:bg-accent-600 hover:border-accent-600'
        : variant === 'danger'
          ? 'bg-danger border-danger text-neutral-100 hover:bg-danger-700 hover:border-danger-700'
          : 'border-transparent text-accent hover:text-accent-600'
    "
  >
    <slot />
  </button>
</template>
