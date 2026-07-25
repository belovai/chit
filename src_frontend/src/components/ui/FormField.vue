<script lang="ts">
import { defineComponent, type PropType } from 'vue'

export default defineComponent({
  name: 'FormField',

  props: {
    id: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      required: true,
    },
    type: {
      type: String,
      default: 'text',
    },
    modelValue: {
      type: String,
      required: true,
    },
    autocomplete: {
      type: String,
      default: undefined,
    },
    errors: {
      type: Array as PropType<string[]>,
      default: () => [],
    },
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
  <div class="flex flex-col gap-1">
    <label :for="id" class="text-xs font-semibold text-neutral-700">{{ label }}</label>
    <input
      :id="id"
      :type="type"
      :autocomplete="autocomplete"
      :value="modelValue"
      class="border border-neutral-400 bg-bg px-3 py-2 text-sm text-text outline-none placeholder:text-neutral-500 focus:border-accent"
      :class="{ 'border-danger': errors.length > 0 }"
      @input="onInput"
    />
    <p v-for="message in errors" :key="message" class="text-xs text-danger-700">{{ message }}</p>
  </div>
</template>
