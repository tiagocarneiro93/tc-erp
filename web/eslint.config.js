import js from '@eslint/js'
import prettier from 'eslint-config-prettier'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import globals from 'globals'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  {
    ignores: [
      'dist',
      'coverage',
      'playwright-report',
      'test-results',
      'src/routeTree.gen.ts',
      'src/api/generated.ts',
      'orval.config.ts',
    ],
  },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommendedTypeChecked],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      // TanStack Router route files handle their own promises (React Query
      // mutations, `beforeLoad`) inside JSX attributes and object properties
      // by design; the attribute/property checks here don't fit that idiom.
      '@typescript-eslint/no-misused-promises': [
        'error',
        { checksVoidReturn: { attributes: false, properties: false } },
      ],
      // TanStack Router's documented redirect pattern is `throw redirect(...)`,
      // where redirect() returns a Response, not an Error.
      '@typescript-eslint/only-throw-error': 'off',
    },
  },
  {
    files: ['src/routes/**/*.tsx'],
    rules: {
      // Every route file exports a `Route` object alongside its component.
      'react-refresh/only-export-components': 'off',
    },
  },
  prettier,
)
