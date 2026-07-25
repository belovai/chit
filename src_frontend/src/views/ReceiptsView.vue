<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import UploadActionSheet, { type UploadAction } from '@/components/layout/UploadActionSheet.vue'

export default defineComponent({
  name: 'ReceiptsView',

  components: {
    AppButton,
    UploadActionSheet,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return { isMenuOpen: false }
  },

  methods: {
    toggleMenu() {
      this.isMenuOpen = !this.isMenuOpen
    },
    closeMenu() {
      this.isMenuOpen = false
    },
    onSelect(action: UploadAction) {
      // Receipt creation flow isn't built yet — this only closes the menu.
      void action
      this.closeMenu()
    },
  },
})
</script>

<template>
  <div>
    <div class="mb-4 hidden justify-end md:flex">
      <div class="relative">
        <AppButton @click="toggleMenu">{{ t('receipts.newReceipt') }}</AppButton>
        <div v-if="isMenuOpen" class="fixed inset-0 z-10" @click="closeMenu"></div>
        <UploadActionSheet v-if="isMenuOpen" variant="dropdown" @select="onSelect" />
      </div>
    </div>

    <div class="border border-divider bg-surface p-6 text-sm text-neutral-600">
      {{ t('receipts.placeholder') }}
    </div>
  </div>
</template>
