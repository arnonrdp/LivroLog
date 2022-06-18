import { createI18n } from 'vue-i18n'
import en from './en.json'
import ja from './ja.json'
import pt from './pt-br.json'
import tr from './tr.json'

const messages = { en, pt, ja, tr }

type MessageSchema = typeof en

const i18n = createI18n<MessageSchema>({
  locale: navigator.language || 'en',
  fallbackLocale: ['en', 'pt', 'ja', 'tr'],
  silentTranslationWarn: true,
  silentFallbackWarn: true,
  messages
})

const localeOptions = [
  { value: 'en', label: 'English' },
  { value: 'ja', label: '日本語' },
  { value: 'pt' && 'pt-BR', label: 'Português' },
  { value: 'tr', label: 'Türkçe' }
]

export { i18n, localeOptions }
