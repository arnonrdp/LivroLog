import { createPinia } from 'pinia'
import { Quasar } from 'quasar'
import { createApp } from 'vue'
import { i18n } from './i18n'
import quasarUserOptions from './quasar-user-options'

import App from './App.vue'
import router from './router'

const app = createApp(App)

app.use(Quasar, quasarUserOptions)
app.use(router)
app.use(createPinia())
app.use(i18n)

app.mount('#app')
