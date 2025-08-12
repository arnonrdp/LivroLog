import { quasar, transformAssetUrls } from '@quasar/vite-plugin'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vitejs.dev/config/
export default defineConfig({
  build: {
    sourcemap: false, // Disable source maps to avoid eval() usage
    rollupOptions: {
      output: {
        manualChunks(id) {
          const chunks = ['axios', 'fsevents', 'lodash', 'pinia', 'quasar', 'typescript', 'vue', 'vue-i18n', 'vue-router']
          if (id.includes('/node_modules/')) {
            for (const chunkName of chunks) {
              if (id.includes(chunkName)) {
                return chunkName
              }
            }
          } else if (id.includes('/src/stores/')) {
            return 'stores'
          } else if (id.includes('/src/wrappers/')) {
            return 'wrappers'
          }
        }
      }
    }
  },

  // Configure development options to avoid eval()
  define: {
    __VUE_PROD_DEVTOOLS__: false,
    __VUE_OPTIONS_API__: true,
    __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false
  },

  plugins: [
    vue({
      template: { transformAssetUrls }
    }),

    quasar({
      sassVariables: fileURLToPath(new URL('./src/quasar-variables.sass', import.meta.url))
    }),

    vueDevTools()
  ],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },

  server: {
    headers: {
      'Cross-Origin-Opener-Policy': 'same-origin-allow-popups',
      'Cross-Origin-Embedder-Policy': 'unsafe-none'
    }
  }
})
