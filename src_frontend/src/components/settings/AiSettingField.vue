<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'
import FormField from '@/components/ui/FormField.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppToggle from '@/components/ui/AppToggle.vue'
import type { AiSettingField } from '@/types/ai'

function humanise(key: string): string {
  const words = key.replace(/_/g, ' ')
  return words.charAt(0).toUpperCase() + words.slice(1)
}

export default defineComponent({
  name: 'AiSettingField',

  components: {
    FormField,
    AppInput,
    AppSelect,
    AppToggle,
  },

  props: {
    field: { type: Object as PropType<AiSettingField>, required: true },
    modelValue: { type: null as unknown as PropType<unknown>, required: true },
    errors: { type: Array as PropType<string[]>, default: () => [] },
  },

  emits: ['update:modelValue'],

  setup() {
    const { t, te } = useI18n()
    return { t, te }
  },

  computed: {
    inputId(): string {
      return `ai-setting-${this.field.key}`
    },

    // A provider setting with no translation still has to render, so the key
    // itself is the fallback label.
    label(): string {
      const key = `ai.settings.${this.field.key}.label`
      return this.te(key) ? this.t(key) : humanise(this.field.key)
    },

    hint(): string | undefined {
      const key = `ai.settings.${this.field.key}.hint`
      return this.te(key) ? this.t(key) : undefined
    },

    stringValue(): string {
      return this.modelValue === null || this.modelValue === undefined
        ? ''
        : String(this.modelValue)
    },

    booleanValue(): boolean {
      return this.modelValue === true
    },
  },

  methods: {
    onIntInput(value: string) {
      // An empty field emits an empty string rather than 0, so the server
      // reports "required" instead of silently accepting a zero.
      this.$emit('update:modelValue', value === '' ? '' : Number(value))
    },

    onEnumInput(value: string) {
      this.$emit('update:modelValue', value)
    },

    onBoolInput(value: boolean) {
      this.$emit('update:modelValue', value)
    },
  },
})
</script>

<template>
  <FormField
    v-if="field.type === 'bool'"
    :id="inputId"
    :label="label"
    :hint="hint"
    :errors="errors"
  >
    <AppToggle
      :id="inputId"
      :model-value="booleanValue"
      :label="label"
      @update:model-value="onBoolInput"
    />
  </FormField>

  <FormField
    v-else-if="field.type === 'enum'"
    :id="inputId"
    v-slot="{ describedBy }"
    :label="label"
    :hint="hint"
    :errors="errors"
  >
    <AppSelect
      :id="inputId"
      :model-value="stringValue"
      :invalid="errors.length > 0"
      :aria-describedby="describedBy"
      @update:model-value="onEnumInput"
    >
      <option v-for="option in field.options ?? []" :key="option" :value="option">
        {{ option }}
      </option>
    </AppSelect>
  </FormField>

  <FormField
    v-else
    :id="inputId"
    v-slot="{ describedBy }"
    :label="label"
    :hint="hint"
    :errors="errors"
  >
    <AppInput
      :id="inputId"
      type="number"
      :model-value="stringValue"
      :invalid="errors.length > 0"
      :aria-describedby="describedBy"
      :min="field.min"
      :max="field.max"
      @update:model-value="onIntInput"
    />
  </FormField>
</template>
