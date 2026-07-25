<script lang="ts">
import { defineComponent } from 'vue'
import { mapActions, mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'

export default defineComponent({
  name: 'DashboardView',

  components: {
    AppButton,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  computed: {
    ...mapState(useAuthStore, ['user']),
  },

  methods: {
    ...mapActions(useAuthStore, ['logout']),

    async onLogout() {
      await this.logout()
      await this.$router.push({ name: 'login' })
    },
  },
})
</script>

<template>
  <main class="flex min-h-screen flex-col items-center justify-center gap-4 px-4">
    <div class="w-full max-w-sm border border-divider bg-surface p-6 text-center">
      <h1 class="text-2xl mb-2">Chit</h1>
      <p class="text-sm text-neutral-600 mb-6">
        {{ t('dashboard.greeting', { name: user?.name ?? '', email: user?.email ?? '' }) }}
      </p>
      <AppButton variant="ghost" @click="onLogout">{{ t('dashboard.logout') }}</AppButton>
    </div>
  </main>
</template>
