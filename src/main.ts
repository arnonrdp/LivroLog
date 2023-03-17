import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { Quasar } from 'quasar'
import { createApp } from 'vue'
import { i18n } from './i18n'
import quasarUserOptions from './quasar-user-options'

import App from './App.vue'
import router from './router'

const app = createApp(App)
const pinia = createPinia()

pinia.use(piniaPluginPersistedstate)

app.use(Quasar, quasarUserOptions)
app.use(router)
app.use(pinia)
app.use(i18n)

app.mount('#app')
