<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'

interface SettingsTab {
  name: string
  label: string
}

export default defineComponent({
  name: 'SettingsSubNav',

  setup() {
    const { t } = useI18n()
    return { t }
  },

  computed: {
    tabs(): SettingsTab[] {
      return [
        { name: 'settings-account', label: this.t('settingsNav.account') },
        { name: 'settings-merchants', label: this.t('settingsNav.merchants') },
        { name: 'settings-products', label: this.t('settingsNav.products') },
        { name: 'settings-tags', label: this.t('settingsNav.tags') },
      ]
    },

    activeTabName(): string {
      // Az overlay gyerek-route-oknak saját nevük van (pl.
      // `settings-merchant-new`), ezért útvonal-előtag alapján egyeztetünk —
      // különben nyitott overlaynél egyik fül sem lenne aktív.
      const path = this.$route.path
      const match = this.tabs.find((tab) =>
        path.startsWith(this.$router.resolve({ name: tab.name }).path),
      )
      return match?.name ?? 'settings-account'
    },
  },

  methods: {
    onMobileSelect(event: Event) {
      const name = (event.target as HTMLSelectElement).value
      this.$router.push({ name })
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4 md:flex-row md:gap-8">
    <nav class="hidden w-48 shrink-0 flex-col gap-0.5 md:flex">
      <RouterLink
        v-for="tab in tabs"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="rounded-md px-3 py-2 text-sm transition-colors"
        :class="
          tab.name === activeTabName
            ? 'bg-surface font-semibold text-text'
            : 'text-neutral-600 hover:bg-surface/60 hover:text-text'
        "
      >
        {{ tab.label }}
      </RouterLink>
    </nav>

    <select
      class="rounded-md border border-neutral-300 bg-panel px-3 py-2 text-sm md:hidden"
      :value="activeTabName"
      @change="onMobileSelect"
    >
      <option v-for="tab in tabs" :key="tab.name" :value="tab.name">{{ tab.label }}</option>
    </select>

    <div class="flex-1">
      <RouterView />
    </div>
  </div>
</template>
