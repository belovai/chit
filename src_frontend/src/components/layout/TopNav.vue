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
  <header class="hidden border-b border-divider bg-panel md:block">
    <div class="mx-auto flex w-full max-w-7xl items-center gap-3 px-8 pt-3 pb-2">
      <img :src="logoUrl" :alt="t('nav.logo')" class="h-6 w-auto" />

      <div class="ml-auto flex items-center gap-3">
        <NotificationBell />
        <ProfileMenu />
      </div>
    </div>

    <div class="mx-auto w-full max-w-7xl px-8">
      <NavTabs />
    </div>
  </header>
</template>
