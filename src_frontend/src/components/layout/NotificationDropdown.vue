<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { useI18n } from 'vue-i18n'

export interface NotificationItem {
  id: string
  message: string
  timestamp: string
  read: boolean
}

export default defineComponent({
  name: 'NotificationDropdown',

  props: {
    items: {
      type: Array as PropType<NotificationItem[]>,
      default: () => [],
    },
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },
})
</script>

<template>
  <div class="w-64 border border-divider bg-bg shadow-lg">
    <p
      class="border-b border-divider px-3 py-2 text-xs font-semibold tracking-wide text-neutral-600 uppercase"
    >
      {{ t('notifications.title') }}
    </p>

    <p v-if="items.length === 0" class="px-3 py-3 text-xs text-neutral-600">
      {{ t('notifications.empty') }}
    </p>
    <ul v-else>
      <li v-for="item in items" :key="item.id" class="border-b border-divider px-3 py-2.5 text-xs">
        <p class="text-text">
          <span
            v-if="!item.read"
            class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-accent2-600 align-middle"
          ></span>
          {{ item.message }}
        </p>
        <p class="mt-0.5 text-neutral-500">{{ item.timestamp }}</p>
      </li>
    </ul>

    <p class="px-3 py-2 text-center text-xs text-accent">{{ t('notifications.viewAll') }}</p>
  </div>
</template>
