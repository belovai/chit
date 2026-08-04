import { apiRequest, apiRequestBlob, apiRequestPaginated } from '@/services/http'
import type {
  PipelineArtifactPayload,
  PipelineRun,
  PipelineRunDetail,
  PipelineStepDetail,
  RetryPayload,
} from '@/types/pipeline'

export interface RunListParams {
  page?: number
  status?: string
  trigger_source?: string
  definition_key?: string
}

export const pipelineService = {
  list(token: string, params: RunListParams = {}) {
    const query: Record<string, string | number> = {}
    if (params.page !== undefined) query.page = params.page
    if (params.status) query.status = params.status
    if (params.trigger_source) query.trigger_source = params.trigger_source
    if (params.definition_key) query.definition_key = params.definition_key

    return apiRequestPaginated<PipelineRun>('/api/pipeline-runs', { token, query })
  },

  get(token: string, hashId: string) {
    return apiRequest<PipelineRunDetail>(`/api/pipeline-runs/${hashId}`, { token })
  },

  attempts(token: string, hashId: string, stepKey: string) {
    return apiRequest<PipelineStepDetail[]>(
      `/api/pipeline-runs/${hashId}/steps/${stepKey}/attempts`,
      { token },
    )
  },

  artifact(token: string, hashId: string, key: string) {
    return apiRequest<PipelineArtifactPayload>(`/api/pipeline-runs/${hashId}/artifacts/${key}`, {
      token,
    })
  },

  artifactBlob(token: string, hashId: string, key: string) {
    return apiRequestBlob(`/api/pipeline-runs/${hashId}/artifacts/${key}`, { token })
  },

  retry(token: string, hashId: string, payload: RetryPayload) {
    return apiRequest<PipelineRunDetail>(`/api/pipeline-runs/${hashId}/retry`, {
      method: 'POST',
      body: payload,
      token,
    })
  },

  cancel(token: string, hashId: string) {
    return apiRequest<PipelineRunDetail>(`/api/pipeline-runs/${hashId}/cancel`, {
      method: 'POST',
      token,
    })
  },
}
