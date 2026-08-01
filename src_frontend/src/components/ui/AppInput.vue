<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppInput',

  props: {
    id: { type: String, required: true },
    modelValue: { type: String, required: true },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: undefined },
    autocomplete: { type: String, default: undefined },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  methods: {
    onInput(event: Event) {
      this.$emit('update:modelValue', (event.target as HTMLInputElement).value)
    },
  },
})
</script>

<template>
  <input
    :id="id"
    :type="type"
    :value="modelValue"
    :placeholder="placeholder"
    :autocomplete="autocomplete"
    :disabled="disabled"
    :aria-invalid="invalid || undefined"
    class="w-full rounded-md border bg-bg px-3 py-2 text-sm text-text outline-none transition-shadow placeholder:text-neutral-500 disabled:cursor-not-allowed disabled:opacity-60"
    :class="invalid ? 'border-danger' : 'border-neutral-300 focus:border-accent'"
    @input="onInput"
  />
</template>
