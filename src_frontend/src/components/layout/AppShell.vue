<script lang="ts">
import { defineComponent } from 'vue'
import TopNav from './TopNav.vue'
import MobileHeader from './MobileHeader.vue'
import UploadFab from './UploadFab.vue'

export default defineComponent({
  name: 'AppShell',

  components: {
    TopNav,
    MobileHeader,
    UploadFab,
  },
})
</script>

<template>
  <div class="min-h-screen">
    <TopNav />
    <MobileHeader />
    <!-- pb-28 mobilon: a lebegő FAB (56px + 20px offset) ne takarja el a lista
         utolsó elemét hosszú listáknál. -->
    <main class="mx-auto w-full max-w-7xl px-4 pt-6 pb-28 md:px-8 md:py-8">
      <RouterView />
    </main>
    <UploadFab />
  </div>
</template>
