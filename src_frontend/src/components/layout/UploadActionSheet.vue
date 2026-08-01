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
    :class="
      variant === 'sheet'
        ? 'fixed inset-x-0 bottom-0 z-40 rounded-t-xl border-t border-divider bg-panel shadow-modal'
        : 'absolute top-full right-0 z-20 mt-1.5 w-56 overflow-hidden rounded-lg border border-divider bg-panel shadow-pop'
    "
    :style="variant === 'sheet' ? { paddingBottom: 'env(safe-area-inset-bottom)' } : undefined"
  >
    <button
      type="button"
      class="block w-full cursor-pointer border-b border-divider px-4 py-3 text-left text-sm last:border-b-0 hover:bg-surface"
      @click="select('photo')"
    >
      {{ t('receipts.takePhoto') }}
    </button>
    <button
      type="button"
      class="block w-full cursor-pointer border-b border-divider px-4 py-3 text-left text-sm last:border-b-0 hover:bg-surface"
      @click="select('file')"
    >
      {{ t('receipts.uploadFile') }}
    </button>
    <button
      type="button"
      class="block w-full cursor-pointer border-b border-divider px-4 py-3 text-left text-sm last:border-b-0 hover:bg-surface"
      @click="select('manual')"
    >
      {{ t('receipts.manualEntry') }}
    </button>
  </div>
</template>
