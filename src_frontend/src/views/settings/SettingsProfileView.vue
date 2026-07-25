<script lang="ts">
import { defineComponent } from 'vue'
import { mapActions, mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'

export default defineComponent({
  name: 'SettingsProfileView',

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
  <div class="border border-divider bg-surface p-6">
    <p class="mb-6 text-sm text-neutral-600">
      {{ t('profile.greeting', { name: user?.name ?? '', email: user?.email ?? '' }) }}
    </p>
    <AppButton variant="ghost" @click="onLogout">{{ t('profile.logout') }}</AppButton>
  </div>
</template>
