// Repo-root ESLint config: self-contained (no imports) so any ESLint version
// (v8 flat / v9 / v10) can load it without module resolution. The frontend has
// its own full config at frontend/eslint.config.js; the backend is PHP.
export default [
  {
    ignores: ['**/*'],
  },
]
