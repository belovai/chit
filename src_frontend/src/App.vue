<script lang="ts">
import { defineComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useHeartbeatStore } from '@/stores/heartbeat'

export default defineComponent({
  name: 'App',

  mounted() {
    if (useAuthStore().isAuthenticated) {
      useHeartbeatStore().start()
    }
  },
})
</script>

<template>
  <RouterView />
</template>
