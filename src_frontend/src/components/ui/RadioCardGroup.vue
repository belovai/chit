<script lang="ts">
import { defineComponent, type PropType } from 'vue'

export interface RadioCardOption {
  value: string
  label: string
  description?: string
}

export default defineComponent({
  name: 'RadioCardGroup',

  props: {
    modelValue: { type: String, required: true },
    options: { type: Array as PropType<RadioCardOption[]>, required: true },
    name: { type: String, required: true },
  },

  emits: ['update:modelValue'],
})
</script>

<template>
  <div class="flex flex-col gap-2">
    <label
      v-for="option in options"
      :key="option.value"
      class="flex cursor-pointer gap-3 rounded-lg border px-4 py-3 transition-colors"
      :class="
        modelValue === option.value
          ? 'border-accent bg-accent-100'
          : 'border-divider bg-panel hover:border-neutral-400'
      "
    >
      <input
        type="radio"
        :name="name"
        :value="option.value"
        :checked="modelValue === option.value"
        class="mt-0.5 h-4 w-4 accent-[var(--color-accent)]"
        @change="$emit('update:modelValue', option.value)"
      />
      <span class="min-w-0">
        <span class="block text-sm font-medium text-text">{{ option.label }}</span>
        <span v-if="option.description" class="mt-0.5 block text-[13px] text-neutral-600">
          {{ option.description }}
        </span>
      </span>
    </label>
  </div>
</template>
