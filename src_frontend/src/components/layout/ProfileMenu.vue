<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ChevronDownIcon,
  ArrowRightStartOnRectangleIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'
import { UserCircleIcon as UserCircleIconSolid } from '@heroicons/vue/24/solid'
import { useAuthStore } from '@/stores/auth'

export default defineComponent({
  name: 'ProfileMenu',

  components: {
    ChevronDownIcon,
    ArrowRightStartOnRectangleIcon,
    UserCircleIcon,
    UserCircleIconSolid,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      isOpen: false,
    }
  },

  methods: {
    toggle() {
      this.isOpen = !this.isOpen
    },

    close() {
      this.isOpen = false
    },

    async logout() {
      this.close()
      const auth = useAuthStore()
      await auth.logout()
      this.$router.push({ name: 'login' })
    },
  },
})
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-1 hover:bg-surface"
      :aria-expanded="isOpen"
      :aria-label="t('profile.menu')"
      @click="toggle"
    >
      <UserCircleIconSolid class="h-7 w-7 text-accent" aria-hidden="true" />
      <ChevronDownIcon class="h-4 w-4 text-neutral-600" aria-hidden="true" />
    </button>

    <div v-if="isOpen" class="fixed inset-0 z-10" @click="close"></div>
    <div
      v-if="isOpen"
      class="absolute top-full right-0 z-20 mt-1.5 w-52 overflow-hidden rounded-lg border border-divider bg-panel shadow-pop"
    >
      <RouterLink
        :to="{ name: 'settings-account' }"
        class="flex items-center gap-2 border-b border-divider px-3 py-2.5 text-sm text-text last:border-b-0 hover:bg-surface"
        @click="close"
      >
        <UserCircleIcon class="h-4 w-4" aria-hidden="true" />
        {{ t('profile.editProfile') }}
      </RouterLink>
      <button
        type="button"
        class="flex w-full cursor-pointer items-center gap-2 px-3 py-2.5 text-left text-sm text-text hover:bg-surface"
        @click="logout"
      >
        <ArrowRightStartOnRectangleIcon class="h-4 w-4" aria-hidden="true" />
        {{ t('profile.logout') }}
      </button>
    </div>
  </div>
</template>
