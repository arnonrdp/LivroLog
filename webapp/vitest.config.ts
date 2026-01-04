import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import { configDefaults, defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue() as Parameters<typeof defineConfig>[0] extends { plugins?: infer P } ? (P extends Array<infer E> ? E : never) : never],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@vue/devtools-kit': fileURLToPath(new URL('./src/tests/mocks/devtools-kit.ts', import.meta.url))
    }
  },
  define: {
    __VUE_PROD_DEVTOOLS__: false,
    'process.env.NODE_ENV': '"test"'
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.{test,spec}.{js,ts}'],
    exclude: [...configDefaults.exclude, 'e2e/*'],
    root: fileURLToPath(new URL('./', import.meta.url)),
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html']
    },
    globalSetup: ['./src/tests/global-setup.ts'],
    setupFiles: ['./src/tests/setup.ts'],
    globals: true,
    pool: 'forks',
    isolate: true,
    deps: {
      inline: [/pinia/]
    }
  }
})
