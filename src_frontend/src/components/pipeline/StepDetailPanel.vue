<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import FindingList from '@/components/pipeline/FindingList.vue'
import { useAuthStore } from '@/stores/auth'
import { pipelineService } from '@/services/pipeline'
import { formatDurationMs } from '@/utils/datetime'
import type { PipelineArtifactSummary, PipelineStepDetail } from '@/types/pipeline'

export default defineComponent({
  name: 'StepDetailPanel',

  components: { AppButton, AppBadge, FindingList },

  props: {
    step: {
      type: Object as PropType<PipelineStepDetail>,
      required: true,
    },
    runHashId: {
      type: String,
      required: true,
    },
    attempts: {
      type: Array as PropType<PipelineStepDetail[]>,
      required: true,
    },
  },

  emits: ['retry-single', 'retry-from', 'close'],

  setup() {
    const { t } = useI18n()
    return { t, formatDurationMs }
  },

  data() {
    return {
      openArtifactKey: null as string | null,
      openArtifactBody: '' as string,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    costLabel(): string {
      if (this.step.cost_usd_micros === null) return '—'
      return `$${(this.step.cost_usd_micros / 1_000_000).toFixed(4)}`
    },
  },

  methods: {
    async openArtifact(artifact: PipelineArtifactSummary) {
      if (artifact.kind === 'binary') {
        window.open(
          `/api/pipeline-runs/${this.runHashId}/artifacts/${artifact.key}`,
          '_blank',
          'noopener',
        )
        return
      }

      const payload = await pipelineService.artifact(
        this.token as string,
        this.runHashId,
        artifact.key,
      )
      this.openArtifactKey = artifact.key
      this.openArtifactBody =
        artifact.kind === 'text'
          ? String((payload.payload as { text?: string } | null)?.text ?? '')
          : JSON.stringify(payload.payload, null, 2)
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-medium">{{ step.step_key }}</h3>
      <div class="flex gap-1">
        <AppBadge v-if="step.is_dynamic">{{ t('pipeline.detail.dynamic') }}</AppBadge>
        <AppBadge v-if="step.allow_failure" variant="warning">
          {{ t('pipeline.detail.allowFailure') }}
        </AppBadge>
      </div>
    </div>

    <dl class="grid grid-cols-2 gap-2 text-xs">
      <dt class="text-neutral-500">{{ t('pipeline.detail.duration') }}</dt>
      <dd>{{ formatDurationMs(step.duration_ms) }}</dd>

      <dt class="text-neutral-500">{{ t('pipeline.detail.confidence') }}</dt>
      <dd>{{ step.confidence === null ? '—' : step.confidence.toFixed(3) }}</dd>

      <dt class="text-neutral-500">{{ t('pipeline.detail.cost') }}</dt>
      <dd>
        {{ costLabel }}
        <span v-if="step.input_tokens !== null" class="text-neutral-500">
          ({{
            t('pipeline.detail.tokens', { input: step.input_tokens, output: step.output_tokens })
          }})
        </span>
      </dd>

      <dt class="text-neutral-500">{{ t('pipeline.detail.dependsOn') }}</dt>
      <dd>{{ step.depends_on.length === 0 ? '—' : step.depends_on.join(', ') }}</dd>
    </dl>

    <section>
      <h4 class="mb-1 text-xs font-medium">{{ t('pipeline.detail.findings') }}</h4>
      <FindingList :findings="step.findings" />
    </section>

    <section v-if="step.error !== null">
      <h4 class="mb-1 text-xs font-medium">{{ t('pipeline.detail.error') }}</h4>
      <p class="rounded-sm bg-danger-100 px-2 py-1 text-xs text-danger-700">
        {{ step.error.message }}
        <AppBadge v-if="step.error.retryable" variant="warning">
          {{ t('pipeline.detail.retryable') }}
        </AppBadge>
      </p>
    </section>

    <section v-if="step.artifacts.length > 0">
      <h4 class="mb-1 text-xs font-medium">{{ t('pipeline.detail.artifacts') }}</h4>
      <ul class="flex flex-col gap-1">
        <li v-for="artifact in step.artifacts" :key="artifact.key">
          <button
            type="button"
            class="text-xs underline"
            :disabled="artifact.is_pruned"
            @click="openArtifact(artifact)"
          >
            {{ artifact.key }}
          </button>
          <span v-if="artifact.is_pruned" class="text-[10px] text-neutral-500">
            — {{ t('pipeline.detail.artifactPruned') }}
          </span>
        </li>
      </ul>
      <pre
        v-if="openArtifactKey !== null"
        class="mt-2 max-h-64 overflow-auto rounded-sm bg-surface p-2 text-[11px]"
        >{{ openArtifactBody }}</pre
      >
    </section>

    <section v-if="attempts.length > 1">
      <h4 class="mb-1 text-xs font-medium">{{ t('pipeline.detail.attempts') }}</h4>
      <ul class="flex flex-col gap-1 text-xs">
        <li v-for="attempt in attempts" :key="attempt.attempt">
          #{{ attempt.attempt }} — {{ t(`pipeline.stepStatus.${attempt.status}`) }} ·
          {{ formatDurationMs(attempt.duration_ms) }}
        </li>
      </ul>
    </section>

    <div class="flex gap-2">
      <AppButton @click="$emit('retry-single', step)">
        {{ t('pipeline.actions.retrySingle') }}
      </AppButton>
      <AppButton @click="$emit('retry-from', step)">
        {{ t('pipeline.actions.retryFrom') }}
      </AppButton>
    </div>
  </div>
</template>
