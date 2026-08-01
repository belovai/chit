<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

import AppCard from '@/components/ui/AppCard.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'

export default defineComponent({
  name: 'SettingsTagsView',

  components: {
    AppCard,
    AppEmptyState,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },
})
</script>

<template>
  <AppCard :padded="false">
    <AppEmptyState :title="t('settingsNav.tagsPlaceholder')" />
  </AppCard>
</template>
