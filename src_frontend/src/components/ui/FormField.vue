<script lang="ts">
import { defineComponent, type PropType } from 'vue'

export default defineComponent({
  name: 'FormField',

  props: {
    id: { type: String, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: undefined },
    errors: { type: Array as PropType<string[]>, default: () => [] },
  },

  computed: {
    describedBy(): string | undefined {
      const ids: string[] = []
      if (this.hint) ids.push(`${this.id}-hint`)
      if (this.errors.length > 0) ids.push(`${this.id}-error`)
      return ids.length > 0 ? ids.join(' ') : undefined
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-[13px] font-medium text-neutral-700">{{ label }}</label>
    <slot :described-by="describedBy" />
    <p v-if="hint" :id="`${id}-hint`" class="text-[13px] text-neutral-600">{{ hint }}</p>
    <p v-if="errors.length > 0" :id="`${id}-error`" class="text-[13px] text-danger-700">
      {{ errors.join(' ') }}
    </p>
  </div>
</template>
