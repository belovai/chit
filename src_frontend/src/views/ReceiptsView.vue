<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import UploadActionSheet, { type UploadAction } from '@/components/layout/UploadActionSheet.vue'

export default defineComponent({
  name: 'ReceiptsView',

  components: {
    AppButton,
    AppSection,
    AppCard,
    AppEmptyState,
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
      this.closeMenu()
      if (action === 'manual') {
        this.$router.push({ name: 'transaction-new' })
      }
      // 'photo' / 'file': receipt upload flow isn't built yet, no-op.
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppSection :title="t('nav.receipts')">
      <template #actions>
        <div class="relative hidden md:block">
          <AppButton @click="toggleMenu">{{ t('receipts.newReceipt') }}</AppButton>
          <div v-if="isMenuOpen" class="fixed inset-0 z-10" @click="closeMenu"></div>
          <UploadActionSheet v-if="isMenuOpen" variant="dropdown" @select="onSelect" />
        </div>
      </template>
    </AppSection>

    <AppCard :padded="false">
      <AppEmptyState :title="t('receipts.placeholder')" />
    </AppCard>
  </div>
</template>
