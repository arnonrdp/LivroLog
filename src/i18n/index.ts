import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import ja from './locales/ja.json'
import pt from './locales/pt-br.json'
import tr from './locales/tr.json'

const i18n = createI18n({
  locale: navigator.language || 'en',
  messages: { en, pt, ja, tr },
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
