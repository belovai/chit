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
        { name: 'settings-tags', label: this.t('settingsNav.tags') },
      ]
    },

    activeTabName(): string {
      return (this.$route.name as string) ?? 'settings-account'
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
    <nav class="hidden w-40 flex-col md:flex">
      <RouterLink
        v-for="tab in tabs"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="px-3 py-2 text-sm"
        :class="
          tab.name === activeTabName
            ? 'border-l-2 border-accent bg-surface font-semibold text-text'
            : 'text-neutral-700 hover:text-text'
        "
      >
        {{ tab.label }}
      </RouterLink>
    </nav>

    <select
      class="border border-neutral-400 bg-bg px-3 py-2 text-sm md:hidden"
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
