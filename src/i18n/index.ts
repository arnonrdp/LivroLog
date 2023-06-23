import { createI18n } from 'vue-i18n'
import en from './en.json'
import ja from './ja.json'
import pt from './pt-br.json'
import tr from './tr.json'

const messages = { en, pt, ja, tr }

const i18n = createI18n({
  locale: navigator.language || 'en',
  messages,
  allowComposition: true,
  legacy: false,
  fallbackLocale: ['en', 'pt', 'ja', 'tr'],
  fallbackWarn: false,
  missingWarn: false
})

const localeOptions = [
  { value: 'en', label: 'English' },
  { value: 'ja', label: '日本語' },
  { value: 'pt', label: 'Português' },
  { value: 'tr', label: 'Türkçe' }
]

export { i18n, localeOptions }
