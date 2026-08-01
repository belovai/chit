<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from './AppButton.vue'
import { randomId } from '@/utils/id'

export default defineComponent({
  name: 'ConfirmDialog',

  components: {
    AppButton,
  },

  props: {
    open: { type: Boolean, required: true },
    title: { type: String, default: undefined },
    message: { type: String, required: true },
    confirmLabel: { type: String, default: undefined },
    cancelLabel: { type: String, default: undefined },
    variant: {
      type: String as () => 'primary' | 'danger',
      default: 'primary',
    },
  },

  emits: ['confirm', 'cancel'],

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      titleId: `confirm-title-${randomId()}`,
    }
  },
})
</script>

<template>
  <Teleport to="body">
    <template v-if="open">
      <div class="fixed inset-0 z-60 bg-text/45" @click="$emit('cancel')"></div>
      <div
        class="fixed inset-x-4 top-1/2 z-70 -translate-y-1/2 rounded-xl border border-divider bg-panel p-5 shadow-modal sm:inset-x-auto sm:left-1/2 sm:w-96 sm:-translate-x-1/2"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="titleId"
      >
        <h2 :id="titleId" class="mb-2 text-base font-semibold text-text">
          {{ title ?? t('common.confirm') }}
        </h2>
        <p class="mb-5 text-sm text-neutral-700">{{ message }}</p>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <AppButton type="button" variant="ghost" @click="$emit('cancel')">
            {{ cancelLabel ?? t('common.cancel') }}
          </AppButton>
          <AppButton type="button" :variant="variant" @click="$emit('confirm')">
            {{ confirmLabel ?? t('common.confirm') }}
          </AppButton>
        </div>
      </div>
    </template>
  </Teleport>
</template>
