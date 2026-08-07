<script lang="ts">
import { defineComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'

export default defineComponent({
  name: 'AppPager',

  components: { AppButton },

  props: {
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    disabled: { type: Boolean, default: false },
  },

  emits: {
    change: (page: number) => typeof page === 'number',
  },

  setup(_props, { emit }) {
    const { t } = useI18n()

    return { t, onChange: (page: number) => emit('change', page) }
  },
})
</script>

<template>
  <div v-if="lastPage > 1" class="flex items-center justify-between gap-3">
    <AppButton
      variant="ghost"
      size="sm"
      :disabled="disabled || currentPage <= 1"
      @click="onChange(currentPage - 1)"
    >
      {{ t('common.previous') }}
    </AppButton>

    <span class="text-[13px] text-neutral-600">
      {{ t('common.pageOf', { current: currentPage, total: lastPage }) }}
    </span>

    <AppButton
      variant="ghost"
      size="sm"
      :disabled="disabled || currentPage >= lastPage"
      @click="onChange(currentPage + 1)"
    >
      {{ t('common.next') }}
    </AppButton>
  </div>
</template>
