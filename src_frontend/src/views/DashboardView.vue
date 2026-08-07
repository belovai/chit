<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

import AppCard from '@/components/ui/AppCard.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppStatTile from '@/components/ui/AppStatTile.vue'

export default defineComponent({
  name: 'DashboardView',

  components: {
    AppCard,
    AppEmptyState,
    AppStatTile,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Values are intentionally empty: there's no data source behind them yet,
         and wiring up dashboard data fetching is out of scope for this round. -->
    <div class="grid gap-4 sm:grid-cols-3">
      <AppStatTile :label="t('dashboard.statSpend')" value="—" />
      <AppStatTile :label="t('dashboard.statReceipts')" value="—" />
      <AppStatTile :label="t('dashboard.statMerchants')" value="—" />
    </div>

    <AppCard :padded="false">
      <AppEmptyState :title="t('dashboard.placeholder')" />
    </AppCard>
  </div>
</template>
