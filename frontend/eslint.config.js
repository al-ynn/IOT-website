import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist', '_legacy/**']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
    },
    rules: {
      '@typescript-eslint/no-unused-expressions': ['error', { allowTernary: true }],
    },
  },
  {
    files: ['src/components/attention/AttentionBanner.tsx', 'src/pages/admin/AdminNeedsAttention.tsx'],
    rules: {
      'react-hooks/set-state-in-effect': 'off',
    },
  },
  {
    files: ['src/components/device/TemplateDefinitionsPanel.tsx', 'src/components/device/tabs/DeviceEventsPanel.tsx', 'src/components/device/tabs/DeviceMetadataPanel.tsx', 'src/services/operational-event.service.ts'],
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
])
