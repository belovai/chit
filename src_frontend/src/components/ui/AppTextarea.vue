<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppTextarea',

  props: {
    id: { type: String, required: true },
    modelValue: { type: String, required: true },
    rows: { type: Number, default: 3 },
    invalid: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  methods: {
    onInput(event: Event) {
      this.$emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
    },
  },
})
</script>

<template>
  <textarea
    :id="id"
    :rows="rows"
    :value="modelValue"
    :aria-invalid="invalid || undefined"
    class="w-full rounded-md border bg-bg px-3 py-2 text-sm text-text outline-none transition-shadow placeholder:text-neutral-500"
    :class="invalid ? 'border-danger' : 'border-neutral-300 focus:border-accent'"
    @input="onInput"
  />
</template>
