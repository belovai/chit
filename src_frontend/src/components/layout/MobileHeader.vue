<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import logoUrl from '@/assets/logo.svg'
import NavTabs from './NavTabs.vue'
import NotificationBell from './NotificationBell.vue'
import ProfileMenu from './ProfileMenu.vue'

export default defineComponent({
  name: 'MobileHeader',

  components: {
    NavTabs,
    NotificationBell,
    ProfileMenu,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      logoUrl,
    }
  },
})
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-divider bg-panel md:hidden">
    <div class="flex items-center gap-3 px-4 py-3">
      <img :src="logoUrl" :alt="t('nav.logo')" class="h-6 w-auto" />

      <div class="ml-auto flex items-center gap-2">
        <NotificationBell />
        <ProfileMenu />
      </div>
    </div>

    <div class="overflow-x-auto px-4">
      <NavTabs dense />
    </div>
  </header>
</template>
