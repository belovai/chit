<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

export default defineComponent({
  name: 'SettingsTagsView',

  setup() {
    const { t } = useI18n()
    return { t }
  },
})
</script>

<template>
  <div class="border border-divider bg-surface p-6 text-sm text-neutral-600">
    {{ t('settingsNav.tagsPlaceholder') }}
  </div>
</template>
