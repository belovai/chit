<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import { BellIcon } from '@heroicons/vue/24/outline'
import NotificationDropdown, { type NotificationItem } from './NotificationDropdown.vue'

export default defineComponent({
  name: 'NotificationBell',

  components: {
    NotificationDropdown,
    BellIcon,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      isOpen: false,
      // Placeholder data — replaced once a notification backend exists (out of
      // scope for this shell/IA spec).
      items: [
        {
          id: '1',
          message: 'Receipt processed — awaiting review',
          timestamp: '2m ago',
          read: false,
        },
        { id: '2', message: '3 receipts processing', timestamp: '10m ago', read: true },
      ] as NotificationItem[],
    }
  },

  computed: {
    hasUnread(): boolean {
      return this.items.some((item) => !item.read)
    },
  },

  methods: {
    toggle() {
      this.isOpen = !this.isOpen
    },
    close() {
      this.isOpen = false
    },
  },
})
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="relative flex h-6 w-6 cursor-pointer items-center justify-center"
      :aria-label="t('notifications.title')"
      @click="toggle"
    >
      <BellIcon class="h-5 w-5 text-neutral-700" aria-hidden="true" />
      <span
        v-if="hasUnread"
        class="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full bg-accent2-600"
        aria-hidden="true"
      ></span>
    </button>

    <div v-if="isOpen" class="fixed inset-0 z-10" @click="close"></div>
    <NotificationDropdown v-if="isOpen" :items="items" class="absolute top-full right-0 z-20" />
  </div>
</template>
