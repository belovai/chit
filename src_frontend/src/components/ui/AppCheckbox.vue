<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppCheckbox',

  props: {
    id: { type: String, required: true },
    modelValue: { type: Boolean, required: true },
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  methods: {
    onChange(event: Event) {
      this.$emit('update:modelValue', (event.target as HTMLInputElement).checked)
    },
  },
})
</script>

<template>
  <label :for="id" class="flex cursor-pointer items-center gap-2 text-sm text-text">
    <input
      :id="id"
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      class="h-4 w-4 rounded-sm border-neutral-400 accent-[var(--color-accent)]"
      @change="onChange"
    />
    {{ label }}
  </label>
</template>
