<script lang="ts">
import { defineComponent, ref } from 'vue'
import { useScrollLock } from '@/composables/useScrollLock'
import { useFocusTrap } from '@/composables/useFocusTrap'

export default defineComponent({
  name: 'BaseOverlay',

  props: {
    titleId: { type: String, required: true },
    variant: { type: String as () => 'center' | 'side', required: true },
  },

  emits: ['close'],

  setup() {
    const panelRef = ref<HTMLElement | null>(null)
    const { lock, unlock } = useScrollLock()
    const { activate, deactivate } = useFocusTrap(panelRef)
    return { panelRef, lock, unlock, activate, deactivate }
  },

  mounted() {
    this.lock()
    this.activate()
    document.addEventListener('keydown', this.onKeydown)
  },

  beforeUnmount() {
    document.removeEventListener('keydown', this.onKeydown)
    this.deactivate()
    this.unlock()
  },

  methods: {
    onKeydown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        this.$emit('close')
      }
    },
  },
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-40 bg-text/45" @click="$emit('close')"></div>

    <div
      ref="panelRef"
      class="fixed inset-0 z-50 flex flex-col bg-panel md:inset-auto"
      :class="
        variant === 'side'
          ? 'md:top-0 md:right-0 md:h-full md:w-[28rem] md:shadow-modal'
          : 'md:top-1/2 md:left-1/2 md:max-h-[85vh] md:w-full md:max-w-lg md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-xl md:shadow-modal'
      "
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      tabindex="-1"
    >
      <slot />
    </div>
  </Teleport>
</template>
