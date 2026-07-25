<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import UploadActionSheet, { type UploadAction } from './UploadActionSheet.vue'

export default defineComponent({
  name: 'UploadFab',

  components: {
    UploadActionSheet,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return { isOpen: false }
  },

  methods: {
    toggle() {
      this.isOpen = !this.isOpen
    },
    close() {
      this.isOpen = false
    },
    onSelect(action: UploadAction) {
      // Receipt creation flow isn't built yet — this only closes the sheet.
      void action
      this.close()
    },
  },
})
</script>

<template>
  <div class="md:hidden">
    <button
      type="button"
      class="fixed right-5 bottom-5 z-20 flex h-12 w-12 items-center justify-center bg-accent2-600 text-2xl text-neutral-100 shadow-lg"
      :aria-label="t('receipts.newReceipt')"
      @click="toggle"
    >
      +
    </button>

    <div v-if="isOpen" class="fixed inset-0 z-10 bg-text/45" @click="close"></div>
    <UploadActionSheet v-if="isOpen" variant="sheet" @select="onSelect" />
  </div>
</template>
