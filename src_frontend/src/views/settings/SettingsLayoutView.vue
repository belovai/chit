<script lang="ts">
import { defineComponent } from 'vue'
import SettingsSubNav from '@/components/layout/SettingsSubNav.vue'

export default defineComponent({
  name: 'SettingsLayoutView',

  components: {
    SettingsSubNav,
  },
})
</script>

<template>
  <SettingsSubNav />
</template>
