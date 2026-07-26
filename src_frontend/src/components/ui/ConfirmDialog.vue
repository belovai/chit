<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from './AppButton.vue'

export default defineComponent({
  name: 'ConfirmDialog',

  components: {
    AppButton,
  },

  props: {
    open: {
      type: Boolean,
      required: true,
    },
    title: {
      type: String,
      default: undefined,
    },
    message: {
      type: String,
      required: true,
    },
    confirmLabel: {
      type: String,
      default: undefined,
    },
    cancelLabel: {
      type: String,
      default: undefined,
    },
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
})
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-20 bg-text/45" @click="$emit('cancel')"></div>
  <div
    v-if="open"
    class="fixed inset-x-4 top-1/2 z-30 -translate-y-1/2 border border-divider bg-bg p-5 shadow-lg sm:inset-x-auto sm:left-1/2 sm:w-96 sm:-translate-x-1/2"
    role="alertdialog"
    aria-modal="true"
  >
    <h2 v-if="title" class="mb-2 text-lg">{{ title }}</h2>
    <p class="mb-5 text-sm text-text">{{ message }}</p>
    <div class="flex justify-end gap-3">
      <AppButton type="button" variant="ghost" @click="$emit('cancel')">
        {{ cancelLabel ?? t('common.cancel') }}
      </AppButton>
      <AppButton type="button" :variant="variant" @click="$emit('confirm')">
        {{ confirmLabel ?? t('common.confirm') }}
      </AppButton>
    </div>
  </div>
</template>
