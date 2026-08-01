<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppCardRow from '@/components/ui/AppCardRow.vue'
import DangerZone from '@/components/ui/DangerZone.vue'

export default defineComponent({
  name: 'SettingsAccountView',

  components: {
    AppSection,
    AppCard,
    AppCardRow,
    DangerZone,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },
})
</script>

<template>
  <div class="flex flex-col gap-8">
    <AppSection :title="t('profile.general')">
      <AppCard :padded="false">
        <div class="divide-y divide-divider">
          <AppCardRow :label="t('profile.generalNamePlaceholder')" />
          <AppCardRow :label="t('profile.generalEmailPlaceholder')" />
          <AppCardRow :label="t('profile.generalAvatarPlaceholder')" />
        </div>
      </AppCard>
    </AppSection>

    <AppSection :title="t('profile.security')">
      <AppCard :padded="false">
        <div class="divide-y divide-divider">
          <AppCardRow :label="t('profile.securityPasswordPlaceholder')" />
          <AppCardRow :label="t('profile.securityTwoFactorPlaceholder')" />
        </div>
      </AppCard>
    </AppSection>

    <DangerZone :title="t('profile.danger')">
      <AppCardRow :label="t('profile.dangerDeletePlaceholder')" />
    </DangerZone>
  </div>
</template>
