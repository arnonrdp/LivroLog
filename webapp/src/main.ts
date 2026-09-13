import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { LocalStorage, Meta, Notify, Quasar } from 'quasar'
import SecureLSModule from 'secure-ls'
import { registerSW } from 'virtual:pwa-register'
import { createApp } from 'vue'
import { i18n } from './locales'

// Import Quasar CSS first
import 'quasar/src/css/index.sass'

// Import icon libraries
import '@quasar/extras/material-icons/material-icons.css'

// Import custom styles after Quasar to override defaults
import './assets/main.sass'

import App from './App.vue'
import router from './router'

Notify.registerType('positive', { icon: 'check_circle', textColor: 'white' })
Notify.registerType('negative', { color: 'negative', icon: 'error', textColor: 'white' })

const app = createApp(App)
const pinia = createPinia()

// secure-ls ships a webpack CJS bundle that sets __esModule at runtime, so the
// bundler cannot see it statically and hands us the namespace instead of the class.
const SecureLS = (SecureLSModule as unknown as { default?: typeof SecureLSModule }).default ?? SecureLSModule

const secureLS = new SecureLS({ encryptionSecret: import.meta.env.VITE_SECURE_LS })

pinia.use(
  createPersistedState({
    storage: {
      getItem: (key) => secureLS.get(key),
      setItem: (key, value) => secureLS.set(key, value),
      removeItem: (key) => secureLS.remove(key)
    } as Storage
  })
)

app.use(Quasar, {
  plugins: { LocalStorage, Notify, Meta },
  config: {
    notify: { position: 'bottom-right', progress: true, timeout: 5000 }
  }
})
app.use(router)
app.use(pinia)
app.use(i18n)

// Register Service Worker with update prompt
const updateSW = registerSW({
  immediate: true,
  onNeedRefresh() {
    Notify.create({
      message: 'Nova versão disponível!',
      caption: 'Clique para atualizar',
      icon: 'system_update',
      color: 'primary',
      timeout: 0,
      actions: [
        {
          label: 'Atualizar',
          color: 'white',
          handler: () => updateSW(true)
        },
        {
          label: 'Depois',
          color: 'white',
          handler: () => {}
        }
      ]
    })
  },
  onOfflineReady() {
    console.log('App ready for offline use')
  }
})

app.mount('#app')
