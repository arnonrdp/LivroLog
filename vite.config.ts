import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'

// https://vitejs.dev/config/
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          const chunks = ['axios', 'firebase', 'fsevents', 'lodash', 'pinia', 'quasar', 'typescript', 'vue', 'vue-i18n', 'vue-router']
          if (id.includes('/node_modules/')) {
            for (const chunkName of chunks) {
              if (id.includes(chunkName)) {
                return chunkName
              }
            }
          }
        }
      }
    }
  },
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  }
})
