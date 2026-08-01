<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

export default defineComponent({
  name: 'NavTabs',

  props: {
    dense: { type: Boolean, default: false },
  },

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

    baseTabClass(): string {
      return [
        'border-b-2 border-transparent text-sm font-medium text-neutral-600',
        'transition-colors hover:text-text',
        this.dense ? 'pb-2' : 'pb-2.5',
      ].join(' ')
    },

    activeTabClass(): string {
      return 'border-accent text-text'
    },
  },
})
</script>

<template>
  <nav class="flex text-sm" :class="dense ? 'gap-4' : 'gap-6'">
    <RouterLink
      :to="{ name: 'dashboard' }"
      :class="baseTabClass"
      :exact-active-class="activeTabClass"
    >
      {{ t('nav.dashboard') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'receipts' }"
      class="flex items-center gap-1.5"
      :class="baseTabClass"
      :active-class="activeTabClass"
    >
      {{ t('nav.receipts') }}
      <span
        v-if="needsReviewCount > 0"
        class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent2-600 px-1 text-[10px] font-semibold text-neutral-100"
      >
        {{ needsReviewCount }}
      </span>
    </RouterLink>
    <RouterLink :to="{ name: 'transactions' }" :class="baseTabClass" :active-class="activeTabClass">
      {{ t('nav.transactions') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'settings-account' }"
      :class="[baseTabClass, isSettingsActive ? activeTabClass : '']"
    >
      {{ t('nav.settings') }}
    </RouterLink>
  </nav>
</template>
