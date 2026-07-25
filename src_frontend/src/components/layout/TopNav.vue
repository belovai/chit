<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import logoUrl from '@/assets/logo.svg'
import NavTabs from './NavTabs.vue'
import NotificationBell from './NotificationBell.vue'
import ProfileMenu from './ProfileMenu.vue'

export default defineComponent({
  name: 'TopNav',

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
  <header class="hidden items-center gap-6 border-b border-divider px-8 py-3 md:flex">
    <img :src="logoUrl" :alt="t('nav.logo')" class="h-6 w-auto" />

    <NavTabs />

    <div class="ml-auto flex items-center gap-3">
      <NotificationBell />
      <ProfileMenu />
    </div>
  </header>
</template>
