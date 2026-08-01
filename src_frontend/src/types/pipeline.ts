export type RunStatus =
  | 'queued'
  | 'running'
  | 'awaiting_manual'
  | 'succeeded'
  | 'warning'
  | 'failed'
  | 'canceled'
  | 'expired'

export type StepStatus =
  | 'pending'
  | 'queued'
  | 'running'
  | 'succeeded'
  | 'failed'
  | 'skipped'
  | 'canceled'
  | 'awaiting_manual'
  | 'expired'

export type TriggerSource = 'manual_upload' | 'email' | 'watch_folder' | 'api' | 'retry'

export type FindingSeverity = 'info' | 'warning' | 'blocker'

export type RetryMode = 'single' | 'from' | 'all'

export interface PipelineFinding {
  code: string
  severity: FindingSeverity
  message: string | null
  context: Record<string, unknown>
}

export interface PipelineArtifactSummary {
  key: string
  kind: 'json' | 'text' | 'binary'
  mime: string | null
  size_bytes: number | null
  is_pruned: boolean
}

export interface PipelineStep {
  step_key: string
  stage: string
  stage_position: number
  position: number
  status: StepStatus
  is_gate: boolean
  is_dynamic: boolean
}

export interface PipelineStepDetail extends PipelineStep {
  attempt: number
  max_attempts: number
  allow_failure: boolean
  depends_on: string[]
  started_at: string | null
  finished_at: string | null
  duration_ms: number | null
  confidence: number | null
  findings: PipelineFinding[]
  input_tokens: number | null
  output_tokens: number | null
  cost_usd_micros: number | null
  error: { class: string; message: string; retryable: boolean } | null
  artifacts: PipelineArtifactSummary[]
}

export interface PipelineRun {
  hash_id: string
  definition_key: string
  definition_version: number
  stages: string[]
  status: RunStatus
  trigger_source: TriggerSource
  subject_type: string | null
  subject_id: number | null
  duration_ms: number | null
  cost_usd_micros: number
  created_at: string | null
  started_at: string | null
  finished_at: string | null
  expires_at: string | null
  steps: PipelineStep[]
}

export interface PipelineRunDetail extends Omit<PipelineRun, 'steps'> {
  error_summary: { step_key: string; message: string } | null
  retried_from_hash_id: string | null
  steps: PipelineStepDetail[]
}

export interface PipelineArtifactPayload {
  key: string
  kind: 'json' | 'text'
  payload: Record<string, unknown> | null
}

export interface RetryPayload {
  mode: RetryMode
  step_key?: string
}

const TERMINAL_RUN_STATUSES: RunStatus[] = ['succeeded', 'warning', 'failed', 'canceled', 'expired']

/** `awaiting_manual` counts as settled for polling: nothing moves without a human. */
export function isRunSettled(status: RunStatus): boolean {
  return TERMINAL_RUN_STATUSES.includes(status) || status === 'awaiting_manual'
}
