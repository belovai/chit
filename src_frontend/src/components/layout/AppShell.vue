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
    <main class="px-4 py-6 md:px-8">
      <RouterView />
    </main>
    <UploadFab />
  </div>
</template>
