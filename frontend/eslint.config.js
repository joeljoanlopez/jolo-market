import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import tseslint from 'typescript-eslint';

// Hexagonal boundaries inside each feature (docs/architecture.md#frontend-structure).
// Paths are matched on the import specifier, so both "@/features/x/infrastructure/..."
// and "../infrastructure/..." are caught.
const react = {
  regex: '^react($|-dom($|/)|/)',
  message: 'domain/ must stay framework-free: no React.',
};
const layer = (name, from) => ({
  regex: `(^|/)${name}(/|$)`,
  message: `${from}/ cannot import from ${name}/ (dependencies point inwards).`,
});

export default tseslint.config(
  { ignores: ['dist', 'coverage', 'playwright-report', 'test-results', 'blob-report'] },
  {
    files: ['**/*.{ts,tsx}'],
    extends: [js.configs.recommended, ...tseslint.configs.strictTypeChecked],
    languageOptions: {
      globals: globals.browser,
      parserOptions: {
        projectService: { allowDefaultProject: ['eslint.config.js'] },
        tsconfigRootDir: import.meta.dirname,
      },
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['error', { allowConstantExport: true }],
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/ban-ts-comment': [
        'error',
        { 'ts-ignore': true, 'ts-expect-error': 'allow-with-description' },
      ],
    },
  },
  {
    files: ['src/features/*/domain/**/*.{ts,tsx}'],
    rules: {
      'no-restricted-imports': [
        'error',
        {
          patterns: [
            react,
            layer('application', 'domain'),
            layer('infrastructure', 'domain'),
            layer('ui', 'domain'),
          ],
        },
      ],
    },
  },
  {
    files: ['src/features/*/application/**/*.{ts,tsx}'],
    rules: {
      'no-restricted-imports': [
        'error',
        { patterns: [layer('infrastructure', 'application'), layer('ui', 'application')] },
      ],
    },
  },
  {
    files: ['**/*.test.{ts,tsx}', 'src/test/**'],
    rules: {
      // Test doubles and fixtures are allowed to sit next to UI files.
      'react-refresh/only-export-components': 'off',
    },
  },
  {
    files: ['eslint.config.js'],
    extends: [tseslint.configs.disableTypeChecked],
    languageOptions: { globals: globals.node },
  },
  prettier,
);
