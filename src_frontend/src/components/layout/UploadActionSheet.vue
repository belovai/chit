<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

export type UploadAction = 'photo' | 'file' | 'manual'

export default defineComponent({
  name: 'UploadActionSheet',

  props: {
    variant: {
      type: String as () => 'sheet' | 'dropdown',
      default: 'sheet',
    },
  },

  emits: ['select'],

  setup() {
    const { t } = useI18n()
    return { t }
  },

  methods: {
    select(action: UploadAction) {
      this.$emit('select', action)
    },
  },
})
</script>

<template>
  <div
    class="border border-divider bg-bg shadow-lg"
    :class="
      variant === 'sheet' ? 'fixed inset-x-0 bottom-0 z-20' : 'absolute top-full right-0 z-20 w-56'
    "
  >
    <button
      type="button"
      class="block w-full border-b border-divider px-4 py-3 text-left text-sm"
      @click="select('photo')"
    >
      {{ t('receipts.takePhoto') }}
    </button>
    <button
      type="button"
      class="block w-full border-b border-divider px-4 py-3 text-left text-sm"
      @click="select('file')"
    >
      {{ t('receipts.uploadFile') }}
    </button>
    <button
      type="button"
      class="block w-full px-4 py-3 text-left text-sm"
      @click="select('manual')"
    >
      {{ t('receipts.manualEntry') }}
    </button>
  </div>
</template>
