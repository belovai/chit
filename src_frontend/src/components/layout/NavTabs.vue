<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

export default defineComponent({
  name: 'NavTabs',

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      // Placeholder — replaced once the Receipt module exposes a real
      // needs-review count query.
      needsReviewCount: 3,
    }
  },

  computed: {
    isSettingsActive(): boolean {
      return this.$route.path.startsWith('/settings')
    },
  },
})
</script>

<template>
  <nav class="flex gap-5 text-sm">
    <RouterLink
      :to="{ name: 'dashboard' }"
      class="border-b-2 border-transparent pb-1 text-neutral-700 hover:text-text"
      exact-active-class="border-accent text-text font-semibold"
    >
      {{ t('nav.dashboard') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'receipts' }"
      class="flex items-center gap-1 border-b-2 border-transparent pb-1 text-neutral-700 hover:text-text"
      active-class="border-accent text-text font-semibold"
    >
      {{ t('nav.receipts') }}
      <span
        v-if="needsReviewCount > 0"
        class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent2-600 px-1 text-[10px] font-semibold text-neutral-100"
      >
        {{ needsReviewCount }}
      </span>
    </RouterLink>
    <RouterLink
      :to="{ name: 'transactions' }"
      class="border-b-2 border-transparent pb-1 text-neutral-700 hover:text-text"
      active-class="border-accent text-text font-semibold"
    >
      {{ t('nav.transactions') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'settings-profile' }"
      class="border-b-2 pb-1"
      :class="
        isSettingsActive
          ? 'border-accent text-text font-semibold'
          : 'border-transparent text-neutral-700 hover:text-text'
      "
    >
      {{ t('nav.settings') }}
    </RouterLink>
  </nav>
</template>
