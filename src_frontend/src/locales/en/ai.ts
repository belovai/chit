export default {
  title: 'AI keys',
  description:
    'Chit processes your documents with your own provider API key. Nothing is uploaded or processed until an active, verified key is stored here.',
  addKey: 'Add API key',
  emptyState: 'No API key stored yet',
  emptyStateDescription:
    'Add a provider API key to start uploading receipts. Chit never processes documents without one.',

  createTitle: 'Add API key',
  editTitle: 'Edit API key',

  providerLabel: 'Provider',
  providerHint: 'The provider cannot be changed later — a different vendor is a different key.',
  labelLabel: 'Name',
  labelHint: 'Only for you, so you can tell your keys apart.',
  apiKeyLabel: 'API key',
  apiKeyCreateHint: 'Stored encrypted. It is never shown again after saving.',
  apiKeyEditHint: 'Leave empty to keep the current key. Fill it in only to replace the key.',
  modelLabel: 'Model',
  settingsTitle: 'Model settings',

  pricing: '${input} in / ${output} out per M tokens',

  create: 'Add key',
  save: 'Save',
  verifying: 'Verifying key…',

  activate: 'Activate',
  activating: 'Activating…',
  verify: 'Verify',
  verifyingShort: 'Verifying…',
  edit: 'Edit',
  delete: 'Delete',
  deleteConfirm: 'Delete this API key? Runs already finished keep their usage history.',

  active: 'Active',
  statusPending: 'Not verified',
  statusVerified: 'Verified',
  statusFailing: 'Failing',
  statusDisabled: 'Disabled',

  lastVerifiedAt: 'Verified {date}',
  lastUsedAt: 'Last used {date}',
  neverUsed: 'Never used',

  capabilities: {
    vision: 'vision',
    json_schema: 'json schema',
    prompt_cache: 'prompt cache',
  },

  settings: {
    max_tokens: {
      label: 'Max tokens',
      hint: 'Upper bound on the response length of a single call.',
    },
    effort: {
      label: 'Reasoning effort',
      hint: 'Higher effort costs more and is slower, but reads difficult documents better.',
    },
  },
}
