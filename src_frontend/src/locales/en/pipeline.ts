export default {
  title: 'Pipelines',
  empty: 'No pipeline runs yet.',
  loadMore: 'Load more',

  status: {
    queued: 'Queued',
    running: 'Running',
    awaiting_manual: 'Needs review',
    succeeded: 'Passed',
    warning: 'Warning',
    failed: 'Failed',
    canceled: 'Canceled',
    expired: 'Expired',
  },

  stepStatus: {
    pending: 'Pending',
    queued: 'Queued',
    running: 'Running',
    succeeded: 'Passed',
    failed: 'Failed',
    skipped: 'Skipped',
    canceled: 'Canceled',
    awaiting_manual: 'Needs review',
    expired: 'Expired',
  },

  trigger: {
    manual_upload: 'Upload',
    email: 'Email',
    watch_folder: 'Folder',
    api: 'API',
    retry: 'Retry',
  },

  stage: {
    ingest: 'Ingest',
    prepare: 'Prepare',
    read: 'Read',
    classify: 'Classify',
    extract: 'Extract',
    resolve: 'Resolve',
    validate: 'Validate',
    review: 'Review',
    commit: 'Commit',
  },

  actions: {
    retry: 'Retry',
    retrySingle: 'Retry this step',
    retryFrom: 'Rerun from here',
    retryAll: 'Rerun everything',
    cancel: 'Cancel run',
    review: 'Review',
    confirmCancelTitle: 'Cancel this run?',
    confirmCancelBody: 'Steps still open will be canceled. No transaction is created.',
  },

  detail: {
    attempts: 'Attempts',
    attempt: 'Attempt {n} of {max}',
    dependsOn: 'Depends on',
    dynamic: 'Added during the run',
    allowFailure: 'Failure allowed',
    confidence: 'Confidence',
    cost: 'Cost',
    tokens: '{input} in / {output} out',
    duration: 'Duration',
    artifacts: 'Artifacts',
    artifactPruned: 'File pruned',
    error: 'Error',
    retryable: 'Retryable',
    noFindings: 'No findings.',
    findings: 'Findings',
    emptyStage: 'No steps',
  },

  severity: {
    info: 'Info',
    warning: 'Warning',
    blocker: 'Blocker',
  },

  // Machine codes from the backend. Unknown codes fall back to the raw code.
  finding: {
    exact_duplicate: 'This exact file has been uploaded before',
    possible_duplicate: 'Looks like a transaction you already have',
    line_items_sum_mismatch: 'Line items do not add up to the total',
    total_missing: 'No total found',
    date_in_future: 'The date is in the future',
    classification_uncertain: 'Could not tell what kind of document this is',
    classification_conflict: 'Detected type differs from the one you picked',
    meter_reading_decreased: 'Meter reading is lower than the previous bill',
    merchant_ambiguous: 'Several merchants match',
    new_merchant: 'Merchant not seen before',
    low_ocr_confidence: 'Text recognition was uncertain',
    consumption_anomaly: 'Consumption differs sharply from previous bills',
    period_gap: 'Gap since the previous billing period',
    fake_blocker: 'Demo blocker',
  },

  error: {
    run_not_retryable: 'This run is still going — wait for it to finish.',
    run_not_awaiting_manual: 'This run is not waiting for a decision.',
    step_not_in_run: 'That step is not part of this run.',
  },
}
