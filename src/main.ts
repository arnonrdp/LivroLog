import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { LocalStorage, Meta, Notify, Quasar } from 'quasar'
import lang from 'quasar/lang/pt-BR.js'
import { createApp } from 'vue'
import { i18n } from './i18n'

import '@quasar/extras/material-icons/material-icons.css'
import 'quasar/src/css/index.sass'

import App from './App.vue'
import router from './router'

const app = createApp(App)
const pinia = createPinia()

pinia.use(piniaPluginPersistedstate)

app.use(Quasar, {
  plugins: { LocalStorage, Notify, Meta },
  lang: lang
})
app.use(router)
app.use(pinia)
app.use(i18n)

app.mount('#app')
