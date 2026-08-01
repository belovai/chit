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
      this.close()
      if (action === 'manual') {
        this.$router.push({ name: 'transaction-new' })
      }
      // 'photo' / 'file': receipt upload flow isn't built yet, no-op.
    },
  },
})
</script>

<template>
  <div class="md:hidden">
    <button
      type="button"
      class="fixed right-5 z-30 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-accent2-600 text-2xl text-neutral-100 shadow-pop"
      :style="{ bottom: 'max(1.25rem, env(safe-area-inset-bottom))' }"
      :aria-label="t('receipts.newReceipt')"
      @click="toggle"
    >
      +
    </button>

    <div v-if="isOpen" class="fixed inset-0 z-30 bg-text/45" @click="close"></div>
    <UploadActionSheet v-if="isOpen" variant="sheet" @select="onSelect" />
  </div>
</template>
