<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

export default defineComponent({
  name: 'SettingsAccountView',

  setup() {
    const { t } = useI18n()
    return { t }
  },
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <section class="border border-divider bg-surface p-6">
      <h2 class="mb-4 text-base font-semibold text-text">{{ t('profile.general') }}</h2>
      <div class="flex flex-col gap-3 text-sm text-neutral-600">
        <div class="border border-divider p-4">{{ t('profile.generalNamePlaceholder') }}</div>
        <div class="border border-divider p-4">{{ t('profile.generalEmailPlaceholder') }}</div>
        <div class="border border-divider p-4">{{ t('profile.generalAvatarPlaceholder') }}</div>
      </div>
    </section>

    <section class="border border-divider bg-surface p-6">
      <h2 class="mb-4 text-base font-semibold text-text">{{ t('profile.security') }}</h2>
      <div class="flex flex-col gap-3 text-sm text-neutral-600">
        <div class="border border-divider p-4">{{ t('profile.securityPasswordPlaceholder') }}</div>
        <div class="border border-divider p-4">{{ t('profile.securityTwoFactorPlaceholder') }}</div>
      </div>
    </section>

    <section class="border border-danger bg-surface p-6">
      <h2 class="mb-4 text-base font-semibold text-danger">{{ t('profile.danger') }}</h2>
      <div class="flex flex-col gap-3 text-sm text-neutral-600">
        <div class="border border-divider p-4">{{ t('profile.dangerDeletePlaceholder') }}</div>
      </div>
    </section>
  </div>
</template>
