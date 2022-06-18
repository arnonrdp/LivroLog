import '@quasar/extras/material-icons/material-icons.css'
import { LocalStorage, Meta, Notify } from 'quasar'
import 'quasar/dist/quasar.css'
import lang from 'quasar/lang/pt-BR.js'

export default {
  config: {
    brand: {
      primary: '#4bb6aa',
      secondary: '#0071CE',
      warning: '#FF9E16',
      background: '#eceff1'
    },
    notify: {
      html: true,
      position: 'top',
      progress: true,
      timeout: 3000,
      multiline: true
    }
  },
  plugins: { LocalStorage, Notify, Meta },
  lang: lang
}
