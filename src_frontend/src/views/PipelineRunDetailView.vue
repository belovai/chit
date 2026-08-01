<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSlideOver from '@/components/ui/AppSlideOver.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import RunStatusPill from '@/components/pipeline/RunStatusPill.vue'
import StepCard from '@/components/pipeline/StepCard.vue'
import StepDetailPanel from '@/components/pipeline/StepDetailPanel.vue'
import { useAuthStore } from '@/stores/auth'
import { pipelineService } from '@/services/pipeline'
import { useRunPolling } from '@/composables/useRunPolling'
import { formatDurationMs, formatRelativeFromIso } from '@/utils/datetime'
import { isRunSettled, type PipelineRunDetail, type PipelineStepDetail } from '@/types/pipeline'

export default defineComponent({
  name: 'PipelineRunDetailView',

  components: {
    AppSection,
    AppCard,
    AppButton,
    AppSlideOver,
    ConfirmDialog,
    RunStatusPill,
    StepCard,
    StepDetailPanel,
  },

  setup() {
    const { t } = useI18n()
    // isRunSettled is used in the template, so it must come through setup —
    // a bare module import is not visible to the Options API template scope.
    return { t, formatDurationMs, formatRelativeFromIso, isRunSettled }
  },

  data() {
    return {
      run: null as PipelineRunDetail | null,
      selectedStep: null as PipelineStepDetail | null,
      attempts: [] as PipelineStepDetail[],
      isCancelOpen: false,
      polling: null as ReturnType<typeof useRunPolling> | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hashId(): string {
      return String(this.$route.params.hashId)
    },

    // One column per declared stage, in declared order, empty ones included.
    columns(): { stage: string; steps: PipelineStepDetail[] }[] {
      if (this.run === null) return []
      const run = this.run
      return run.stages.map((stage) => ({
        stage,
        steps: run.steps
          .filter((step) => step.stage === stage)
          .sort((a, b) => a.position - b.position),
      }))
    },
  },

  async mounted() {
    this.polling = useRunPolling(
      () => this.load(),
      () => this.run !== null && !isRunSettled(this.run.status),
    )
    await this.load()
    this.polling.start()
  },

  methods: {
    async load() {
      this.run = await pipelineService.get(this.token as string, this.hashId)

      if (this.selectedStep !== null) {
        const key = this.selectedStep.step_key
        this.selectedStep = this.run.steps.find((step) => step.step_key === key) ?? null
      }
    },

    async selectStep(step: PipelineStepDetail) {
      this.selectedStep = step
      this.attempts = await pipelineService.attempts(
        this.token as string,
        this.hashId,
        step.step_key,
      )
    },

    closePanel() {
      this.selectedStep = null
      this.attempts = []
    },

    async retry(mode: 'single' | 'from' | 'all', step?: PipelineStepDetail) {
      const result = await pipelineService.retry(this.token as string, this.hashId, {
        mode,
        step_key: step?.step_key,
      })

      if (result.hash_id !== this.hashId) {
        // `all` starts a fresh run — follow it.
        await this.$router.push({
          name: 'pipeline-detail',
          params: { hashId: result.hash_id },
        })
        return
      }

      this.run = result
      this.closePanel()
      this.polling?.start()
    },

    async cancel() {
      this.run = await pipelineService.cancel(this.token as string, this.hashId)
      this.isCancelOpen = false
    },

    stageLabel(stage: string): string {
      return this.t(`pipeline.stage.${stage}`)
    },
  },
})
</script>

<template>
  <div v-if="run !== null" class="flex flex-col gap-4">
    <AppSection :title="`#${run.hash_id}`">
      <template #actions>
        <div class="flex items-center gap-2">
          <RunStatusPill :status="run.status" />
          <AppButton @click="retry('all')">{{ t('pipeline.actions.retryAll') }}</AppButton>
          <AppButton
            v-if="!isRunSettled(run.status) || run.status === 'awaiting_manual'"
            @click="isCancelOpen = true"
          >
            {{ t('pipeline.actions.cancel') }}
          </AppButton>
        </div>
      </template>
    </AppSection>

    <AppCard>
      <div class="flex flex-wrap gap-4 text-xs text-neutral-500">
        <span>{{ run.definition_key }} v{{ run.definition_version }}</span>
        <span>{{ t(`pipeline.trigger.${run.trigger_source}`) }}</span>
        <span>{{ formatDurationMs(run.duration_ms) }}</span>
        <span>{{ formatRelativeFromIso(run.created_at) }}</span>
        <span>${{ (run.cost_usd_micros / 1_000_000).toFixed(4) }}</span>
        <RouterLink
          v-if="run.retried_from_hash_id !== null"
          class="underline"
          :to="{ name: 'pipeline-detail', params: { hashId: run.retried_from_hash_id } }"
        >
          #{{ run.retried_from_hash_id }}
        </RouterLink>
      </div>

      <p
        v-if="run.error_summary !== null"
        class="mt-2 rounded-sm bg-danger-100 px-2 py-1 text-xs text-danger-700"
      >
        {{ run.error_summary.step_key }}: {{ run.error_summary.message }}
      </p>
    </AppCard>

    <div class="flex gap-3 overflow-x-auto pb-2">
      <div v-for="column in columns" :key="column.stage" class="flex w-48 shrink-0 flex-col gap-2">
        <h3 class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">
          {{ stageLabel(column.stage) }}
        </h3>
        <p v-if="column.steps.length === 0" class="text-[11px] text-neutral-400">
          {{ t('pipeline.detail.emptyStage') }}
        </p>
        <StepCard
          v-for="step in column.steps"
          :key="step.step_key"
          :step="step"
          :selected="selectedStep?.step_key === step.step_key"
          @select="selectStep"
        />
      </div>
    </div>

    <AppSlideOver v-if="selectedStep !== null" :title="selectedStep.step_key" @close="closePanel">
      <StepDetailPanel
        :step="selectedStep"
        :run-hash-id="run.hash_id"
        :attempts="attempts"
        @retry-single="retry('single', $event)"
        @retry-from="retry('from', $event)"
        @close="closePanel"
      />
    </AppSlideOver>

    <ConfirmDialog
      :open="isCancelOpen"
      :title="t('pipeline.actions.confirmCancelTitle')"
      :message="t('pipeline.actions.confirmCancelBody')"
      variant="danger"
      @confirm="cancel"
      @cancel="isCancelOpen = false"
    />
  </div>
</template>
