// Root ESLint config — delegates to the frontend flat config so
// repo-root lint runs (e.g. platform gates) work without duplicating rules.
// Flat-config relative patterns resolve against the importing file, so
// path-prefixed ignores/overrides are re-declared here for repo-root runs.
import frontendConfig from './frontend/eslint.config.js'

const RELAX_SET_STATE = [
  'frontend/src/components/attention/AttentionBanner.tsx',
  'frontend/src/pages/admin/AdminNeedsAttention.tsx',
]
const RELAX_ANY = [
  'frontend/src/components/device/TemplateDefinitionsPanel.tsx',
  'frontend/src/components/device/tabs/DeviceEventsPanel.tsx',
  'frontend/src/components/device/tabs/DeviceMetadataPanel.tsx',
  'frontend/src/services/operational-event.service.ts',
]

export default [
  {
    ignores: [
      '**/node_modules/**',
      '**/dist/**',
      '**/vendor/**',
      '**/e2e/**',
      'frontend/_legacy/**',
      'backend/**',
      '_template_backup/**',
      'photo_reference/**',
      'docs/**',
      'infrastructure/**',
      'tests/**',
      'scripts/**',
      'memory/**',
    ],
  },
  ...frontendConfig,
  { files: RELAX_SET_STATE, rules: { 'react-hooks/set-state-in-effect': 'off' } },
  { files: RELAX_ANY, rules: { '@typescript-eslint/no-explicit-any': 'off' } },
]
