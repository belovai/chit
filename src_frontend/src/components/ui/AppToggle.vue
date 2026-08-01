<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppToggle',

  props: {
    id: { type: String, required: true },
    modelValue: { type: Boolean, required: true },
    label: { type: String, required: true },
  },

  emits: ['update:modelValue'],
})
</script>

<template>
  <button
    :id="id"
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :aria-label="label"
    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors"
    :class="modelValue ? 'bg-accent' : 'bg-neutral-400'"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span
      class="inline-block h-4 w-4 transform rounded-full bg-neutral-100 transition-transform"
      :class="modelValue ? 'translate-x-4.5' : 'translate-x-0.5'"
    ></span>
  </button>
</template>
