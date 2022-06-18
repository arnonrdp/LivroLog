import { createPinia } from 'pinia'
import { Quasar } from 'quasar'
import { createApp } from 'vue'
import App from './App.vue'
import { i18n } from './i18n'
import quasarUserOptions from './quasar-user-options'
import router from './router'

createApp(App).use(Quasar, quasarUserOptions).use(router).use(createPinia()).use(i18n).mount('#app')
