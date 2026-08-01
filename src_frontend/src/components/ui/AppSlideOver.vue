<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import BaseOverlay from './BaseOverlay.vue'
import { randomId } from '@/utils/id'

export default defineComponent({
  name: 'AppSlideOver',

  components: {
    BaseOverlay,
    XMarkIcon,
  },

  props: {
    title: { type: String, required: true },
    description: { type: String, default: undefined },
  },

  emits: ['close'],

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      titleId: `slideover-title-${randomId()}`,
    }
  },
})
</script>

<template>
  <BaseOverlay :title-id="titleId" variant="side" @close="$emit('close')">
    <header class="flex items-start gap-4 border-b border-divider px-5 py-4">
      <div class="min-w-0 flex-1">
        <h2 :id="titleId" class="text-base font-semibold text-text">{{ title }}</h2>
        <p v-if="description" class="mt-0.5 text-[13px] text-neutral-600">{{ description }}</p>
      </div>
      <button
        type="button"
        class="shrink-0 cursor-pointer rounded-md p-1 text-neutral-600 hover:bg-surface hover:text-text"
        :aria-label="t('overlay.close')"
        @click="$emit('close')"
      >
        <XMarkIcon class="h-5 w-5" aria-hidden="true" />
      </button>
    </header>

    <div class="flex-1 overflow-y-auto px-5 py-4">
      <slot />
    </div>

    <slot name="footer" />
  </BaseOverlay>
</template>
