<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppSelect',

  props: {
    id: { type: String, required: true },
    modelValue: { type: String, required: true },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  methods: {
    onChange(event: Event) {
      this.$emit('update:modelValue', (event.target as HTMLSelectElement).value)
    },
  },
})
</script>

<template>
  <select
    :id="id"
    :value="modelValue"
    :disabled="disabled"
    :aria-invalid="invalid || undefined"
    class="w-full rounded-md border bg-bg px-3 py-2 text-sm text-text outline-none transition-shadow disabled:cursor-not-allowed disabled:opacity-60"
    :class="invalid ? 'border-danger' : 'border-neutral-300 focus:border-accent'"
    @change="onChange"
  >
    <slot />
  </select>
</template>
