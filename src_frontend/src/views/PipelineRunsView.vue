<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppListItem from '@/components/ui/AppListItem.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import RunStatusPill from '@/components/pipeline/RunStatusPill.vue'
import StageBubbles from '@/components/pipeline/StageBubbles.vue'
import { useAuthStore } from '@/stores/auth'
import { pipelineService } from '@/services/pipeline'
import { useRunPolling } from '@/composables/useRunPolling'
import { formatDurationMs, formatRelativeFromIso } from '@/utils/datetime'
import { isRunSettled, type PipelineRun, type RunStatus } from '@/types/pipeline'

const STATUS_OPTIONS: RunStatus[] = [
  'queued',
  'running',
  'awaiting_manual',
  'succeeded',
  'warning',
  'failed',
  'canceled',
  'expired',
]

export default defineComponent({
  name: 'PipelineRunsView',

  components: {
    AppSection,
    AppCard,
    AppListItem,
    AppEmptyState,
    AppBadge,
    AppButton,
    AppSelect,
    RunStatusPill,
    StageBubbles,
  },

  setup() {
    const { t } = useI18n()
    return { t, formatDurationMs, formatRelativeFromIso, statusOptions: STATUS_OPTIONS }
  },

  data() {
    return {
      runs: [] as PipelineRun[],
      currentPage: 1,
      lastPage: 1,
      isLoading: false,
      polling: null as ReturnType<typeof useRunPolling> | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    statusFilter: {
      get(): RunStatus | '' {
        const value = this.$route.query.status
        return ((Array.isArray(value) ? value[0] : value) || '') as RunStatus | ''
      },
      set(value: RunStatus | '') {
        const query = { ...this.$route.query }
        if (value) {
          query.status = value
        } else {
          delete query.status
        }
        this.$router.replace({ query })
      },
    },

    hasMore(): boolean {
      return this.currentPage < this.lastPage
    },

    hasMovingRun(): boolean {
      return this.runs.some((run) => !isRunSettled(run.status))
    },
  },

  watch: {
    async statusFilter() {
      await this.loadPage(1)
      this.polling?.start()
    },
  },

  async mounted() {
    this.polling = useRunPolling(
      () => this.refreshFirstPage(),
      () => this.hasMovingRun,
    )
    await this.loadPage(1)
    this.polling.start()
  },

  methods: {
    async loadPage(page: number) {
      this.isLoading = true
      try {
        const result = await pipelineService.list(this.token as string, {
          page,
          status: this.statusFilter || undefined,
        })
        this.runs = page === 1 ? result.data : [...this.runs, ...result.data]
        this.currentPage = result.currentPage
        this.lastPage = result.lastPage
      } finally {
        this.isLoading = false
      }
    },

    // Poll refresh only replaces the first page, so "load more" results survive.
    async refreshFirstPage() {
      const result = await pipelineService.list(this.token as string, {
        page: 1,
        status: this.statusFilter || undefined,
      })
      const fresh = new Map(result.data.map((run) => [run.hash_id, run]))
      this.runs = this.runs.map((run) => fresh.get(run.hash_id) ?? run)
      for (const run of result.data) {
        if (!this.runs.some((existing) => existing.hash_id === run.hash_id)) {
          this.runs = [run, ...this.runs]
        }
      }
    },

    goToDetail(run: PipelineRun) {
      this.$router.push({ name: 'pipeline-detail', params: { hashId: run.hash_id } })
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppSection :title="t('pipeline.title')">
      <template #actions>
        <AppSelect id="pipeline-status-filter" v-model="statusFilter" class="w-44">
          <option value="">{{ t('common.all') }}</option>
          <option v-for="status in statusOptions" :key="status" :value="status">
            {{ t(`pipeline.status.${status}`) }}
          </option>
        </AppSelect>
      </template>
    </AppSection>

    <AppCard :padded="false">
      <AppEmptyState v-if="runs.length === 0 && !isLoading" :title="t('pipeline.empty')" />

      <ul v-else class="divide-y divide-divider">
        <li v-for="run in runs" :key="run.hash_id">
          <AppListItem interactive @click="goToDetail(run)">
            <div class="flex w-full flex-col gap-2 md:flex-row md:items-center md:gap-4">
              <div class="flex w-40 shrink-0 flex-col gap-1">
                <RunStatusPill :status="run.status" />
                <span class="text-[11px] text-neutral-500">
                  {{ formatDurationMs(run.duration_ms) }} ·
                  {{ formatRelativeFromIso(run.created_at) }}
                </span>
              </div>

              <div class="flex min-w-0 flex-1 flex-col gap-1">
                <span class="truncate text-sm font-medium">#{{ run.hash_id }}</span>
                <div class="flex items-center gap-1">
                  <AppBadge>{{ run.definition_key }}</AppBadge>
                  <AppBadge variant="neutral">
                    {{ t(`pipeline.trigger.${run.trigger_source}`) }}
                  </AppBadge>
                </div>
              </div>

              <div class="min-w-0 flex-1">
                <StageBubbles :stages="run.stages" :steps="run.steps" />
              </div>

              <div class="w-20 shrink-0 text-right text-[11px] text-neutral-500">
                ${{ (run.cost_usd_micros / 1_000_000).toFixed(4) }}
              </div>
            </div>
          </AppListItem>
        </li>
      </ul>

      <template v-if="hasMore" #footer>
        <AppButton
          variant="ghost"
          size="sm"
          :disabled="isLoading"
          @click="loadPage(currentPage + 1)"
        >
          {{ t('pipeline.loadMore') }}
        </AppButton>
      </template>
    </AppCard>
  </div>
</template>
